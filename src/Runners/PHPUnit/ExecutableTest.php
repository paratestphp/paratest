<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function array_map;
use function array_merge;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const DIRECTORY_SEPARATOR;

abstract class ExecutableTest
{
    /**
     * The path to the test to run.
     *
     * @var string
     */
    protected $path;

    /**
     * A path to the temp file created
     * for this test.
     *
     * @var string
     */
    protected $temp;

    /** @var array */
    protected $pipes = [];

    /**
     * Path where the coveragereport is stored.
     *
     * @var string
     */
    protected $coverageFileName;

    /** @var Process */
    protected $process;

    /**
     * A unique token value for a given
     * process.
     *
     * @var int
     */
    protected $token;

    /**
     * Last executed process command.
     *
     * @var string
     */
    protected $lastCommand = '';

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /**
     * Get the expected count of tests to be executed.
     */
    abstract public function getTestCount(): int;

    /**
     * Get the path to the test being executed.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the path to this test's temp file.
     * If the temp file does not exist, it will be
     * created.
     */
    public function getTempFile(): string
    {
        if ($this->temp === null) {
            $this->temp = tempnam(sys_get_temp_dir(), 'PT_');
        }

        return $this->temp;
    }

    /**
     * Return the test process' stderr contents.
     */
    public function getStderr(): string
    {
        return $this->process->getErrorOutput();
    }

    /**
     * Stop the process and return it's
     * exit code.
     */
    public function stop(): int
    {
        return $this->process->stop();
    }

    /**
     * Removes the test file.
     */
    public function deleteFile(): void
    {
        $outputFile = $this->getTempFile();
        unlink($outputFile);
    }

    /**
     * Check if the process has terminated.
     */
    public function isDoneRunning(): bool
    {
        return $this->process->isTerminated();
    }

    /**
     * Return the exit code of the process.
     */
    public function getExitCode(): int
    {
        return $this->process->getExitCode();
    }

    /**
     * Return the last process command.
     */
    public function getLastCommand(): string
    {
        return $this->lastCommand;
    }

    /**
     * Set the last process command.
     */
    public function setLastCommand(string $command): void
    {
        $this->lastCommand = $command;
    }

    /**
     * Executes the test by creating a separate process.
     *
     * @param array         $options
     * @param array         $environmentVariables
     * @param string[]|null $passthru
     * @param string[]|null $passthruPhp
     *
     * @return $this
     */
    public function run(
        string $binary,
        array $options = [],
        array $environmentVariables = [],
        ?array $passthru = null,
        ?array $passthruPhp = null
    ) {
        $environmentVariables['PARATEST'] = 1;
        $this->handleEnvironmentVariables($environmentVariables);

        $command = $this->getFullCommandlineString($binary, $options, $passthru, $passthruPhp);

        $this->assertValidCommandLineLength($command);
        $this->setLastCommand($command);

        $this->process = Process::fromShellCommandline($command, null, $environmentVariables);
        $this->process->start();

        return $this;
    }

    /**
     * Build the full executable as we would do on the command line, e.g.
     * php -d zend_extension=xdebug.so vendor/bin/phpunit --teststuite suite1 --prepend xdebug-filter.php.
     *
     * @param array         $options
     * @param string[]|null $passthru
     * @param string[]|null $passthruPhp
     */
    protected function getFullCommandlineString(
        string $binary,
        array $options,
        ?array $passthru = null,
        ?array $passthruPhp = null
    ): string {
        $finder = new PhpExecutableFinder();

        $args = [$finder->find()];
        if ($passthruPhp !== null) {
            $args = array_merge($args, $passthruPhp);
        }

        $args = array_merge($args, $this->commandArguments($binary, $options, $passthru));

        return (new Process($args))->getCommandLine();
    }

    /**
     * Returns the unique token for this test process.
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * Generate command line arguments with passed options suitable to handle through paratest.
     *
     * @param string        $binary   executable binary name
     * @param array         $options  command line options
     * @param string[]|null $passthru
     *
     * @return string[] command line arguments
     */
    public function commandArguments(string $binary, array $options = [], ?array $passthru = null): array
    {
        $options = array_merge($this->prepareOptions($options), ['log-junit' => $this->getTempFile()]);
        $options = $this->redirectCoverageOption($options);

        $arguments = [$binary];
        if ($passthru !== null) {
            $arguments = array_merge($arguments, $passthru);
        }

        foreach ($options as $key => $value) {
            $arguments[] = "--$key";
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
     * Generate command line with passed options suitable to handle through paratest.
     *
     * @param string        $binary   executable binary name
     * @param array         $options  command line options
     * @param string[]|null $passthru
     *
     * @return string command line
     */
    public function command(string $binary, array $options = [], ?array $passthru = null): string
    {
        return (new Process($this->commandArguments($binary, $options, $passthru)))->getCommandLine();
    }

    /**
     * Get coverage filename.
     */
    public function getCoverageFileName(): string
    {
        if ($this->coverageFileName === null) {
            $this->coverageFileName = tempnam(sys_get_temp_dir(), 'CV_');
        }

        return $this->coverageFileName;
    }

    /**
     * Get process stdout content.
     */
    public function getStdout(): string
    {
        return $this->process->getOutput();
    }

    /**
     * Set process temporary filename.
     */
    public function setTempFile(string $temp): void
    {
        $this->temp = $temp;
    }

    /**
     * Assert that command line length is valid.
     *
     * In some situations process command line can became too long when combining different test
     * cases in single --filter arguments so it's better to show error regarding that to user
     * and propose him to decrease max batch size.
     *
     * @param string $cmd Command line
     *
     * @throws RuntimeException on too long command line.
     */
    protected function assertValidCommandLineLength(string $cmd): void
    {
        if (DIRECTORY_SEPARATOR === '\\') { // windows
            // symfony's process wrapper
            $cmd = 'cmd /V:ON /E:ON /C "(' . $cmd . ')';
            if (strlen($cmd) > 32767) {
                throw new RuntimeException('Command line is too long, try to decrease max batch size');
            }
        }

        /*
         * @todo Implement command line length validation for linux/osx/freebsd.
         *       Please note that on unix environment variables also became part of command line:
         *         - linux: echo | xargs --show-limits
         *         - osx/linux: getconf ARG_MAX
         */
    }

    /**
     * A template method that can be overridden to add necessary options for a test.
     *
     * @param array $options the options that are passed to the run method
     *
     * @return array $options the prepared options
     */
    protected function prepareOptions(array $options): array
    {
        return $options;
    }

    /**
     * Checks environment variables for the presence of a TEST_TOKEN
     * variable and sets $this->token based on its value.
     *
     * @param array $environmentVariables
     */
    protected function handleEnvironmentVariables(array $environmentVariables): void
    {
        if (! isset($environmentVariables['TEST_TOKEN'])) {
            return;
        }

        $this->token = $environmentVariables['TEST_TOKEN'];
    }

    /**
     * Checks if the coverage-php option is set and redirects it to a unique temp file.
     * This will ensure, that multiple tests write to separate coverage-files.
     *
     * @param array $options
     *
     * @return array $options
     */
    protected function redirectCoverageOption(array $options): array
    {
        if (isset($options['coverage-php'])) {
            $options['coverage-php'] = $this->getCoverageFileName();
        }

        unset(
            $options['coverage-html'],
            $options['coverage-clover'],
            $options['coverage-text'],
            $options['coverage-crap4j']
        );

        return $options;
    }
}
