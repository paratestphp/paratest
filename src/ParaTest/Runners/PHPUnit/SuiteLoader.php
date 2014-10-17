<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\Parser;

class SuiteLoader
{
    /**
     * The pattern used for grabbing test files. Uses the *Test.php convention
     * that PHPUnit defaults to.
     */
    const TEST_PATTERN = '/.+Test\.php$/';

    /**
     * Matches php files
     */
    const FILE_PATTERN = '/.+\.php$/';

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

    /**
     * Used to ignore directory paths '.' and '..'
     *
     * @var string
     */
    private static $dotPattern = '/([.]+)$/';

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
        foreach($this->loadedSuites as $suite)
            $methods = array_merge($methods, $suite->getFunctions());

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
            $this->loadPath($path);
        } elseif ($suites = $configuration->getSuites()) {
            foreach ($suites as $suite) {
                foreach ($suite as $suitePath) {
                    $this->loadPath($suitePath);
                }
            }
        }

        if (!$this->files) {
            throw new \RuntimeException("No path or configuration provided (tests must end with Test.php)");
        }

        $this->initSuites();
    }

    /**
     * Loads suites based on a specific path.
     * A valid path can be a directory or file
     *
     * @param $path
     * @throws \InvalidArgumentException
     */
    private function loadPath($path)
    {
        $path = $path ? : $this->options->path;
        if ($path instanceof SuitePath) {
            $pattern = $path->getPattern();
            $path = $path->getPath();
        } else {
            $pattern = self::TEST_PATTERN;
        }
        if (!file_exists($path))
            throw new \InvalidArgumentException("$path is not a valid directory or file");
        if (is_dir($path))
            $this->loadDir($path, $pattern);
        else if (file_exists($path))
            $this->loadFile($path);
    }

    /**
     * Loads suites from a directory
     *
     * @param string $path
     * @param string $pattern
     */
    private function loadDir($path, $pattern = self::TEST_PATTERN)
    {
        $files = scandir($path);
        foreach($files as $file)
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file, $pattern);
    }

    /**
     * Load a single suite file
     *
     * @param $path
     */
    private function loadFile($path)
    {
        $this->tryLoadTests($path, self::FILE_PATTERN);
    }

    /**
     * Attempts to load suites from a path.
     *
     * @param string $path
     * @param string $pattern regular expression for matching file names
     */
    private function tryLoadTests($path, $pattern = self::TEST_PATTERN)
    {
        if(preg_match($pattern, $path))
            $this->files[] = $path;

        if(!preg_match(self::$dotPattern, $path) && is_dir($path))
            $this->loadDir($path, $pattern);
    }

    /**
     * Called after all files are loaded. Parses loaded files into
     * ExecutableTest objects - either Suite or TestMethod
     */
    private function initSuites()
    {
        foreach ($this->files as $path) {
            $parser = new Parser($path);
            if ($class = $parser->getClass()) {
                $this->loadedSuites[$path] = new Suite(
                    $path,
                    $this->executableTests(
                        $path,
                        $class->getMethods($this->options ? $this->options->annotations : array())
                    ),
                    $class->getName()
                );
            }
        }
    }

    private function executableTests($path, $classMethods)
    {
        $executableTests = array();
        $methodBatches = $this->getMethodBatches($classMethods);
        foreach ($methodBatches as $methodBatch) {
            $executableTest = new TestMethod($path, implode('|', $methodBatch));
            $executableTests[] = $executableTest;
        }
        return $executableTests;
    }

    /**
     * Identify method dependencies, and group dependents and dependees on a single methodBatch
     * If no dependencies exist each methodBatch will contain a single method.
     * @param  array of ParsedFunction $classMethods
     * @return array of MethodBatches. Each MethodBatch has an array of method names
     */
    private function getMethodBatches($classMethods)
    {
        $methodBatches = array();
        foreach ($classMethods as $method) {
            if (($dependsOn = $this->methodDependency($method)) != null) {
                foreach ($methodBatches as $key => $methodBatch) {
                    foreach ($methodBatch as $methodName) {
                        if ($dependsOn === $methodName) {
                            $methodBatches[$key][] = $method->getName();
                            continue;
                        }
                    }
                }
            } else {
                $methodBatches[] = array($method->getName());
            }
        }
        return $methodBatches;
    }

    private function methodDependency($method)
    {
        if (preg_match("/@\bdepends\b \b(.*)\b/", $method->getDocBlock(), $matches)) {
            return $matches[1];
        }
        return null;
    }
}
