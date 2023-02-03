<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use InvalidArgumentException;
use ParaTest\Options;
use ParaTest\WrapperRunner\RunnerInterface;
use ParaTest\WrapperRunner\WrapperRunner;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;
use function file_exists;
use function getenv;
use function putenv;
use function sprintf;

abstract class TestBase extends TestCase
{
    /** @var class-string<RunnerInterface> */
    protected string $runnerClass = WrapperRunner::class;
    /** @var array<string, string|bool|int|null> */
    protected array $bareOptions = [];
    /** @var string */
    protected $tmpDir;

    final protected function setUp(): void
    {
        $this->tmpDir = (new TmpDirCreator())->create();

        $this->setUpTest();
    }

    protected function setUpTest(): void
    {
    }

    /** @param array<string, string|bool|int|null> $argv */
    final protected function createOptionsFromArgv(array $argv, ?string $cwd = null): Options
    {
        $inputDefinition = new InputDefinition();
        Options::setInputDefinition($inputDefinition);

        if (! isset($argv['--configuration'])) {
            $argv['--no-configuration'] = true;
        }

        if (! isset($argv['--processes'])) {
            $argv['--processes'] = (string) PROCESSES_FOR_TESTS;
        }

        if (! isset($argv['--tmp-dir'])) {
            $argv['--tmp-dir'] = $this->tmpDir;
        }

        $input = new ArrayInput($argv, $inputDefinition);

        return Options::fromConsoleInput($input, $cwd ?? __DIR__);
    }

    final protected function runRunner(?string $cwd = null): RunnerResult
    {
        $output      = new BufferedOutput();
        $runnerClass = $this->runnerClass;

        $options                              = $this->createOptionsFromArgv($this->bareOptions, $cwd);
        $shouldPutEnvForParatestTestingItSelf = $options->noTestTokens;
        $runner                               = new $runnerClass($options, $output);
        if ($shouldPutEnvForParatestTestingItSelf) {
            $prevToken       = getenv(Options::ENV_KEY_TOKEN);
            $prevUniqueToken = getenv(Options::ENV_KEY_UNIQUE_TOKEN);

            putenv(Options::ENV_KEY_TOKEN);
            putenv(Options::ENV_KEY_UNIQUE_TOKEN);
            unset($_SERVER[Options::ENV_KEY_TOKEN]);
            unset($_SERVER[Options::ENV_KEY_UNIQUE_TOKEN]);
        }

        $runner->run();
        if ($shouldPutEnvForParatestTestingItSelf) {
            putenv(Options::ENV_KEY_TOKEN . '=' . $prevToken);
            putenv(Options::ENV_KEY_UNIQUE_TOKEN . '=' . $prevUniqueToken);
            $_SERVER[Options::ENV_KEY_TOKEN]        = $prevToken;
            $_SERVER[Options::ENV_KEY_UNIQUE_TOKEN] = $prevUniqueToken;
        }

        return new RunnerResult($runner->getExitCode(), $output->fetch());
    }

    final protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new InvalidArgumentException("Fixture {$fixture} not found");
        }

        return $fixture;
    }
}
