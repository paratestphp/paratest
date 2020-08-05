<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use PHPUnit\TextUI\XmlConfiguration\Configuration;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function array_merge;
use function strlen;

use const DIRECTORY_SEPARATOR;

final class RunnerWorker
{
    /** @var ExecutableTest */
    private $executableTest;
    /** @var Process */
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
    public function stop(): ?int
    {
        return $this->process->stop();
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
    public function getExitCode(): ?int
    {
        return $this->process->getExitCode();
    }

    /**
     * Executes the test by creating a separate process.
     *
     * @param array<string, (string|bool|int|Configuration|string[]|null)> $options
     * @param array<string, string|int>                                    $environmentVariables
     * @param string[]|null                                                $passthru
     * @param string[]|null                                                $passthruPhp
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
        $process = $this->getProcess($binary, $options, $environmentVariables, $passthru, $passthruPhp);
        $cmd     = $process->getCommandLine();

        $this->assertValidCommandLineLength($cmd);
        $this->executableTest->setLastCommand($cmd);

        $this->process = $process;
        $this->process->start();

        return $this;
    }

    /**
     * Build the full executable as we would do on the command line, e.g.
     * php -d zend_extension=xdebug.so vendor/bin/phpunit --teststuite suite1 --prepend xdebug-filter.php.
     *
     * @param array<string, (string|bool|int|Configuration|string[]|null)> $options
     * @param array<string, string|int>                                    $environmentVariables
     * @param string[]|null                                                $passthru
     * @param string[]|null                                                $passthruPhp
     */
    private function getProcess(
        string $binary,
        array $options,
        array $environmentVariables = [],
        ?array $passthru = null,
        ?array $passthruPhp = null
    ): Process {
        $finder = new PhpExecutableFinder();

        $args = [$finder->find()];
        if ($passthruPhp !== null) {
            $args = array_merge($args, $passthruPhp);
        }

        $args = array_merge($args, $this->executableTest->commandArguments($binary, $options, $passthru));

        $environmentVariables['PARATEST'] = 1;

        return new Process($args, null, $environmentVariables);
    }

    /**
     * Get process stdout content.
     */
    public function getStdout(): string
    {
        return $this->process->getOutput();
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
}
