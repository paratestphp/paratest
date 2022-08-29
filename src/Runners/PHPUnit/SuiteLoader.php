<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use PHPUnit\Framework\ExecutionOrderDependency;
use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\FilterMapper;
use PHPUnit\TextUI\XmlConfiguration\Configuration;
use PHPUnit\TextUI\XmlConfiguration\PhpHandler;
use PHPUnit\TextUI\XmlConfiguration\TestSuite;
use PHPUnit\Util\FileLoader;
use PHPUnit\Util\Test;
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
    private $files = [];

    /** @var string[]|null */
    private $suitesName = null;

    /**
     * The collection of parsed test classes.
     *
     * @var array<string, ExecutableTest>
     */
    private $loadedSuites = [];

    /**
     * The configuration.
     *
     * @var Configuration|null
     */
    private $configuration;

    /** @var Options */
    private $options;
    /** @var OutputInterface */
    private $output;

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
     * @return ExecutableTest[]
     * @psalm-return list<ExecutableTest>
     */
    public function getSuites(): array
    {
        return array_values($this->loadedSuites);
    }

    /**
     * Returns a collection of TestMethod objects
     * for all loaded ExecutableTest instances.
     *
     * @return TestMethod[]
     */
    public function getTestMethods(): array
    {
        $methods = [];
        foreach ($this->loadedSuites as $suite) {
            assert($suite instanceof Suite);
            $methods = array_merge($methods, $suite->getFunctions());
        }

        return $methods;
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
                    if (count($suite->getFunctions()) > 0) {
                        $loadedSuites[$class->getParentsCount()][$path] = $suite;
                    }
                } catch (NoClassInFileException $e) {
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
     * @return TestMethod[]
     * @psalm-return list<TestMethod>
     */
    private function executableTests(string $path, ParsedClass $class): array
    {
        $executableTests = [];
        $methodBatches   = $this->getMethodBatches($class);
        foreach ($methodBatches as $methodBatch) {
            $executableTests[] = new TestMethod(
                $path,
                $methodBatch,
                $this->options->hasCoverage(),
                $this->options->needsTeamcity(),
                $this->options->tmpDir(),
            );
        }

        return $executableTests;
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
        $maxBatchSize = $this->options->functional() ? $this->options->maxBatchSize() : 0;
        assert($maxBatchSize !== null);

        $batches = [];
        foreach ($classMethods as $method) {
            $tests = $this->getMethodTests($class, $method);
            // if filter passed to paratest then method tests can be blank if not match to filter
            if (count($tests) === 0) {
                continue;
            }

            $dependencies = Test::getDependencies($class->getName(), $method->getName());
            if (count($dependencies) !== 0) {
                $this->addDependentTestsToBatchSet($batches, $dependencies, $tests);
            } else {
                $this->addTestsToBatchSet($batches, $tests, $maxBatchSize);
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
                if (in_array($methodName, $dependencies, true)) {
                    $batches[$key] = array_merge($batches[$key], $tests);
                    continue;
                }
            }
        }
    }

    /**
     * @param string[][] $batches
     * @param string[]   $tests
     */
    private function addTestsToBatchSet(array &$batches, array $tests, int $maxBatchSize): void
    {
        foreach ($tests as $test) {
            $lastIndex = count($batches) - 1;
            if (
                $lastIndex !== -1
                && count($batches[$lastIndex]) < $maxBatchSize
            ) {
                $batches[$lastIndex][] = $test;
            } else {
                $batches[] = [$test];
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

        /** @var string[] $groups */
        $groups = Test::getGroups($class->getName(), $method->getName());
        if ($this->containsOnlyVirtualGroups($groups)) {
            $groups[] = 'default';
        }

        if (! $this->testMatchGroupOptions($groups)) {
            return $result;
        }

        try {
            $providedData = Test::getProvidedData($class->getName(), $method->getName());
        } catch (Throwable $throwable) {
            $providedData = null;
        }

        if ($providedData !== null) {
            foreach (array_keys($providedData) as $key) {
                $test = sprintf(
                    '%s with data set %s',
                    $method->getName(),
                    is_int($key) ? '#' . $key : '"' . $key . '"',
                );
                if (! $this->testMatchFilterOptions($class->getName(), $test)) {
                    continue;
                }

                $result[] = $test;
            }
        } elseif ($this->testMatchFilterOptions($class->getName(), $method->getName())) {
            $result = [$method->getName()];
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

    private function testMatchFilterOptions(string $className, string $name): bool
    {
        if (($filter = $this->options->filter()) === null) {
            return true;
        }

        $re       = '/' . trim($filter, '/') . '/';
        $fullName = $className . '::' . $name;

        return preg_match($re, $fullName) === 1;
    }

    private function createSuite(string $path, ParsedClass $class): Suite
    {
        return new Suite(
            $path,
            $this->executableTests(
                $path,
                $class,
            ),
            $this->options->hasCoverage(),
            $this->options->needsTeamcity(),
            $this->options->tmpDir(),
        );
    }

    private function createFullSuite(string $suiteName): FullSuite
    {
        return new FullSuite(
            $suiteName,
            $this->options->hasCoverage(),
            $this->options->needsTeamcity(),
            $this->options->tmpDir(),
        );
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

        FileLoader::checkAndLoad($bootstrap);
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
