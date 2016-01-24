<?php


namespace ParaTest\Runners\PHPUnit;


class TestFileLoader
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
     * Used to ignore directory paths '.' and '..'
     *
     * @var string
     */
    private static $dotPattern = '/([.]+)$/';

    /**
     * The collection of loaded files for this test suite
     *
     * @var array
     */
    protected $files = array();

    /**
     * The collection of excluded files
     *
     * @var array
     */
    protected $excludedFiles = array();

    /**
     * When true, the SuiteLoader add the files to excluded files
     *
     * @var bool
     */
    protected $excludingFiles = false;


    public function __construct($options = null)
    {
        if ($options && !$options instanceof Options) {
            throw new \InvalidArgumentException("SuiteLoader options must be null or of type Options");
        }

        $this->options = $options;
    }

    /**
     * Loads a SuitePath and makes sure to
     * take into account the excluded directory / files
     *
     * @param SuitePath $path
     * @return string[]
     */
    public function loadSuitePath(SuitePath $path)
    {
        // First initialize the list of files and excluded files
        $this->files          = array();
        $this->excludedFiles  = array();
        $this->excludingFiles = true;
        foreach ($path->getExcludedPaths() as $excludedPath) {
            $this->loadPath($excludedPath, $path->getPattern());
        }

        // Load the SuitePath
        $this->excludingFiles = false;
        $this->loadPath($path->getPath(), $path->getPattern());

        // Reinitialise the excluded files
        $this->excludedFiles = array();

        return $this->files;
    }

    /**
     * Loads suites based on a specific path.
     * A valid path can be a directory or file
     *
     * @param $path
     * @param $pattern
     * @throws \InvalidArgumentException
     * @return string[]
     */
    public function loadPath($path, $pattern = null)
    {
        $this->files = array();
        $path        = $path ?: $this->options->path;
        $pattern     = is_null($pattern) ? self::TEST_PATTERN : $pattern;

        if (!file_exists($path)) {
            throw new \InvalidArgumentException("$path is not a valid directory or file");
        }
        if (is_dir($path)) {
            $this->loadDir($path, $pattern);
        } elseif (file_exists($path)) {
            $this->loadFile($path);
        }

        return $this->files;
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
        foreach ($files as $file) {
            $this->tryLoadTests($path.DIRECTORY_SEPARATOR.$file, $pattern);
        }
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
        if (preg_match($pattern, $path)) {
            if ($this->excludingFiles) {
                $this->excludedFiles[$path] = $path;
            } elseif (!array_key_exists($path, $this->excludedFiles)) {
                $this->files[] = $path;
            }
        }

        if (!preg_match(self::$dotPattern, $path) && is_dir($path)) {
            $this->loadDir($path, $pattern);
        }
    }

}