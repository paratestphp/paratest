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
    public function __construct(
        private string $path,
    ) {
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

    private function touchTempFile(?string &$tempName, string $prefix): string
    {
        if ($tempName === null) {
            $newFile = tempnam($this->tmpDir, $prefix);
            assert($newFile !== false);

            $tempName = $newFile;
        }

        return $tempName;
    }

    private function unlinkTempFile(?string &$tempName): void
    {
        if ($tempName === null) {
            return;
        }

        unlink($tempName);
        $tempName = null;
    }

  /**
     * Returns the path to this test's JUnit temp file.
     * If the temp file does not exist, it will be
     * created.
     */
    final public function getTempFile(): string
    {
        return $this->touchTempFile($this->tempJUnit, 'PT_');
    }

    /**
     * Removes the test file.
     */
    final public function deleteTempFiles(): void
    {
        $this->unlinkTempFile($this->tempJUnit);
        $this->unlinkTempFile($this->tempTeamcity);
        $this->unlinkTempFile($this->coverageFileName);
    }

    /**
     * Generate command line arguments with passed options suitable to handle through paratest.
     *
     * @param string                     $binary   executable binary name
     * @param array<string, string|null> $options  command line options
     * @param string[]|null              $passthru
     *
     * @return string[] command line arguments
     * @psalm-return array<string>
     */
    final public function commandArguments(string $binary, array $options, ?array $passthru): array
    {
        $options                        = $this->prepareOptions($options);
        $options['do-not-cache-result'] = null;
        $options['no-logging']          = null;
        $options['no-coverage']         = null;
        $options['no-output']           = null;
        $options['log-junit']           = $this->getTempFile();

        if ($this->needsTeamcity) {
            $options['log-teamcity'] = $this->getTeamcityTempFile();
        }

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
        return $this->touchTempFile($this->coverageFileName, 'CV_');
    }

    /**
     * Returns the path to this test's Teamcity format temp file.
     * If the temp file does not exist, it will be
     * created.
     */
    final public function getTeamcityTempFile(): string
    {
        return $this->touchTempFile($this->tempTeamcity, 'TF_');
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
