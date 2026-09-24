<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use Generator;
use ParaTest\Options;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Framework\DataProviderTestSuite;
use PHPUnit\Framework\IterativeTestSuite;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\ExecutionOrder\ReorderPipeline;
use PHPUnit\Runner\Extension\ExtensionBootstrapper;
use PHPUnit\Runner\Extension\ExtensionFacade;
use PHPUnit\Runner\Extension\PharLoader;
use PHPUnit\Runner\Phpt\TestCase as PhptTestCase;
use PHPUnit\Runner\TestRunHistory\DefaultTestRunHistory;
use PHPUnit\Runner\TestRunHistory\NullTestRunHistory;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TextUI\Command\Result;
use PHPUnit\TextUI\Command\WarmCodeCoverageCacheCommand;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\TestSuiteBuilder;
use PHPUnit\TextUI\TestSuiteFilterProcessor;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function array_values;
use function assert;
use function ceil;
use function count;
use function is_string;
use function mt_srand;
use function ob_get_clean;
use function ob_start;
use function preg_quote;
use function spl_object_id;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_KEY;

/** @internal */
final readonly class SuiteLoader
{
    public int $testCount;
    /** @var list<non-empty-list<non-empty-string>> Work items, grouped by the worker that must run them together */
    public array $tests;

    public function __construct(
        private Options $options,
        OutputInterface $output,
        CodeCoverageFilterRegistry $codeCoverageFilterRegistry,
    ) {
        $emitter = EventFacade::emitter();
        (new PhpHandler($emitter))->handle($this->options->configuration->php());

        if ($this->options->configuration->hasBootstrap()) {
            $bootstrapFilename = $this->options->configuration->bootstrap();
            include_once $bootstrapFilename;
            $emitter->testRunnerBootstrapFinished($bootstrapFilename);
        }

        if (! $this->options->configuration->noExtensions()) {
            if ($this->options->configuration->hasPharExtensionDirectory()) {
                (new PharLoader($emitter))->loadPharExtensionsInDirectory(
                    $this->options->configuration->pharExtensionDirectory(),
                );
            }

            $extensionFacade       = new ExtensionFacade();
            $extensionBootstrapper = new ExtensionBootstrapper(
                $this->options->configuration,
                $extensionFacade,
                $emitter,
            );

            foreach ($this->options->configuration->extensionBootstrappers() as $bootstrapper) {
                $extensionBootstrapper->bootstrap(
                    $bootstrapper['className'],
                    $bootstrapper['parameters'],
                );
            }
        }

        TestResultFacade::init();
        EventFacade::instance()->seal();

        $testSuite = (new TestSuiteBuilder($emitter))->build($this->options->configuration);

        $groups = DependencyGrouper::group($this->workItems($testSuite));

        if ($this->options->hasShard()) {
            $this->shardTests($testSuite, $groups);
        }

        if ($this->options->configuration->executionOrder() === TestSuiteSorter::ORDER_RANDOMIZED) {
            mt_srand($this->options->configuration->randomOrderSeed());
        }

        if (
            $this->options->configuration->executionOrder() !== TestSuiteSorter::ORDER_DEFAULT ||
            $this->options->configuration->executionOrderDefects() !== TestSuiteSorter::ORDER_DEFAULT ||
            $this->options->configuration->resolveDependencies()
        ) {
            $resultCache = new NullTestRunHistory();
            if ($this->options->configuration->recordTestRunHistory()) {
                $resultCache = new DefaultTestRunHistory($this->options->configuration->testRunHistoryFile());
                $resultCache->load();
            }

            $pipeline = ReorderPipeline::fromConfiguration(
                $this->options->configuration->executionOrder(),
                $this->options->configuration->executionOrderDefects(),
                $this->options->configuration->resolveDependencies(),
            );
            if (! $pipeline->isEmpty()) {
                (new TestSuiteSorter($resultCache))->apply($testSuite, $pipeline);
            }
        }

        (new TestSuiteFilterProcessor($emitter))->process($this->options->configuration, $testSuite);

        $this->testCount = count($testSuite);

        $tests = [];
        foreach ($this->workItems($testSuite) as $workItem => $test) {
            $tests[$groups[$workItem]][$workItem] = $workItem;
        }

        $this->tests = array_values(array_map(array_values(...), $tests));

        if (! $this->options->configuration->hasCoverageReport()) {
            return;
        }

        ob_start();
        $result       = (new WarmCodeCoverageCacheCommand(
            $this->options->configuration,
            $codeCoverageFilterRegistry,
        ))->execute();
        $ob_get_clean = ob_get_clean();
        assert($ob_get_clean !== false);
        $output->write($ob_get_clean);
        $output->write($result->output());
        if ($result->shellExitCode() !== Result::SUCCESS) {
            exit($result->shellExitCode());
        }
    }

    /**
     * A work item is what a worker gets to run: a file, or a single test with --functional
     *
     * @return Generator<non-empty-string, (PhptTestCase|TestCase)>
     */
    private function workItems(Test $test): Generator
    {
        if ($test instanceof IterativeTestSuite) {
            // The repetitions or attempts of a test run as one unit: queue it once
            $tests = $test->tests();
            assert($tests !== []);
            $test = $tests[0];
        } elseif ($test instanceof TestSuite) {
            foreach ($test as $childTest) {
                yield from $this->workItems($childTest);
            }

            return;
        }

        if ($test instanceof PhptTestCase) {
            $refProperty = new ReflectionProperty(PhptTestCase::class, 'filename');
            $filename    = $refProperty->getValue($test);
            assert(is_string($filename) && $filename !== '');

            yield $this->stripCwd($filename) => $test;

            return;
        }

        if (! $test instanceof TestCase) {
            return;
        }

        $refClass = new ReflectionClass($test);
        $filename = $refClass->getFileName();
        assert(is_string($filename));
        $filename = $this->stripCwd($filename);

        if (! $this->options->functional) {
            yield $filename => $test;

            return;
        }

        $name = $test->providedData() !== []
            ? sprintf('/::%s%s$/', preg_quote($test->name(), '/'), preg_quote($test->dataSetAsString(), '/'))
            : sprintf('/::%s$/', $test->name());

        yield "$filename\0$name" => $test;
    }

    /**
     * @param non-empty-string $filename
     *
     * @return non-empty-string
     */
    private function stripCwd(string $filename): string
    {
        if (! str_starts_with($filename, $this->options->cwd)) {
            return $filename;
        }

        $substr = substr($filename, 1 + strlen($this->options->cwd));
        assert($substr !== '');

        return $substr;
    }

    /**
     * Shards over groups of items, so that tests depending on each other end up in the same shard
     *
     * @param array<non-empty-string, non-empty-string> $groups
     */
    private function shardTests(TestSuite $suite, array $groups): void
    {
        $shards  = $this->options->totalShards;
        $current = $this->options->currentShard - 1; // 0 indexed. Shard 1 is in reality shard 0

        // With --functional, shard over individual test methods (values).
        // Without --functional, shard over class-level suites (keys) to keep whole files together.
        $items = $this->options->functional
            ? $this->extractTestsInSuite($suite)
            : $this->extractClassSuites($suite);

        // Items without tests, as test classes without test methods, belong to no group and are dropped
        $itemGroups = [];
        foreach ($items as $item) {
            foreach ($this->workItems($item) as $workItem => $test) {
                $itemGroups[$groups[$workItem]][spl_object_id($item)] = $item;
            }
        }

        $itemGroups = array_values($itemGroups);

        $shardedItemGroups = match ($this->options->shardDistribution) {
            ShardDistribution::Sequential => array_slice($itemGroups, (int) ceil(count($itemGroups) / $shards) * $current, (int) ceil(count($itemGroups) / $shards)),
            ShardDistribution::RoundRobin => array_values(array_filter($itemGroups, static fn (int $i): bool => $i % $shards === $current, ARRAY_FILTER_USE_KEY)),
            ShardDistribution::Random => $this->randomShardTests($itemGroups, $shards, $current),
        };

        $suite->setTests(array_merge(...$shardedItemGroups));
    }

    /**
     * @param list<array<int, Test>> $itemGroups
     *
     * @return list<array<int, Test>>
     */
    private function randomShardTests(array $itemGroups, int $shards, int $current): array
    {
        $randomizer = new Randomizer(new Mt19937($this->options->shardDistributionSeed));
        /** @var list<array<int, Test>> $itemGroups */
        $itemGroups = $randomizer->shuffleArray($itemGroups);

        return array_values(array_filter($itemGroups, static fn (int $i): bool => $i % $shards === $current, ARRAY_FILTER_USE_KEY));
    }

    /** @return list<TestSuite> */
    private function extractClassSuites(TestSuite $suite): array
    {
        $classSuites = [];

        foreach ($suite->tests() as $item) {
            if (! ($item instanceof TestSuite)) {
                continue;
            }

            $children = $item->tests();
            if (
                $children !== []
                && $children[0] instanceof TestSuite
                && ! ($children[0] instanceof DataProviderTestSuite)
            ) {
                $classSuites = array_merge($classSuites, $this->extractClassSuites($item));
            } else {
                $classSuites[] = $item;
            }
        }

        return $classSuites;
    }

    /** @return list<Test> */
    private function extractTestsInSuite(TestSuite $suite): array
    {
        $extractedTests = [];
        $suiteItems     = $suite->tests();

        foreach ($suiteItems as $item) {
            if ($item instanceof TestSuite) {
                $extractedTests = array_merge($extractedTests, $this->extractTestsInSuite($item));
            } else {
                $extractedTests[] = $item;
            }
        }

        return $extractedTests;
    }
}
