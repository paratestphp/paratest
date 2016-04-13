<?php
namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\ParsedObject;
use ParaTest\Parser\Parser;

class SuiteLoader
{
    /**
     * The collection of loaded files
     *
     * @var array
     */
    protected $files = array();

    /**
     * The collection of parsed test classes
     *
     * @var array
     */
    protected $loadedSuites = array();

    public function __construct($options = null)
    {
        if ($options && !$options instanceof Options) {
            throw new \InvalidArgumentException("SuiteLoader options must be null or of type Options");
        }

        $this->options = $options;
    }

    /**
     * Returns all parsed suite objects as ExecutableTest
     * instances
     *
     * @return array
     */
    public function getSuites()
    {
        return $this->loadedSuites;
    }

    /**
     * Returns a collection of TestMethod objects
     * for all loaded ExecutableTest instances
     *
     * @return array
     */
    public function getTestMethods()
    {
        $methods = array();
        foreach ($this->loadedSuites as $suite) {
            $methods = array_merge($methods, $suite->getFunctions());
        }

        return $methods;
    }

    /**
     * Populates the loaded suite collection. Will load suites
     * based off a phpunit xml configuration or a specified path
     *
     * @param string $path
     * @throws \RuntimeException
     */
    public function load($path = '')
    {
        if (is_object($this->options) && isset($this->options->filtered['configuration'])) {
            $configuration = $this->options->filtered['configuration'];
        } else {
            $configuration = new Configuration('');
        }

        if ($path) {
            $testFileLoader = new TestFileLoader($this->options);
            $this->files = array_merge($this->files, $testFileLoader->loadPath($path));
        } elseif (isset($this->options->testsuite) && $this->options->testsuite) {
            foreach ($configuration->getSuiteByName($this->options->testsuite) as $suite) {
                foreach ($suite as $suitePath) {
                    $testFileLoader = new TestFileLoader($this->options);
                    $this->files = array_merge($this->files, $testFileLoader->loadSuitePath($suitePath));
                }
            }
        } elseif ($suites = $configuration->getSuites()) {
            foreach ($suites as $suite) {
                foreach ($suite as $suitePath) {
                    $testFileLoader = new TestFileLoader($this->options);
                    $this->files = array_merge($this->files, $testFileLoader->loadSuitePath($suitePath));
                }
            }
        }


        if (!$this->files) {
            throw new \RuntimeException("No path or configuration provided (tests must end with Test.php)");
        }

        $this->files = array_unique($this->files); // remove duplicates

        $this->initSuites();
    }


    /**
     * Called after all files are loaded. Parses loaded files into
     * ExecutableTest objects - either Suite or TestMethod
     */
    private function initSuites()
    {
        foreach ($this->files as $path) {
            try {
                $parser = new Parser($path);
                if ($class = $parser->getClass()) {
                    $this->loadedSuites[$path] = $this->createSuite($path, $class);
                }
            } catch (NoClassInFileException $e) {
                continue;
            }
        }
    }

    private function executableTests($path, $class)
    {
        $executableTests = array();
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
     * @param  ParsedClass $class
     * @return array of MethodBatches. Each MethodBatch has an array of method names
     */
    private function getMethodBatches($class)
    {
        $classMethods = $class->getMethods($this->options ? $this->options->annotations : array());
        $maxBatchSize = $this->options && $this->options->functional ? $this->options->maxBatchSize : 0;
        $batches = array();
        foreach ($classMethods as $method) {
            $tests = $this->getMethodTests($class, $method, $maxBatchSize != 0);
            // if filter passed to paratest then method tests can be blank if not match to filter
            if (!$tests) {
                continue;
            }

            if (($dependsOn = $this->methodDependency($method)) != null) {
                $this->addDependentTestsToBatchSet($batches, $dependsOn, $tests);
            } else {
                $this->addTestsToBatchSet($batches, $tests, $maxBatchSize);
            }
        }
        return $batches;
    }

    private function addDependentTestsToBatchSet(&$batches, $dependsOn, $tests)
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

    private function addTestsToBatchSet(&$batches, $tests, $maxBatchSize)
    {
        foreach ($tests as $test) {
            $lastIndex = count($batches) - 1;
            if ($lastIndex != -1
                && count($batches[$lastIndex]) < $maxBatchSize
            ) {
                $batches[$lastIndex][] = $test;
            } else {
                $batches[] = array($test);
            }
        }
    }

    /**
     * Get method all available tests.
     *
     * With empty filter this method returns single test if doesnt' have data provider or
     * data provider is not used and return all test if has data provider and data provider is used.
     *
     * @param  ParsedClass  $class            Parsed class.
     * @param  ParsedObject $method           Parsed method.
     * @param  bool         $useDataProvider  Try to use data provider or not.
     * @return string[]     Array of test names.
     */
    private function getMethodTests($class, $method, $useDataProvider = false)
    {
        $result = array();

        $groups = $this->methodGroups($method);

        $dataProvider = $this->methodDataProvider($method);
        if ($useDataProvider && isset($dataProvider)) {
            $testFullClassName = "\\" . $class->getName();
            $testClass = new $testFullClassName();
            $result = array();
            $datasetKeys = array_keys($testClass->$dataProvider());
            foreach ($datasetKeys as $key) {
                $test = sprintf(
                    "%s with data set %s",
                    $method->getName(),
                    is_int($key) ? "#" . $key : "\"" . $key . "\""
                );
                if ($this->testMatchOptions($class->getName(), $test, $groups)) {
                    $result[] = $test;
                }
            }
        } elseif ($this->testMatchOptions($class->getName(), $method->getName(), $groups)) {
            $result = array($method->getName());
        }

        return $result;
    }

    private function testMatchGroupOptions($groups)
    {
        if (empty($groups)) {
            return true;
        }

        if (!empty($this->options->groups)
            && !array_intersect($groups, $this->options->groups)
        ) {
            return false;
        }

        if (!empty($this->options->excludeGroups)
            && array_intersect($groups, $this->options->excludeGroups)
        ) {
            return false;
        }

        return true;
    }

    private function testMatchFilterOptions($className, $name, $group)
    {
        if (empty($this->options->filter)) {
            return true;
        }

        $re = substr($this->options->filter, 0, 1) == "/"
            ? $this->options->filter
            : "/" . $this->options->filter . "/";
        $fullName = $className . "::" . $name;
        $result = preg_match($re, $fullName);

        return $result;
    }

    private function testMatchOptions($className, $name, $group)
    {
        $result = $this->testMatchGroupOptions($group)
                && $this->testMatchFilterOptions($className, $name, $group);

        return $result;
    }

    private function methodDataProvider($method)
    {
        if (preg_match("/@\bdataProvider\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function methodDependency($method)
    {
        if (preg_match("/@\bdepends\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function methodGroups($method)
    {
        if (preg_match_all("/@\bgroup\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }
        return array();
    }

    private function createSuite($path, ParsedClass $class)
    {
        return new Suite(
            $path,
            $this->executableTests(
                $path,
                $class
            ),
            $class->getName()
        );
    }
}
