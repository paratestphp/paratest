<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\Options;
use ParaTest\Tests\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;

use function mt_rand;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @internal */
#[CoversClass(Options::class)]
final class OptionsTest extends TestBase
{
    private Options $options;
    /** @var array<non-empty-string, non-empty-string|list<non-empty-string>>  */
    private array $unfiltered;

    public function setUpTest(): void
    {
        $this->unfiltered = [
            '--processes' => '5',
            '--group' => ['group1'],
            '--exclude-group' => ['group2'],
            '--bootstrap' => '/path/to/bootstrap',
            'path' => '/path/to/tests',
        ];

        $this->options = $this->createOptionsFromArgv($this->unfiltered);
    }

    public function testFilteredOptionsShouldContainExtraneousOptions(): void
    {
        self::assertEquals(['group1'], $this->options->phpunitOptions['group']);
        self::assertEquals('/path/to/bootstrap', $this->options->phpunitOptions['bootstrap']);
    }

    public function testAutoProcessesMode(): void
    {
        $options = $this->createOptionsFromArgv(['--processes' => 'auto']);

        self::assertEquals(Options::getNumberOfCPUCores(), $options->processes);
    }

    public function testPassthru(): void
    {
        $argv = ['--passthru-php' => "'-d' 'zend_extension=xdebug.so'"];

        $options = $this->createOptionsFromArgv($argv);

        $expectedPassthruPhp = ['-d', 'zend_extension=xdebug.so'];

        self::assertSame($expectedPassthruPhp, $options->passthruPhp);
    }

    public function testDefaultOptions(): void
    {
        $options = $this->createOptionsFromArgv([], __DIR__);

        self::assertSame(__DIR__, $options->cwd);
        self::assertNotEmpty($options->phpunitOptions);
        self::assertSame(0, $options->maxBatchSize);
        self::assertFalse($options->noTestTokens);
        self::assertSame(['-d', 'zend.assertions=1'], $options->passthruPhp);
        self::assertStringContainsString('phpunit', $options->phpunit);
        self::assertSame(PROCESSES_FOR_TESTS, $options->processes);
        self::assertSame('WrapperRunner', $options->runner);
        self::assertSame($this->tmpDir, $options->tmpDir);
        self::assertFalse($options->verbose);
    }

    public function testProvidedOptions(): void
    {
        $argv = [
            '--max-batch-size' => 5,
            '--no-test-tokens' => true,
            '--passthru-php' => '-d a=1',
            '--processes' => '999',
            '--runner' => 'MYRUNNER',
            '--tmp-dir' => ($tmpDir = uniqid($this->tmpDir . DIRECTORY_SEPARATOR . 't')),
            '--verbose' => true,
            'path' => 'PATH',
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        self::assertSame(5, $options->maxBatchSize);
        self::assertTrue($options->noTestTokens);
        self::assertSame(['-d', 'a=1'], $options->passthruPhp);
        self::assertSame('PATH', $options->configuration->cliArguments()[0]);
        self::assertSame(999, $options->processes);
        self::assertSame('MYRUNNER', $options->runner);
        self::assertSame($tmpDir, $options->tmpDir);
        self::assertTrue($options->verbose);
    }

    public function testFillEnvWithTokens(): void
    {
        $options = $this->createOptionsFromArgv(['--no-test-tokens' => false]);

        $inc = mt_rand(10, 99);
        $env = $options->fillEnvWithTokens($inc);

        self::assertSame(1, $env['PARATEST']);
        self::assertArrayHasKey(Options::ENV_KEY_TOKEN, $env);
        self::assertSame($inc, $env[Options::ENV_KEY_TOKEN]);
        self::assertArrayHasKey(Options::ENV_KEY_UNIQUE_TOKEN, $env);
        self::assertStringContainsString($inc . '_', $env[Options::ENV_KEY_UNIQUE_TOKEN]);

        $options = $this->createOptionsFromArgv(['--no-test-tokens' => true]);

        $inc = mt_rand(10, 99);
        $env = $options->fillEnvWithTokens($inc);

        self::assertSame(1, $env['PARATEST']);
        self::assertArrayNotHasKey(Options::ENV_KEY_TOKEN, $env);
        self::assertArrayNotHasKey(Options::ENV_KEY_UNIQUE_TOKEN, $env);
    }

    public function testNeedsTeamcityGetsActivatedBothByLogTeamcityAndTeamcityFlags(): void
    {
        $options = $this->createOptionsFromArgv(['--teamcity' => true], __DIR__);

        self::assertTrue($options->needsTeamcity);

        $options = $this->createOptionsFromArgv(['--log-teamcity' => 'LOG-TEAMCITY'], __DIR__);

        self::assertTrue($options->needsTeamcity);
    }
}
