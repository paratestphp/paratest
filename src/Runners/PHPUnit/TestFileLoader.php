<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;

use function array_key_exists;
use function file_exists;
use function is_dir;
use function preg_match;
use function realpath;
use function scandir;

use const DIRECTORY_SEPARATOR;

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

    /** @var Options|null */
    protected $options;

    public function __construct(?Options $options = null)
    {
        $this->options = $options;
    }

    /**
     * Loads a SuitePath and makes sure to
     * take into account the excluded directory / files.
     *
     * @return string[]
     */
    public function loadSuitePath(SuitePath $path): array
    {
        // First initialize the list of files and excluded files
        $this->files          = [];
        $this->excludedFiles  = [];
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
     * @return string[]
     *
     * @throws InvalidArgumentException
     */
    public function loadPath(string $path, ?string $pattern = null): array
    {
        $this->files = [];

        $pattern = $pattern ?? self::TEST_PATTERN;

        $path = $path ?: $this->options->path;

        if (! file_exists($path)) {
            throw new InvalidArgumentException("$path is not a valid directory or file");
        }

        if (is_dir($path)) {
            $this->loadDir($path, $pattern);
        } elseif (file_exists($path)) {
            $this->loadFile($path);
        }

        return $this->files;
    }

    /**
     * Loads suites from a directory.
     */
    private function loadDir(string $path, string $pattern = self::TEST_PATTERN): void
    {
        $path  = realpath($path);
        $files = scandir($path);
        foreach ($files as $file) {
            $this->tryLoadTests($path . DIRECTORY_SEPARATOR . $file, $pattern);
        }
    }

    /**
     * Load a single suite file.
     */
    private function loadFile(string $path): void
    {
        $this->tryLoadTests($path, self::FILE_PATTERN);
    }

    /**
     * Attempts to load suites from a path.
     *
     * @param string $pattern regular expression for matching file names
     */
    private function tryLoadTests(string $path, string $pattern = self::TEST_PATTERN): void
    {
        if (preg_match($pattern, $path)) {
            if ($this->excludingFiles) {
                $this->excludedFiles[$path] = $path;
            } elseif (! array_key_exists($path, $this->excludedFiles)) {
                $this->files[] = $path;
            }
        }

        if (preg_match(self::$dotPattern, $path) || ! is_dir($path)) {
            return;
        }

        $this->loadDir($path, $pattern);
    }
}
