<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use InvalidArgumentException;
use ParaTest\Options;
use ParaTest\RunnerInterface;
use ParaTest\WrapperRunner\WrapperRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;

use function file_exists;
use function getenv;
use function preg_replace;
use function putenv;

use const DIRECTORY_SEPARATOR;

abstract class TestBase extends TestCase
{
    /** @var class-string<RunnerInterface> */
    protected string $runnerClass = WrapperRunner::class;
    /** @var array<string, string|bool|int|null> */
    protected array $bareOptions = [];
    protected string $tmpDir;

    final protected function setUp(): void
    {
        $this->tmpDir = (new TmpDirCreator())->create();

        $this->setUpTest();
    }

    protected function setUpTest(): void
    {
    }

    /**
     * @param array<string, string|bool|int|null> $argv
     * @param non-empty-string|null               $cwd
     */
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

    public const SANITIZE = [
        '/^Time: .+/m',
        '/^Random Seed: .+/m',
        '/Warming cache for static analysis ... \[[^]]+\]/',
        "/flowId='\d+'/",
        "/duration='\d+'/",
    ];

    final protected function runRunner(): RunnerResult
    {
        $last = null;
        foreach (
            isset($this->bareOptions['--max-batch-size'])
            || isset($this->bareOptions['--verbose'])
            || isset($this->bareOptions['--log-teamcity'])
            || isset($this->bareOptions['--teamcity'])
            ? [false] : [true, false] as $functional
        ) {
            $output      = new BufferedOutput();
            $runnerClass = $this->runnerClass;

            $options                              = $this->createOptionsFromArgv($this->bareOptions + ['--functional' => $functional]);
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

            $exitCode = $runner->run();
            if ($shouldPutEnvForParatestTestingItSelf) {
                putenv(Options::ENV_KEY_TOKEN . '=' . $prevToken);
                putenv(Options::ENV_KEY_UNIQUE_TOKEN . '=' . $prevUniqueToken);
                $_SERVER[Options::ENV_KEY_TOKEN]        = $prevToken;
                $_SERVER[Options::ENV_KEY_UNIQUE_TOKEN] = $prevUniqueToken;
            }

            if ($last !== null) {
                self::assertEquals($last->exitCode, $exitCode);
                self::assertEquals(
                    preg_replace(self::SANITIZE, '', $last->output),
                    preg_replace(self::SANITIZE, '', $output->fetch()),
                );

                return $last;
            }

            $last = new RunnerResult($exitCode, $output->fetch());
        }

        return $last;
    }

    /**
     * @param non-empty-string $fixture
     *
     * @return non-empty-string
     */
    final protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DIRECTORY_SEPARATOR . $fixture;
        if (! file_exists($fixture)) {
            throw new InvalidArgumentException("Fixture {$fixture} not found");
        }

        return $fixture;
    }
}
