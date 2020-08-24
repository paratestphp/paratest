<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function array_merge;
use function assert;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class RunnerWorker
{
    /** @var ExecutableTest */
    private $executableTest;
    /** @var Process|null */
    private $process;

    public function __construct(ExecutableTest $executableTest)
    {
        $this->executableTest = $executableTest;
    }

    public function getExecutableTest(): ExecutableTest
    {
        return $this->executableTest;
    }

    /**
     * Stop the process and return it's
     * exit code.
     */
    public function stop(): ?int
    {
        assert($this->process !== null);

        return $this->process->stop();
    }

    /**
     * Check if the process has terminated.
     */
    public function isDoneRunning(): bool
    {
        assert($this->process !== null);

        return $this->process->isTerminated();
    }

    /**
     * Return the exit code of the process.
     */
    public function getExitCode(): ?int
    {
        assert($this->process !== null);

        return $this->process->getExitCode();
    }

    /**
     * Executes the test by creating a separate process.
     *
     * @param array<string, string|null>    $options
     * @param array<string|int, string|int> $environmentVariables
     * @param string[]|null                 $passthru
     * @param string[]|null                 $passthruPhp
     */
    public function run(
        string $binary,
        array $options,
        array $environmentVariables,
        ?array $passthru,
        ?array $passthruPhp,
        string $cwd
    ): void {
        $process = $this->getProcess($binary, $options, $environmentVariables, $passthru, $passthruPhp, $cwd);
        $cmd     = $process->getCommandLine();

        $this->assertValidCommandLineLength($cmd);
        $this->executableTest->setLastCommand($cmd);

        $this->process = $process;
        $this->process->start();
    }

    /**
     * Build the full executable as we would do on the command line, e.g.
     * php -d zend_extension=xdebug.so vendor/bin/phpunit -_teststuite suite1 --prepend xdebug-filter.php.
     *
     * @param array<string, string|null>    $options
     * @param array<string|int, string|int> $environmentVariables
     * @param string[]|null                 $passthru
     * @param string[]|null                 $passthruPhp
     */
    private function getProcess(
        string $binary,
        array $options,
        array $environmentVariables,
        ?array $passthru,
        ?array $passthruPhp,
        string $cwd
    ): Process {
        $finder = new PhpExecutableFinder();

        $args = [$finder->find()];
        if ($passthruPhp !== null) {
            $args = array_merge($args, $passthruPhp);
        }

        $args = array_merge($args, $this->executableTest->commandArguments($binary, $options, $passthru));

        return new Process($args, $cwd, $environmentVariables);
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
    private function assertValidCommandLineLength(string $cmd): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // @codeCoverageIgnoreStart
            // symfony's process wrapper
            $cmd = 'cmd /V:ON /E:ON /C "(' . $cmd . ')';
            if (strlen($cmd) > 32767) {
                throw new RuntimeException('Command line is too long, try to decrease max batch size');
            }

            // @codeCoverageIgnoreEnd
        }

        /*
         * @todo Implement command line length validation for linux/osx/freebsd.
         *       Please note that on unix environment variables also became part of command line:
         *         - linux: echo | xargs --show-limits
         *         - osx/linux: getconf ARG_MAX
         */
    }

    public function getCrashReport(): string
    {
        assert($this->process !== null);

        $error = sprintf(
            'The command "%s" failed.' . "\n\nExit Code: %s(%s)\n\nWorking directory: %s",
            $this->process->getCommandLine(),
            (string) $this->process->getExitCode(),
            (string) $this->process->getExitCodeText(),
            (string) $this->process->getWorkingDirectory()
        );

        if (! $this->process->isOutputDisabled()) {
            $error .= sprintf(
                "\n\nOutput:\n================\n%s\n\nError Output:\n================\n%s",
                $this->process->getOutput(),
                $this->process->getErrorOutput()
            );
        }

        return $error;
    }
}
