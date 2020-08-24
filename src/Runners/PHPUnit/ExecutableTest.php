<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_map;
use function array_merge;
use function assert;
use function tempnam;
use function unlink;

abstract class ExecutableTest
{
    /**
     * The path to the test to run.
     *
     * @var string
     */
    private $path;

    /**
     * A path to the temp file created
     * for this test.
     *
     * @var string|null
     */
    private $temp;

    /**
     * Path where the coveragereport is stored.
     *
     * @var string|null
     */
    private $coverageFileName;

    /**
     * Last executed process command.
     *
     * @var string
     */
    private $lastCommand = '';

    /** @var bool */
    private $needsCoverage;
    /** @var string */
    private $tmpDir;

    public function __construct(string $path, bool $needsCoverage, string $tmpDir)
    {
        $this->path          = $path;
        $this->needsCoverage = $needsCoverage;
        $this->tmpDir        = $tmpDir;
    }

    /**
     * Get the expected count of tests to be executed.
     */
    abstract public function getTestCount(): int;

    /**
     * Get the path to the test being executed.
     */
    final public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the path to this test's temp file.
     * If the temp file does not exist, it will be
     * created.
     */
    final public function getTempFile(): string
    {
        if ($this->temp === null) {
            $temp = tempnam($this->tmpDir, 'PT_');
            assert($temp !== false);

            $this->temp = $temp;
        }

        return $this->temp;
    }

    /**
     * Removes the test file.
     */
    final public function deleteFile(): void
    {
        if ($this->temp !== null) {
            unlink($this->temp);
            $this->temp = null;
        }

        if ($this->coverageFileName === null) {
            return;
        }

        unlink($this->coverageFileName);
        $this->coverageFileName = null;
    }

    /**
     * Return the last process command.
     */
    final public function getLastCommand(): string
    {
        return $this->lastCommand;
    }

    /**
     * Set the last process command.
     */
    final public function setLastCommand(string $command): void
    {
        $this->lastCommand = $command;
    }

    /**
     * Generate command line arguments with passed options suitable to handle through paratest.
     *
     * @param string                     $binary   executable binary name
     * @param array<string, string|null> $options  command line options
     * @param string[]|null              $passthru
     *
     * @return string[] command line arguments
     */
    final public function commandArguments(string $binary, array $options, ?array $passthru): array
    {
        $options              = $this->prepareOptions($options);
        $options['log-junit'] = $this->getTempFile();
        if ($this->needsCoverage) {
            $options['coverage-php'] = $this->getCoverageFileName();
        }

        $arguments = [$binary];
        if ($passthru !== null) {
            $arguments = array_merge($arguments, $passthru);
        }

        foreach ($options as $key => $value) {
            $arguments[] = "--{$key}";
            if ($value === null) {
                continue;
            }

            $arguments[] = $value;
        }

        $arguments[] = $this->getPath();
        $arguments   = array_map('strval', $arguments);

        return $arguments;
    }

    /**
     * Get coverage filename.
     */
    final public function getCoverageFileName(): string
    {
        if ($this->coverageFileName === null) {
            $coverageFileName = tempnam($this->tmpDir, 'CV_');
            assert($coverageFileName !== false);

            $this->coverageFileName = $coverageFileName;
        }

        return $this->coverageFileName;
    }

    /**
     * A template method that can be overridden to add necessary options for a test.
     *
     * @param array<string, string|null> $options
     *
     * @return array<string, string|null>
     */
    abstract protected function prepareOptions(array $options): array;
}
