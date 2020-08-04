<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Parser\Parser;
use ReflectionClass;
use RuntimeException;

use function array_intersect;
use function array_merge;
use function array_unique;
use function assert;
use function count;
use function is_array;
use function is_int;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function substr;

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

    /** @var Options|null */
    public $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options;

        $this->configuration = $this->options->filtered['configuration'] ?? new Configuration('');
    }

    /**
     * Returns all parsed suite objects as ExecutableTest
     * instances.
     *
     * @return array<string, ExecutableTest>
     */
    public function getSuites(): array
    {
        return $this->loadedSuites;
    }

    /**
     * Returns a collection of TestMethod objects
     * for all loaded ExecutableTest instances.
     *
     * @return array<int, ParsedFunction|TestMethod>
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
    public function load(string $path = ''): void
    {
        if ($path !== '') {
            $testFileLoader = new TestFileLoader($this->options);
            $this->files    = array_merge(
                $this->files,
                $testFileLoader->loadPath($path)
            );
        } elseif (
            isset($this->options->parallelSuite)
            && $this->options->parallelSuite
        ) {
            $this->suitesName = $this->configuration->getSuitesName();
        } elseif ($this->configuration->hasSuites()) {
            if (is_array($this->options->testsuite) && count($this->options->testsuite) > 0) {
                $suites = [];
                foreach ($this->options->testsuite as $testsuite) {
                    $suites = array_merge($suites, $this->configuration->getSuiteByName($testsuite));
                }
            } else {
                $suites = $this->configuration->getSuites();
            }

            foreach ($suites as $suite) {
                foreach ($suite as $suitePath) {
                    $testFileLoader = new TestFileLoader($this->options);
                    $this->files    = array_merge(
                        $this->files,
                        $testFileLoader->loadSuitePath($suitePath)
                    );
                }
            }
        }

        if (count($this->files) === 0 && ! is_array($this->suitesName)) {
            throw new RuntimeException('No path or configuration provided (tests must end with Test.php)');
        }

        $this->files = array_unique($this->files); // remove duplicates

        $this->initSuites();
    }

    /**
     * Called after all files are loaded. Parses loaded files into
     * ExecutableTest objects - either Suite or TestMethod or FullSuite.
     */
    private function initSuites(): void
    {
        if (is_array($this->suitesName)) {
            foreach ($this->suitesName as $suiteName) {
                $this->loadedSuites[$suiteName] = $this->createFullSuite($suiteName, $this->configuration->getPath());
            }
        } else {
            foreach ($this->files as $path) {
                try {
                    $parser = new Parser($path);
                    if (($class = $parser->getClass()) !== null) {
                        $suite = $this->createSuite($path, $class);
                        if (count($suite->getFunctions()) > 0) {
                            $this->loadedSuites[$path] = $suite;
                        }
                    }
                } catch (NoClassInFileException $e) {
                    continue;
                }
            }
        }
    }

    /**
     * @return TestMethod[]
     */
    private function executableTests(string $path, ParsedClass $class): array
    {
        $executableTests = [];
        $methodBatches   = $this->getMethodBatches($class);
        foreach ($methodBatches as $methodBatch) {
            $executableTests[] = new TestMethod($path, $methodBatch);
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
        $classMethods = $class->getMethods($this->options !== null ? $this->options->annotations : []);
        $maxBatchSize = $this->options !== null && $this->options->functional ? $this->options->maxBatchSize : 0;
        $batches      = [];
        foreach ($classMethods as $method) {
            $tests = $this->getMethodTests($class, $method);
            // if filter passed to paratest then method tests can be blank if not match to filter
            if (count($tests) === 0) {
                continue;
            }

            if (($dependsOn = $this->methodDependency($method)) !== null) {
                $this->addDependentTestsToBatchSet($batches, $dependsOn, $tests);
            } else {
                $this->addTestsToBatchSet($batches, $tests, $maxBatchSize);
            }
        }

        return $batches;
    }

    /**
     * @param string[][] $batches
     * @param string[]   $tests
     */
    private function addDependentTestsToBatchSet(array &$batches, string $dependsOn, array $tests): void
    {
        foreach ($batches as $key => $batch) {
            foreach ($batch as $methodName) {
                if ($dependsOn === $methodName) {
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
     * @param ParsedClass    $class  parsed class
     * @param ParsedFunction $method parsed method
     *
     * @return string[] array of test names
     */
    private function getMethodTests(ParsedClass $class, ParsedFunction $method): array
    {
        $result = [];

        $groups = $this->testGroups($class, $method);

        $dataProvider = $this->methodDataProvider($method);
        if (isset($dataProvider)) {
            $testFullClassName = '\\' . $class->getName();
            $testClass         = new $testFullClassName();
            $result            = [];

            $testClassReflection = new ReflectionClass($testFullClassName);
            $dataProviderMethod  = $testClassReflection->getMethod($dataProvider);

            if ($dataProviderMethod->getNumberOfParameters() === 0) {
                $data = $dataProviderMethod->invoke($testClass);
            } else {
                $data = $dataProviderMethod->invoke($testClass, $method->getName());
            }

            foreach ($data as $key => $value) {
                $test = sprintf(
                    '%s with data set %s',
                    $method->getName(),
                    is_int($key) ? '#' . $key : '"' . $key . '"'
                );
                if (! $this->testMatchOptions($class->getName(), $test, $groups)) {
                    continue;
                }

                $result[] = $test;
            }
        } elseif ($this->testMatchOptions($class->getName(), $method->getName(), $groups)) {
            $result = [$method->getName()];
        }

        return $result;
    }

    /**
     * @param string[] $groups
     */
    private function testMatchGroupOptions(array $groups): bool
    {
        if (count($groups) === 0 || $this->options === null) {
            return true;
        }

        if (
            count($this->options->groups) > 0
            && count(array_intersect($groups, $this->options->groups)) === 0
        ) {
            return false;
        }

        return count($this->options->excludeGroups) === 0
            || count(array_intersect($groups, $this->options->excludeGroups)) === 0;
    }

    private function testMatchFilterOptions(string $className, string $name): bool
    {
        if ($this->options === null || $this->options->filter === null) {
            return true;
        }

        $re       = substr($this->options->filter, 0, 1) === '/'
            ? $this->options->filter
            : '/' . $this->options->filter . '/';
        $fullName = $className . '::' . $name;

        return preg_match($re, $fullName) === 1;
    }

    /**
     * @param string[] $group
     */
    private function testMatchOptions(string $className, string $name, array $group): bool
    {
        return $this->testMatchGroupOptions($group)
                && $this->testMatchFilterOptions($className, $name);
    }

    /**
     * @return string[]
     */
    private function testGroups(ParsedClass $class, ParsedFunction $method): array
    {
        return array_merge(
            $this->classGroups($class),
            $this->methodGroups($method)
        );
    }

    private function methodDataProvider(ParsedFunction $method): ?string
    {
        if (preg_match("/@\bdataProvider\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function methodDependency(ParsedFunction $method): ?string
    {
        if (preg_match("/@\bdepends\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function methodGroups(ParsedFunction $method): array
    {
        if (preg_match_all("/@\bgroup\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function classGroups(ParsedClass $class): array
    {
        if (preg_match_all("/@\bgroup\b \b(.*)\b/", $class->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return [];
    }

    private function createSuite(string $path, ParsedClass $class): Suite
    {
        return new Suite(
            $path,
            $this->executableTests(
                $path,
                $class
            )
        );
    }

    private function createFullSuite(string $suiteName, string $configPath): FullSuite
    {
        return new FullSuite($suiteName, $configPath);
    }
}
