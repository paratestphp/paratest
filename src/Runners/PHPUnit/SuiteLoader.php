<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\ParsedFunction;
use ParaTest\Parser\ParsedObject;
use ParaTest\Parser\Parser;

class SuiteLoader
{
    /**
     * The collection of loaded files.
     *
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $suitesName = null;

    /**
     * The collection of parsed test classes.
     *
     * @var array
     */
    protected $loadedSuites = [];

    /**
     * The configuration.
     *
     * @var Configuration|null
     */
    protected $configuration;

    /**
     * @var Options
     */
    public $options;

    public function __construct(Options $options = null)
    {
        $this->options = $options;

        $this->configuration = isset($this->options->filtered['configuration'])
            ? $this->options->filtered['configuration']
            : new Configuration('');
    }

    /**
     * Returns all parsed suite objects as ExecutableTest
     * instances.
     *
     * @return array
     */
    public function getSuites(): array
    {
        return $this->loadedSuites;
    }

    /**
     * Returns a collection of TestMethod objects
     * for all loaded ExecutableTest instances.
     *
     * @return array
     */
    public function getTestMethods(): array
    {
        $methods = [];
        foreach ($this->loadedSuites as $suite) {
            $methods = \array_merge($methods, $suite->getFunctions());
        }

        return $methods;
    }

    /**
     * Populates the loaded suite collection. Will load suites
     * based off a phpunit xml configuration or a specified path.
     *
     * @param string $path
     *
     * @throws \RuntimeException
     */
    public function load(string $path = ''): void
    {
        if ($path) {
            $testFileLoader = new TestFileLoader($this->options);
            $this->files = \array_merge(
                $this->files,
                $testFileLoader->loadPath($path)
            );
        } elseif (
            isset($this->options->parallelSuite)
            && $this->options->parallelSuite
        ) {
            $this->suitesName = $this->configuration->getSuitesName();
        } elseif ($this->configuration->hasSuites()) {
            if (!empty($this->options->testsuite)) {
                $suites = [];
                foreach ($this->options->testsuite as $testsuite) {
                    $suites = \array_merge($suites, $this->configuration->getSuiteByName($testsuite));
                }
            } else {
                $suites = $this->configuration->getSuites();
            }

            foreach ($suites as $suite) {
                foreach ($suite as $suitePath) {
                    $testFileLoader = new TestFileLoader($this->options);
                    $this->files = \array_merge(
                        $this->files,
                        $testFileLoader->loadSuitePath($suitePath)
                    );
                }
            }
        }

        if (!$this->files && !\is_array($this->suitesName)) {
            throw new \RuntimeException('No path or configuration provided (tests must end with Test.php)');
        }

        $this->files = \array_unique($this->files); // remove duplicates

        $this->initSuites();
    }

    /**
     * Called after all files are loaded. Parses loaded files into
     * ExecutableTest objects - either Suite or TestMethod or FullSuite.
     */
    protected function initSuites(): void
    {
        if (\is_array($this->suitesName)) {
            foreach ($this->suitesName as $suiteName) {
                $this->loadedSuites[$suiteName] = $this->createFullSuite($suiteName, $this->configuration->getPath());
            }
        } else {
            foreach ($this->files as $path) {
                try {
                    $parser = new Parser($path);
                    if ($class = $parser->getClass()) {
                        $suite = $this->createSuite($path, $class);
                        if (\count($suite->getFunctions()) > 0) {
                            $this->loadedSuites[$path] = $suite;
                        }
                    }
                } catch (NoClassInFileException $e) {
                    continue;
                }
            }
        }
    }

    protected function executableTests(string $path, ParsedClass $class): array
    {
        $executableTests = [];
        $methodBatches = $this->getMethodBatches($class);
        foreach ($methodBatches as $methodBatch) {
            $executableTest = new TestMethod($path, $methodBatch);
            $executableTests[] = $executableTest;
        }

        return $executableTests;
    }

    /**
     * Get method batches.
     *
     * Identify method dependencies, and group dependents and dependees on a single methodBatch.
     * Use max batch size to fill batches.
     *
     * @param ParsedClass $class
     *
     * @return array of MethodBatches. Each MethodBatch has an array of method names
     */
    protected function getMethodBatches(ParsedClass $class): array
    {
        $classMethods = $class->getMethods($this->options ? $this->options->annotations : []);
        $maxBatchSize = $this->options && $this->options->functional ? $this->options->maxBatchSize : 0;
        $batches = [];
        foreach ($classMethods as $method) {
            $tests = $this->getMethodTests($class, $method);
            // if filter passed to paratest then method tests can be blank if not match to filter
            if (!$tests) {
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

    protected function addDependentTestsToBatchSet(array &$batches, string $dependsOn, array $tests): void
    {
        foreach ($batches as $key => $batch) {
            foreach ($batch as $methodName) {
                if ($dependsOn === $methodName) {
                    $batches[$key] = \array_merge($batches[$key], $tests);
                    continue;
                }
            }
        }
    }

    protected function addTestsToBatchSet(array &$batches, array $tests, int $maxBatchSize): void
    {
        foreach ($tests as $test) {
            $lastIndex = \count($batches) - 1;
            if (
                $lastIndex !== -1
                && \count($batches[$lastIndex]) < $maxBatchSize
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
     * @param ParsedClass  $class  parsed class
     * @param ParsedObject $method parsed method
     *
     * @return string[] array of test names
     */
    protected function getMethodTests(ParsedClass $class, ParsedFunction $method): array
    {
        $result = [];

        $groups = $this->testGroups($class, $method);

        $dataProvider = $this->methodDataProvider($method);
        if (isset($dataProvider)) {
            $testFullClassName = '\\' . $class->getName();
            $testClass = new $testFullClassName();
            $result = [];
            foreach ($testClass->$dataProvider() as $key => $value) {
                $test = \sprintf(
                    '%s with data set %s',
                    $method->getName(),
                    \is_int($key) ? '#' . $key : '"' . $key . '"'
                );
                if ($this->testMatchOptions($class->getName(), $test, $groups)) {
                    $result[] = $test;
                }
            }
        } elseif ($this->testMatchOptions($class->getName(), $method->getName(), $groups)) {
            $result = [$method->getName()];
        }

        return $result;
    }

    protected function testMatchGroupOptions(array $groups): bool
    {
        if (empty($groups)) {
            return true;
        }

        if (
            !empty($this->options->groups)
            && !\array_intersect($groups, $this->options->groups)
        ) {
            return false;
        }

        if (
            !empty($this->options->excludeGroups)
            && \array_intersect($groups, $this->options->excludeGroups)
        ) {
            return false;
        }

        return true;
    }

    protected function testMatchFilterOptions(string $className, string $name): bool
    {
        if (empty($this->options->filter)) {
            return true;
        }

        $re = \substr($this->options->filter, 0, 1) === '/'
            ? $this->options->filter
            : '/' . $this->options->filter . '/';
        $fullName = $className . '::' . $name;

        return 1 === \preg_match($re, $fullName);
    }

    protected function testMatchOptions(string $className, string $name, array $group): bool
    {
        $result = $this->testMatchGroupOptions($group)
                && $this->testMatchFilterOptions($className, $name);

        return $result;
    }

    protected function testGroups(ParsedClass $class, ParsedFunction $method): array
    {
        return \array_merge(
            $this->classGroups($class),
            $this->methodGroups($method)
        );
    }

    protected function methodDataProvider(ParsedFunction $method): ?string
    {
        if (\preg_match("/@\bdataProvider\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function methodDependency(ParsedFunction $method): ?string
    {
        if (\preg_match("/@\bdepends\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function methodGroups(ParsedFunction $method): array
    {
        if (\preg_match_all("/@\bgroup\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return [];
    }

    protected function classGroups(ParsedClass $class): array
    {
        if (\preg_match_all("/@\bgroup\b \b(.*)\b/", $class->getDocBlock(), $matches)) {
            return $matches[1];
        }

        return [];
    }

    protected function createSuite(string $path, ParsedClass $class): Suite
    {
        return new Suite(
            $path,
            $this->executableTests(
                $path,
                $class
            )
        );
    }

    private function createFullSuite($suiteName, $configPath): FullSuite
    {
        return new FullSuite($suiteName, $configPath);
    }
}
