<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use PHPUnit\Framework\ExecutionOrderDependency;
use PHPUnit\Metadata\Api\DataProvider;
use PHPUnit\Metadata\Api\Dependencies;
use PHPUnit\Metadata\Api\Groups;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\TestSuite;
use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\FilterMapper;
use PHPUnit\TextUI\XmlConfiguration\LoadedFromFileConfiguration;
use ReflectionMethod;
use RuntimeException;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\StaticAnalysis\CacheWarmer;
use SebastianBergmann\Environment\Runtime;
use SebastianBergmann\FileIterator\Facade;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function ksort;
use function preg_match;
use function realpath;
use function sprintf;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function version_compare;

use const PHP_VERSION;

/** @internal */
final class SuiteLoader
{
    /**
     * The collection of loaded files.
     *
     * @var string[]
     */
    private array $files = [];

    /** @var string[]|null */
    private ?array $suitesName = null;

    /**
     * The collection of parsed test classes.
     *
     * @var array<string, ExecutableTest>
     */
    private array $loadedSuites = [];

    private ?LoadedFromFileConfiguration $configuration;
    private Options $options;
    private OutputInterface $output;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options       = $options;
        $this->configuration = $options->configuration();
        $this->output        = $output;
    }

    /**
     * Returns all parsed suite objects as ExecutableTest
     * instances.
     *
     * @return list<ExecutableTest>
     */
    public function getSuites(): array
    {
        return array_values($this->loadedSuites);
    }

    /**
     * Populates the loaded suite collection. Will load suites
     * based off a phpunit xml configuration or a specified path.
     *
     * @throws RuntimeException
     */
    public function load(): void
    {
        $this->loadConfiguration();

        $testSuiteCollection = null;
        if (($path = $this->options->path()) !== null) {
            if (realpath($path) === false) {
                throw new RuntimeException("Invalid path {$path} provided");
            }

            $this->files = array_merge(
                $this->files,
                (new Facade())->getFilesAsArray($path, ['Test.php']),
            );
        } elseif (
            $this->options->parallelSuite()
            && $this->configuration !== null
            && ! $this->configuration->testSuite()->isEmpty()
        ) {
            $testSuiteCollection = $this->configuration->testSuite()->asArray();
            $this->suitesName    = array_map(static function (TestSuite $testSuite): string {
                return $testSuite->name();
            }, $testSuiteCollection);
        } elseif (
            $this->configuration !== null
            && ! $this->configuration->testSuite()->isEmpty()
        ) {
            $testSuiteCollection = array_filter(
                $this->configuration->testSuite()->asArray(),
                function (TestSuite $testSuite): bool {
                    return $this->options->testsuite() === [] ||
                        in_array($testSuite->name(), $this->options->testsuite(), true);
                },
            );

            foreach ($testSuiteCollection as $testSuite) {
                $this->loadFilesFromTestSuite($testSuite);
            }
        }

        if ($path === null && $testSuiteCollection === null) {
            throw new RuntimeException('No path or configuration provided (tests must end with Test.php)');
        }

        $this->files = array_unique($this->files); // remove duplicates

        $this->initSuites();
        $this->warmCoverageCache();
    }

    /**
     * Called after all files are loaded. Parses loaded files into
     * ExecutableTest objects - either Suite or TestMethod or FullSuite.
     */
    private function initSuites(): void
    {
        if (is_array($this->suitesName)) {
            foreach ($this->suitesName as $suiteName) {
                $this->loadedSuites[$suiteName] = $this->createFullSuite($suiteName);
            }
        } else {
            // The $class->getParentsCount() + array_merge(...$loadedSuites) stuff
            // are needed to run test with child tests early, because PHPUnit autoloading
            // of such classes in WrapperRunner environments fails (Runner is fine)
            $loadedSuites = [];
            foreach ($this->files as $path) {
                try {
                    $class = (new Parser($path))->getClass();
                    $suite = $this->createSuite($path, $class);
                    if ($suite->getTestCount() > 0) {
                        $loadedSuites[$class->getParentsCount()][$path] = $suite;
                    }
                } catch (NoClassInFileException) {
                    continue;
                }
            }

            foreach (array_keys($loadedSuites) as $key) {
                ksort($loadedSuites[$key]);
            }

            ksort($loadedSuites);

            foreach ($loadedSuites as $loadedSuite) {
                $this->loadedSuites = array_merge($this->loadedSuites, $loadedSuite);
            }
        }
    }

    /**
     * @return int
     */
    private function executableTests(ParsedClass $class): int
    {
        $executableTests = [];
        $methodBatches   = $this->getMethodBatches($class);
        foreach ($methodBatches as $methodBatch) {
            $executableTests[] = count($methodBatch);
        }

        return array_sum($executableTests);
    }

    /**
     * Get method batches.
     *
     * Identify method dependencies, and group dependents and dependees on a single methodBatch.
     * Use max batch size to fill batches.
     *
     * @return string[][] of MethodBatches. Each MethodBatch has an array of method names
     */
    private function getMethodBatches(ParsedClass $class): array
    {
        $classMethods = $class->getMethods();

        $batches = [];
        foreach ($classMethods as $method) {
            $tests = $this->getMethodTests($class, $method);
            // if filter passed to paratest then method tests can be blank if not match to filter
            if (count($tests) === 0) {
                continue;
            }

            $dependencies = Dependencies::dependencies($class->getName(), $method->getName());
            if (count($dependencies) !== 0) {
                $this->addDependentTestsToBatchSet($batches, $dependencies, $tests);
            } else {
                foreach ($tests as $test) {
                    $batches[] = [$test];
                }
            }
        }

        return $batches;
    }

    /**
     * @param string[][]                 $batches
     * @param ExecutionOrderDependency[] $dependencies
     * @param string[]                   $tests
     */
    private function addDependentTestsToBatchSet(array &$batches, array $dependencies, array $tests): void
    {
        $dependencies = array_map(static function (ExecutionOrderDependency $dependency): string {
            return substr($dependency->getTarget(), (int) strrpos($dependency->getTarget(), ':') + 1);
        }, $dependencies);

        foreach ($batches as $key => $batch) {
            foreach ($batch as $methodName) {
                if (!in_array($methodName, $dependencies, true)) {
                    continue;
                }

                $batches[$key] = array_merge($batches[$key], $tests);
            }
        }
    }

    /**
     * Get method all available tests.
     *
     * With empty filter this method returns single test if doesn't have data provider or
     * data provider is not used and return all test if has data provider and data provider is used.
     *
     * @return string[] array of test names
     * @psalm-return list<string>
     */
    private function getMethodTests(ParsedClass $class, ReflectionMethod $method): array
    {
        $result = [];

        $groups = (new Groups())->groups($class->getName(), $method->getName());
        if (! $this->testMatchGroupOptions($groups)) {
            return $result;
        }

        try {
            $providedData = (new DataProvider())->providedData($class->getName(), $method->getName());
        } catch (Throwable) {
            $providedData = null;
        }

        if ($providedData === null) {
            return [$method->getName()];
        }

        foreach (array_keys($providedData) as $key) {
            $test = sprintf(
                '%s with data set %s',
                $method->getName(),
                is_int($key) ? '#' . $key : '"' . $key . '"',
            );

            $result[] = $test;
        }

        return $result;
    }

    /** @param string[] $groups */
    private function testMatchGroupOptions(array $groups): bool
    {
        if ($this->options->group() === [] && $this->options->excludeGroup() === []) {
            return true;
        }

        $matchGroupIncluded = (
            $this->options->group() !== []
            && array_intersect($groups, $this->options->group()) !== []
        );

        $matchGroupNotExcluded = (
            $this->options->excludeGroup() !== []
            && array_intersect($groups, $this->options->excludeGroup()) === []
        );

        return $matchGroupIncluded || $matchGroupNotExcluded;
    }

    private function createSuite(string $path, ParsedClass $class): Suite
    {
        return new Suite($this->executableTests($class), $path);
    }

    private function createFullSuite(string $suiteName): FullSuite
    {
        return new FullSuite($suiteName);
    }

    /** @see \PHPUnit\TextUI\XmlConfiguration\TestSuiteMapper::map */
    private function loadFilesFromTestSuite(TestSuite $testSuiteCollection): void
    {
        foreach ($testSuiteCollection->directories() as $directory) {
            if (
                ! version_compare(
                    PHP_VERSION,
                    $directory->phpVersion(),
                    $directory->phpVersionOperator()->asString(),
                )
            ) {
                continue; // @codeCoverageIgnore
            }

            $exclude = [];

            foreach ($testSuiteCollection->exclude()->asArray() as $file) {
                $exclude[] = $file->path();
            }

            $this->files = array_merge($this->files, (new Facade())->getFilesAsArray(
                $directory->path(),
                $directory->suffix(),
                $directory->prefix(),
                $exclude,
            ));
        }

        foreach ($testSuiteCollection->files() as $file) {
            if (
                ! version_compare(
                    PHP_VERSION,
                    $file->phpVersion(),
                    $file->phpVersionOperator()->asString(),
                )
            ) {
                continue; // @codeCoverageIgnore
            }

            $this->files[] = $file->path();
        }
    }

    private function loadConfiguration(): void
    {
        if ($this->configuration !== null) {
            (new PhpHandler())->handle($this->configuration->php());
        }

        $bootstrap = null;
        if ($this->options->bootstrap() !== null) {
            $bootstrap = $this->options->bootstrap();
        } elseif ($this->configuration !== null && $this->configuration->phpunit()->hasBootstrap()) {
            $bootstrap = $this->configuration->phpunit()->bootstrap();
        }

        if ($bootstrap === null) {
            return;
        }

        include_once $bootstrap;
    }

    private function warmCoverageCache(): void
    {
        if (
            ! $this->options->hasCoverage()
            || ! (new Runtime())->canCollectCodeCoverage()
            || ($configuration = $this->options->configuration()) === null
            || ! $configuration->codeCoverage()->hasCacheDirectory()
        ) {
            return;
        }

        $filter = new Filter();
        (new FilterMapper())->map(
            $filter,
            $configuration->codeCoverage(),
        );
        $timer = new Timer();
        $timer->start();

        $this->output->write('Warming cache for static analysis ... ');

        (new CacheWarmer())->warmCache(
            $configuration->codeCoverage()->cacheDirectory()->path(),
            ! $configuration->codeCoverage()->disableCodeCoverageIgnore(),
            $configuration->codeCoverage()->ignoreDeprecatedCodeUnits(),
            $filter,
        );

        $this->output->write(sprintf("done [%s]\n\n", $timer->stop()->asString()));
    }

    /**
     * @see \PHPUnit\Framework\TestSuite::containsOnlyVirtualGroups
     *
     * @param string[] $groups
     */
    private function containsOnlyVirtualGroups(array $groups): bool
    {
        foreach ($groups as $group) {
            if (strpos($group, '__phpunit_') !== 0) {
                return false;
            }
        }

        return true;
    }
}
