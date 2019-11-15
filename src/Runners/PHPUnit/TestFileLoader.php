<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

class TestFileLoader
{
    /**
     * The pattern used for grabbing test files. Uses the *Test.php convention
     * that PHPUnit defaults to.
     */
    private const TEST_PATTERN = '/.+Test\.php$/';

    /**
     * Matches php files.
     */
    private const FILE_PATTERN = '/.+\.php$/';

    /**
     * Used to ignore directory paths '.' and '..'.
     *
     * @var string
     */
    private static $dotPattern = '/([.]+)$/';

    /**
     * The collection of loaded files for this test suite.
     *
     * @var array
     */
    protected $files = [];

    /**
     * The collection of excluded files.
     *
     * @var array
     */
    protected $excludedFiles = [];

    /**
     * When true, the SuiteLoader add the files to excluded files.
     *
     * @var bool
     */
    protected $excludingFiles = false;

    public function __construct(Options $options = null)
    {
        $this->options = $options;
    }

    /**
     * Loads a SuitePath and makes sure to
     * take into account the excluded directory / files.
     *
     * @param SuitePath $path
     *
     * @return string[]
     */
    public function loadSuitePath(SuitePath $path): array
    {
        // First initialize the list of files and excluded files
        $this->files = [];
        $this->excludedFiles = [];
        $this->excludingFiles = true;
        foreach ($path->getExcludedPaths() as $excludedPath) {
            $this->loadPath($excludedPath, $path->getPattern());
        }

        // Load the SuitePath
        $this->excludingFiles = false;
        $this->loadPath($path->getPath(), $path->getPattern());

        // Reinitialise the excluded files
        $this->excludedFiles = [];

        return $this->files;
    }

    /**
     * Loads suites based on a specific path.
     * A valid path can be a directory or file.
     *
     * @param $path
     * @param $pattern
     *
     * @throws \InvalidArgumentException
     *
     * @return string[]
     */
    public function loadPath(string $path, string $pattern = null): array
    {
        $this->files = [];

        $pattern = $pattern ?? self::TEST_PATTERN;

        $path = $path ?: $this->options->path;
        if ($path instanceof SuitePath) {
            $pattern = $path->getPattern();
            $path = $path->getPath();
        }

        if (!\file_exists($path)) {
            throw new \InvalidArgumentException("$path is not a valid directory or file");
        }
        if (\is_dir($path)) {
            $this->loadDir($path, $pattern);
        } elseif (\file_exists($path)) {
            $this->loadFile($path);
        }

        return $this->files;
    }

    /**
     * Loads suites from a directory.
     *
     * @param string $path
     * @param string $pattern
     */
    private function loadDir(string $path, string $pattern = self::TEST_PATTERN)
    {
        $files = \scandir($path);
        foreach ($files as $file) {
            $this->tryLoadTests($path . \DIRECTORY_SEPARATOR . $file, $pattern);
        }
    }

    /**
     * Load a single suite file.
     *
     * @param $path
     */
    private function loadFile(string $path)
    {
        $this->tryLoadTests($path, self::FILE_PATTERN);
    }

    /**
     * Attempts to load suites from a path.
     *
     * @param string $path
     * @param string $pattern regular expression for matching file names
     */
    private function tryLoadTests(string $path, string $pattern = self::TEST_PATTERN)
    {
        if (\preg_match($pattern, $path)) {
            if ($this->excludingFiles) {
                $this->excludedFiles[$path] = $path;
            } elseif (!\array_key_exists($path, $this->excludedFiles)) {
                $this->files[] = $path;
            }
        }

        if (!\preg_match(self::$dotPattern, $path) && \is_dir($path)) {
            $this->loadDir($path, $pattern);
        }
    }
}
