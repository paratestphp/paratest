<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use function array_merge;
use function assert;
use function basename;
use function dirname;
use function file_exists;
use function glob;
use function is_dir;
use function sprintf;
use function unlink;

/**
 * @coversNothing
 */
final class PHPUnitTest extends FunctionalTestBase
{
    public function testWithJustBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests'));
    }

    public function testWithJustConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', ['--configuration' => PHPUNIT_CONFIGURATION]));
    }

    /**
     * @param array<string, string|bool> $options
     *
     * @dataProvider provideGithubIssues
     */
    public function testGithubIssues(
        string $directory,
        array $options,
        ?string $testPattern = null,
        ?string $assertionPattern = null
    ): void {
        $this->assertTestsPassed($this->invokeParatest(
            null,
            array_merge([
                '--configuration' => sprintf('%s%sphpunit%s.xml', $directory, DS, basename($directory)),
            ], $options),
        ), $testPattern, $assertionPattern);
    }

    /**
     * @return array<string, array<string, (string|array<string, (string|bool)>|null)>>
     */
    public function provideGithubIssues(): array
    {
        $directory = $this->fixture('github');
        $cases     = [];
        $glob      = glob($directory . DS . '*');
        assert($glob !== false);
        foreach ($glob as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $cases['issue-' . basename($path)] = [
                'directory' => $path,
                'options' => [],
                'testPattern' => null,
                'assertionPattern' => null,
            ];
        }

        $cases['issue-420bis']['options']['--bootstrap'] = sprintf(
            '%s%sbootstrap420bis.php',
            $cases['issue-420bis']['directory'],
            DS
        );

        $cases['issue-432']['options']          = ['--group' => 'group1'];
        $cases['issue-432']['testPattern']      = '1';
        $cases['issue-432']['assertionPattern'] = '1';

        $cases['issue-505']['options']       = ['--no-test-tokens' => true];
        $cases['issue-505tokens']['options'] = [];

        return $cases;
    }

    public function testWithColorsGreenBar(): void
    {
        $proc = $this->invokeParatest(
            'paratest-only-tests/EnvironmentTest.php',
            ['--colors' => true]
        );
        static::assertStringContainsString(
            '[30;42m[2KOK',
            $proc->getOutput()
        );
    }

    public function testWithColorsRedBar(): void
    {
        $proc = $this->invokeParatest(
            'failing-tests/UnitTestWithErrorTest.php',
            ['--colors' => true]
        );
        static::assertStringContainsString(
            '[37;41m[2KFAILURES',
            $proc->getOutput()
        );
    }

    public function testWithConfigurationInDirWithoutConfigFile(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [], dirname(FIXTURES)));
    }

    public function testFunctionalWithBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['--functional' => true]
        ));
    }

    public function testFunctionalWithConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['--configuration' => PHPUNIT_CONFIGURATION, '--functional' => true]
        ));
    }

    public function testWithBootstrapAndProcessesSwitch(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['--processes' => 6]
        );
        static::assertMatchesRegularExpression('/Running phpunit in 6 processes/', $proc->getOutput());
        $this->assertTestsPassed($proc);
    }

    public function testDefaultSettingsWithoutBootstrap(): void
    {
        $this->assertTestsPassed($this->invokeParatest('passing-tests', [], PARATEST_ROOT));
    }

    public function testDefaultSettingsWithSpecifiedPath(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['--path' => 'test/fixtures/passing-tests'],
            PARATEST_ROOT
        ));
    }

    public function testLoggingXmlOfDirectory(): void
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional-directory.xml';
        $proc   = $this->invokeParatest('passing-tests', ['--log-junit' => $output], PARATEST_ROOT);
        $this->assertTestsPassed($proc);
        static::assertFileExists($output);
        unlink($output);
    }

    public function testTestTokenEnvVarIsPassed(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests',
            ['--path' => 'test/fixtures/paratest-only-tests/TestTokenTest.php'],
            PARATEST_ROOT
        );
        $this->assertTestsPassed($proc, '1', '1');
    }

    public function testLoggingXmlOfSingleFile(): void
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional-file.xml';
        $proc   = $this->invokeParatest('passing-tests/GroupsTest.php', ['--log-junit' => $output], PARATEST_ROOT);
        $this->assertTestsPassed($proc, '5', '5');
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testFullyConfiguredRun(): void
    {
        $output = FIXTURES . DS . 'logs' . DS . 'functional.xml';
        $proc   = $this->invokeParatest('passing-tests', [
            '--functional' => true,
            '--processes' => 6,
            '--log-junit' => $output,
        ]);
        $this->assertTestsPassed($proc);
        $results = $proc->getOutput();
        static::assertMatchesRegularExpression('/Running phpunit in 6 processes/', $results);
        static::assertMatchesRegularExpression('/Functional mode is on/i', $results);
        static::assertFileExists($output);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testUsingDefaultLoadedConfiguration(): void
    {
        $this->assertTestsPassed($this->invokeParatest(
            'passing-tests',
            ['--functional' => true]
        ));
    }

    public function testEachTestRunsExactlyOnceOnChainDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnChain.php',
            ['--functional' => true]
        );
        $this->assertTestsPassed($proc, '5', '5');
    }

    public function testEachTestRunsExactlyOnceOnSameDependencyOnFunctionalMode(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/DependsOnSame.php',
            ['--functional' => true]
        );
        $this->assertTestsPassed($proc, '3', '3');
    }

    public function testFunctionalModeEachTestCalledOnce(): void
    {
        $proc = $this->invokeParatest(
            'passing-tests/FunctionalModeEachTestCalledOnce.php',
            ['--functional' => true]
        );
        $this->assertTestsPassed($proc, '2', '2');
    }
}
