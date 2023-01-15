<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\DefaultResultPrinter;
use Symfony\Component\Console\Input\InputDefinition;

use function defined;
use function file_put_contents;
use function intdiv;
use function mt_rand;
use function sort;
use function str_replace;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\Options
 */
final class OptionsTest extends TestBase
{
    private Options $options;
    /** @var array<string, mixed>  */
    private array $unfiltered;

    public function setUpTest(): void
    {
        $this->unfiltered = [
            '--processes' => '5',
            '--path' => '/path/to/tests',
            '--functional' => true,
            '--group' => 'group1',
            '--exclude-group' => 'group2',
            '--bootstrap' => '/path/to/bootstrap',
        ];

        $this->options = $this->createOptionsFromArgv($this->unfiltered);
    }

    public function testOptionsAreOrdered(): void
    {
        $inputDefinition = new InputDefinition();
        Options::setInputDefinition($inputDefinition);

        $options = [];
        foreach ($inputDefinition->getOptions() as $inputOption) {
            $options[] = $inputOption->getName();
        }

        $expectedOrder = $options;
        sort($expectedOrder);

        static::assertSame($expectedOrder, $options);
    }

    public function testFilteredOptionsShouldContainExtraneousOptions(): void
    {
        static::assertEquals('group1', $this->options->filtered()['group']);
        static::assertEquals('/path/to/bootstrap', $this->options->filtered()['bootstrap']);
    }

    public function testFilteredOptionsIsSet(): void
    {
        static::assertEquals($this->unfiltered['--processes'], $this->options->processes());
        static::assertEquals($this->unfiltered['--path'], $this->options->path());
        static::assertEquals($this->unfiltered['--functional'], $this->options->functional());
        static::assertEquals([$this->unfiltered['--group']], $this->options->group());
    }

    public function testFilterOptionRequiresFunctionalMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOptionsFromArgv([
            '--functional' => false,
            '--filter' => 'testMe',
        ]);
    }

    public function testOrderByBadParam(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOptionsFromArgv([
            '--order-by' => uniqid('not_a_valid_order_'),
        ]);
    }

    public function testOrderWithoutOrderBy(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOptionsFromArgv(['--random-order-seed' => '123']);
    }

    public function testOrderBadOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOptionsFromArgv([
            '--order-by' => Options::ORDER_REVERSE,
            '--random-order-seed' => '123',
        ]);
    }

    public function testOrderBadRepeat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createOptionsFromArgv(['--repeat' => 'invalid']);
    }

    public function testSeedNotNumberic(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not_a_numeric_seed/');

        $this->createOptionsFromArgv([
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => 'not_a_numeric_seed',
        ]);
    }

    public function testAutoProcessesMode(): void
    {
        $options = $this->createOptionsFromArgv(['--processes' => 'auto']);

        static::assertEquals(Options::getNumberOfCPUCores(), $options->processes());
    }

    public function testHalfProcessesMode(): void
    {
        $options = $this->createOptionsFromArgv(['--processes' => 'half']);

        static::assertEquals(intdiv(Options::getNumberOfCPUCores(), 2), $options->processes());
    }

    public function testConfigurationShouldReturnXmlIfConfigNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', $this->tmpDir);
    }

    public function testConfigurationShouldReturnXmlDistIfConfigAndXmlNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', $this->tmpDir);
    }

    public function testConfigurationShouldReturnSpecifiedConfigurationIfFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit-myconfig.xml', $this->tmpDir, 'phpunit-myconfig.xml');
    }

    public function testConfigurationKeyIsNotPresentIfNoConfigGiven(): void
    {
        $options = $this->createOptionsFromArgv([], __DIR__);

        static::assertArrayNotHasKey('configuration', $options->filtered());
    }

    public function testRandomOrderSeedAutoset(): void
    {
        $options = $this->createOptionsFromArgv(['--order-by' => Options::ORDER_RANDOM]);

        static::assertGreaterThan(0, $options->randomOrderSeed());
    }

    public function testPassthru(): void
    {
        $argv = [
            '--passthru' => "'--prepend' 'xdebug-filter.php'",
            '--passthru-php' => "'-d' 'zend_extension=xdebug.so'",
        ];
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $argv['--passthru']     = str_replace('\'', '"', $argv['--passthru']);
            $argv['--passthru-php'] = str_replace('\'', '"', $argv['--passthru-php']);
        }

        $options = $this->createOptionsFromArgv($argv);

        $expectedPassthru    = ['--prepend', 'xdebug-filter.php'];
        $expectedPassthruPhp = ['-d', 'zend_extension=xdebug.so'];

        static::assertSame($expectedPassthru, $options->passthru());
        static::assertSame($expectedPassthruPhp, $options->passthruPhp());

        $emptyOptions = $this->createOptionsFromArgv(['--passthru' => '']);

        static::assertNull($emptyOptions->passthru());
    }

    public function testConfigurationShouldReturnXmlIfConfigSpecifiedAsDirectoryAndFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', $this->tmpDir, $this->tmpDir);
    }

    public function testConfigurationShouldReturnXmlDistIfConfigSpecifiedAsDirectoryAndFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', $this->tmpDir, $this->tmpDir);
    }

    private function assertConfigurationFileFiltered(
        string $configFileName,
        string $path,
        ?string $configurationParameter = null
    ): void {
        file_put_contents($this->tmpDir . DS . $configFileName, '<?xml version="1.0" encoding="UTF-8"?><phpunit />');
        $this->unfiltered['path'] = $path;
        if ($configurationParameter !== null) {
            $this->unfiltered['--configuration'] = $configurationParameter;
        }

        $options       = $this->createOptionsFromArgv($this->unfiltered, $this->tmpDir);
        $configuration = $options->configuration();
        static::assertNotNull($configuration);
        static::assertEquals(
            $this->tmpDir . DS . $configFileName,
            $configuration->filename(),
        );
    }

    public function testDefaultOptions(): void
    {
        $options = $this->createOptionsFromArgv([], __DIR__);

        static::assertNull($options->bootstrap());
        static::assertFalse($options->colors());
        static::assertNull($options->configuration());
        static::assertNull($options->coverageClover());
        static::assertNull($options->coverageCobertura());
        static::assertNull($options->coverageCrap4j());
        static::assertNull($options->coverageHtml());
        static::assertNull($options->coveragePhp());
        static::assertSame(0, $options->coverageTestLimit());
        static::assertNull($options->coverageText());
        static::assertNull($options->coverageXml());
        static::assertSame(__DIR__, $options->cwd());
        static::assertEmpty($options->excludeGroup());
        static::assertNull($options->filter());
        static::assertEmpty($options->filtered());
        static::assertFalse($options->functional());
        static::assertEmpty($options->group());
        static::assertNull($options->logJunit());
        static::assertNull($options->logTeamcity());
        static::assertSame(0, $options->maxBatchSize());
        static::assertFalse($options->noTestTokens());
        static::assertFalse($options->parallelSuite());
        static::assertEmpty($options->passthru());
        static::assertEmpty($options->passthruPhp());
        static::assertNull($options->path());
        static::assertStringContainsString('phpunit', $options->phpunit());
        static::assertSame(PROCESSES_FOR_TESTS, $options->processes());
        static::assertSame('Runner', $options->runner());
        static::assertFalse($options->stopOnFailure());
        static::assertEmpty($options->testsuite());
        static::assertSame($this->tmpDir, $options->tmpDir());
        static::assertFalse($options->verbose());
        static::assertFalse($options->debug());
        static::assertNull($options->coverageFilter());
        static::assertSame(Options::ORDER_DEFAULT, $options->orderBy());
        static::assertSame(0, $options->randomOrderSeed());
        static::assertFalse($options->teamcity());
        static::assertFalse($options->hasLogTeamcity());
        static::assertFalse($options->needsTeamcity());
        static::assertFalse($options->hasCoverage());
        static::assertSame(0, $options->repeat());
        static::assertFalse($options->testdox());
    }

    public function testProvidedOptions(): void
    {
        $expected_random_seed = mt_rand(100, 199);
        $argv                 = [
            '--bootstrap' => 'BOOTSTRAP',
            '--colors' => DefaultResultPrinter::COLOR_ALWAYS,
            '--configuration' => 'phpunit-ConfigurationTest.xml',
            '--coverage-clover' => 'COVERAGE-CLOVER',
            '--coverage-cobertura' => 'COVERAGE-COBERTURA',
            '--coverage-crap4j' => 'COVERAGE-CRAP4J',
            '--coverage-html' => 'COVERAGE-HTML',
            '--coverage-php' => 'COVERAGE-PHP',
            '--coverage-test-limit' => 3,
            '--coverage-text' => null,
            '--coverage-xml' => 'COVERAGE-XML',
            '--exclude-group' => 'EXCLUDE-GROUP',
            '--filter' => 'FILTER',
            '--functional' => true,
            '--group' => 'GROUP',
            '--log-junit' => 'LOG-JUNIT',
            '--teamcity' => true,
            '--log-teamcity' => 'LOG-TEAMCITY',
            '--max-batch-size' => 5,
            '--no-test-tokens' => true,
            '--parallel-suite' => true,
            '--passthru' => '-v',
            '--passthru-php' => '-d a=1',
            '--path' => 'PATH',
            '--processes' => '999',
            '--runner' => 'MYRUNNER',
            '--stop-on-failure' => true,
            '--testsuite' => 'TESTSUITE',
            '--tmp-dir' => ($tmpDir = uniqid($this->tmpDir . DS . 't')),
            '--verbose' => true,
            '--debug' => true,
            '--coverage-filter' => 'FILTER',
            '--order-by' => Options::ORDER_RANDOM,
            '--random-order-seed' => (string) $expected_random_seed,
            '--repeat' => '2',
            '--testdox' => true,
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        static::assertSame('BOOTSTRAP', $options->bootstrap());
        static::assertTrue($options->colors());
        static::assertNotNull($options->configuration());
        static::assertSame('COVERAGE-CLOVER', $options->coverageClover());
        static::assertSame('COVERAGE-COBERTURA', $options->coverageCobertura());
        static::assertSame('COVERAGE-CRAP4J', $options->coverageCrap4j());
        static::assertSame('COVERAGE-HTML', $options->coverageHtml());
        static::assertSame('COVERAGE-PHP', $options->coveragePhp());
        static::assertSame(3, $options->coverageTestLimit());
        static::assertSame('', $options->coverageText());
        static::assertSame('COVERAGE-XML', $options->coverageXml());
        static::assertSame(__DIR__, $options->cwd());
        static::assertSame(['EXCLUDE-GROUP'], $options->excludeGroup());
        static::assertSame('FILTER', $options->filter());
        static::assertTrue($options->functional());
        static::assertSame(['GROUP'], $options->group());
        static::assertSame('LOG-JUNIT', $options->logJunit());
        static::assertTrue($options->teamcity());
        static::assertSame('LOG-TEAMCITY', $options->logTeamcity());
        static::assertTrue($options->needsTeamcity());
        static::assertSame(5, $options->maxBatchSize());
        static::assertTrue($options->noTestTokens());
        static::assertTrue($options->parallelSuite());
        static::assertSame(['-v'], $options->passthru());
        static::assertSame(['-d', 'a=1'], $options->passthruPhp());
        static::assertSame('PATH', $options->path());
        static::assertSame(999, $options->processes());
        static::assertSame('MYRUNNER', $options->runner());
        static::assertTrue($options->stopOnFailure());
        static::assertSame(['TESTSUITE'], $options->testsuite());
        static::assertSame($tmpDir, $options->tmpDir());
        static::assertTrue($options->verbose());
        static::assertTrue($options->debug());
        static::assertSame('FILTER', $options->coverageFilter());
        static::assertSame(Options::ORDER_RANDOM, $options->orderBy());
        static::assertSame($expected_random_seed, $options->randomOrderSeed());
        static::assertSame(2, $options->repeat());
        static::assertTrue($options->testdox());

        static::assertSame([
            'bootstrap' => 'BOOTSTRAP',
            'configuration' => $options->configuration()->filename(),
            'coverage-filter' => 'FILTER',
            'exclude-group' => 'EXCLUDE-GROUP',
            'group' => 'GROUP',
            'order-by' => Options::ORDER_RANDOM,
            'random-order-seed' => (string) $expected_random_seed,
            'repeat' => '2',
            'stop-on-failure' => null,
        ], $options->filtered());

        static::assertTrue($options->hasLogTeamcity());
        static::assertTrue($options->hasCoverage());
    }

    public function testSingleVerboseFlag(): void
    {
        static::assertFalse($this->createOptionsFromArgv(['--verbose' => false], __DIR__)->verbose());
        static::assertTrue($this->createOptionsFromArgv(['--verbose' => true], __DIR__)->verbose());
    }

    public function testGatherOptionsFromConfiguration(): void
    {
        $argv = [
            '--configuration' => $this->fixture('phpunit-fully-configured.xml'),
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        static::assertTrue($options->colors());
        static::assertNotNull($options->configuration());
        static::assertNotNull($options->coverageClover());
        static::assertStringContainsString('clover.xml', $options->coverageClover());
        static::assertNotNull($options->coverageCobertura());
        static::assertStringContainsString('cobertura.xml', $options->coverageCobertura());
        static::assertNotNull($options->coverageCrap4j());
        static::assertStringContainsString('crap4j.xml', $options->coverageCrap4j());
        static::assertNotNull($options->coverageHtml());
        static::assertStringContainsString('html-coverage', $options->coverageHtml());
        static::assertNotNull($options->coveragePhp());
        static::assertStringContainsString('coverage.php', $options->coveragePhp());
        static::assertNotNull($options->coverageXml());
        static::assertStringContainsString('xml-coverage', $options->coverageXml());
        static::assertNotNull($options->coverageText());
        static::assertStringContainsString('coverage.txt', $options->coverageText());
        static::assertNotNull($options->logJunit());
        static::assertStringContainsString('junit.xml', $options->logJunit());

        static::assertTrue($options->hasCoverage());
    }

    public function testNoCoverageOptionDisablesCoverageOptions(): void
    {
        $argv = [
            '--coverage-clover' => 'COVERAGE-CLOVER',
            '--coverage-cobertura' => 'COVERAGE-COBERTURA',
            '--coverage-crap4j' => 'COVERAGE-CRAP4J',
            '--coverage-html' => 'COVERAGE-HTML',
            '--coverage-php' => 'COVERAGE-PHP',
            '--coverage-text' => false,
            '--coverage-xml' => 'COVERAGE-XML',
            '--no-coverage' => true,
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        static::assertFalse($options->hasCoverage());
    }

    public function testNoCoverageOptionDisablesCoverageConfiguration(): void
    {
        $argv = [
            '--configuration' => $this->fixture('phpunit-fully-configured.xml'),
            '--no-coverage' => true,
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        static::assertFalse($options->hasCoverage());
    }

    public function testFillEnvWithTokens(): void
    {
        $options = $this->createOptionsFromArgv(['--no-test-tokens' => false]);

        $inc = mt_rand(10, 99);
        $env = $options->fillEnvWithTokens($inc);

        static::assertSame(1, $env['PARATEST']);
        static::assertArrayHasKey(Options::ENV_KEY_TOKEN, $env);
        static::assertSame($inc, $env[Options::ENV_KEY_TOKEN]);
        static::assertArrayHasKey(Options::ENV_KEY_UNIQUE_TOKEN, $env);
        static::assertIsString($env[Options::ENV_KEY_UNIQUE_TOKEN]);
        static::assertStringContainsString($inc . '_', $env[Options::ENV_KEY_UNIQUE_TOKEN]);

        $options = $this->createOptionsFromArgv(['--no-test-tokens' => true]);

        $inc = mt_rand(10, 99);
        $env = $options->fillEnvWithTokens($inc);

        static::assertSame(1, $env['PARATEST']);
        static::assertArrayNotHasKey(Options::ENV_KEY_TOKEN, $env);
        static::assertArrayNotHasKey(Options::ENV_KEY_UNIQUE_TOKEN, $env);
    }

    public function testNeedsTeamcityGetsActivatedBothByLogTeamcityAndTeamcityFlags(): void
    {
        $options = $this->createOptionsFromArgv(['--teamcity' => true], __DIR__);

        self::assertTrue($options->needsTeamcity());

        $options = $this->createOptionsFromArgv(['--log-teamcity' => 'LOG-TEAMCITY'], __DIR__);

        self::assertTrue($options->needsTeamcity());
    }

    /**
     * @param array<string, (string|null)> $options
     *
     * @dataProvider provideColorsCases
     */
    public function testColors(bool $expected, ?string $phpunitConfig, array $options, bool $hasColorSupport): void
    {
        if ($phpunitConfig !== null) {
            $options['--configuration'] = $phpunitConfig;
        }

        self::assertSame($expected, $this->createOptionsFromArgv($options, __DIR__, $hasColorSupport)->colors());
    }

    /** @return array<int, array<int, (false|array<string, (null|string)>|null)>> */
    public function provideColorsCases(): array
    {
        return [
            [false, null, [], false],
            [false, null, [], true],
            [false, null, ['--colors' => null], false],
            [true,  null, ['--colors' => null], true],
            [false, null, ['--colors' => DefaultResultPrinter::COLOR_NEVER], false],
            [false, null, ['--colors' => DefaultResultPrinter::COLOR_NEVER], true],
            [true,  null, ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], false],
            [true,  null, ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], true],
            [false, null, ['--colors' => DefaultResultPrinter::COLOR_AUTO], false],
            [true,  null, ['--colors' => DefaultResultPrinter::COLOR_AUTO], true],

            [false, FIXTURES . DS . 'phpunit-colors-false.xml', [], false],
            [false, FIXTURES . DS . 'phpunit-colors-false.xml', [], true],
            [false, FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => null], false],
            [true,  FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => null], true],
            [false, FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_NEVER], false],
            [false, FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_NEVER], true],
            [true,  FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], false],
            [true,  FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], true],
            [false, FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_AUTO], false],
            [true,  FIXTURES . DS . 'phpunit-colors-false.xml', ['--colors' => DefaultResultPrinter::COLOR_AUTO], true],

            [false, FIXTURES . DS . 'phpunit-colors-true.xml', [], false],
            [true,  FIXTURES . DS . 'phpunit-colors-true.xml', [], true],
            [false, FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => null], false],
            [true,  FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => null], true],
            [false, FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_NEVER], false],
            [false, FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_NEVER], true],
            [true,  FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], false],
            [true,  FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_ALWAYS], true],
            [false, FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_AUTO], false],
            [true,  FIXTURES . DS . 'phpunit-colors-true.xml', ['--colors' => DefaultResultPrinter::COLOR_AUTO], true],
        ];
    }
}
