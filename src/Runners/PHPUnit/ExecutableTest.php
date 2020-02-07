<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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

    protected $pipes = [];

    /**
     * Path where the coveragereport is stored.
     *
     * @var string
     */
    protected $coverageFileName;

    /**
     * @var Process
     */
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
     *
     * @return int
     */
    abstract public function getTestCount(): int;

    /**
     * Get the path to the test being executed.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the path to this test's temp file.
     * If the temp file does not exist, it will be
     * created.
     *
     * @return string
     */
    public function getTempFile(): string
    {
        if (null === $this->temp) {
            $this->temp = \tempnam(\sys_get_temp_dir(), 'PT_');
        }

        return $this->temp;
    }

    /**
     * Return the test process' stderr contents.
     *
     * @return string
     */
    public function getStderr(): string
    {
        return $this->process->getErrorOutput();
    }

    /**
     * Stop the process and return it's
     * exit code.
     *
     * @return int
     */
    public function stop(): int
    {
        return $this->process->stop();
    }

    /**
     * Removes the test file.
     */
    public function deleteFile()
    {
        $outputFile = $this->getTempFile();
        \unlink($outputFile);
    }

    /**
     * Check if the process has terminated.
     *
     * @return bool
     */
    public function isDoneRunning(): bool
    {
        return $this->process->isTerminated();
    }

    /**
     * Return the exit code of the process.
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->process->getExitCode();
    }

    /**
     * Return the last process command.
     *
     * @return string
     */
    public function getLastCommand(): string
    {
        return $this->lastCommand;
    }

    /**
     * Set the last process command.
     *
     * @param string $command
     */
    public function setLastCommand(string $command)
    {
        $this->lastCommand = $command;
    }

    /**
     * Executes the test by creating a separate process.
     *
     * @param string      $binary
     * @param array       $options
     * @param array       $environmentVariables
     * @param string|null $passthru
     * @param string|null $passthruPhp
     *
     * @return $this
     */
    public function run(
        string $binary,
        array $options = [],
        array $environmentVariables = [],
        ?string $passthru = null,
        ?string $passthruPhp = null
    ) {
        $environmentVariables['PARATEST'] = 1;
        $this->handleEnvironmentVariables($environmentVariables);

        $command = $this->getFullCommandlineString($binary, $options, $passthru, $passthruPhp);

        $this->assertValidCommandLineLength($command);
        $this->setLastCommand($command);

        $this->process = \method_exists(Process::class, 'fromShellCommandline') ?
            Process::fromShellCommandline($command, null, $environmentVariables) :
            new Process($command, null, $environmentVariables);

        if (\method_exists($this->process, 'inheritEnvironmentVariables')) {
            // no such method in 3.0, but emits warning if this isn't done in 3.3
            $this->process->inheritEnvironmentVariables();
        }
        $this->process->start();

        return $this;
    }

    /**
     * Build the full executable as we would do on the command line, e.g.
     * php -d zend_extension=xdebug.so vendor/bin/phpunit --teststuite suite1 --prepend xdebug-filter.php.
     *
     * @param $binary
     * @param $options
     * @param string|null $passthru
     * @param string|null $passthruPhp
     *
     * @return string
     */
    protected function getFullCommandlineString(
        $binary,
        $options,
        ?string $passthru = null,
        ?string $passthruPhp = null
    ) {
        $finder = new PhpExecutableFinder();
        $args = [];

        $args['php'] = $finder->find();
        if (!empty($passthruPhp)) {
            $args['phpOptions'] = $passthruPhp;
        }
        $args['phpunit'] = $this->command($binary, $options, $passthru);

        $command = \implode(' ', $args);

        return $command;
    }

    /**
     * Returns the unique token for this test process.
     *
     * @return int
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * Generate command line with passed options suitable to handle through paratest.
     *
     * @param string      $binary   executable binary name
     * @param array       $options  command line options
     * @param string|null $passthru
     *
     * @return string command line
     */
    public function command(string $binary, array $options = [], ?string $passthru = null): string
    {
        $options = \array_merge($this->prepareOptions($options), ['log-junit' => $this->getTempFile()]);
        $options = $this->redirectCoverageOption($options);

        $cmd = $this->getCommandString($binary, $options, $passthru);

        return $cmd;
    }

    /**
     * Get coverage filename.
     *
     * @return string
     */
    public function getCoverageFileName(): string
    {
        if ($this->coverageFileName === null) {
            $this->coverageFileName = \tempnam(\sys_get_temp_dir(), 'CV_');
        }

        return $this->coverageFileName;
    }

    /**
     * Get process stdout content.
     *
     * @return string
     */
    public function getStdout(): string
    {
        return $this->process->getOutput();
    }

    /**
     * Set process temporary filename.
     *
     * @param string $temp
     */
    public function setTempFile(string $temp)
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
     * @throws \RuntimeException on too long command line
     */
    protected function assertValidCommandLineLength(string $cmd)
    {
        if (\DIRECTORY_SEPARATOR === '\\') { // windows
            // symfony's process wrapper
            $cmd = 'cmd /V:ON /E:ON /C "(' . $cmd . ')';
            if (\strlen($cmd) > 32767) {
                throw new \RuntimeException('Command line is too long, try to decrease max batch size');
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
     * Returns the command string that will be executed
     * by proc_open.
     *
     * @param string      $binary
     * @param array       $options
     * @param string|null $passthru
     *
     * @return mixed
     */
    protected function getCommandString(string $binary, array $options = [], ?string $passthru = null)
    {
        // The order we add stuff into $arguments is important
        $arguments = [$binary];
        // Note:
        // the arguments MUST come last and we need to "somehow"
        // merge the passthru string in there.
        // Thus, we "split" the command creation here.
        // For a clean solution, we would need to manually parse and verify
        // the passthru. I'll leave that as a
        // TODO
        // @see https://stackoverflow.com/a/34871367/413531
        // @see https://github.com/symfony/console/blob/68001d4b65139ef4f22da581a8da7be714218aec/Input/StringInput.php
        $cmd = (new Process($arguments))->getCommandLine();
        if (!empty($passthru)) {
            $cmd .= ' ' . $passthru;
        }

        $arguments = [];
        foreach ($options as $key => $value) {
            $arguments[] = "--$key";
            if ($value !== null) {
                $arguments[] = $value;
            }
        }

        $arguments[] = $this->getPath();

        $args = (new Process($arguments))->getCommandLine();

        return $cmd . ' ' . $args;
    }

    /**
     * Checks environment variables for the presence of a TEST_TOKEN
     * variable and sets $this->token based on its value.
     *
     * @param $environmentVariables
     */
    protected function handleEnvironmentVariables(array $environmentVariables)
    {
        if (isset($environmentVariables['TEST_TOKEN'])) {
            $this->token = $environmentVariables['TEST_TOKEN'];
        }
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

        unset($options['coverage-html'], $options['coverage-clover'], $options['coverage-text']);

        return $options;
    }
}
