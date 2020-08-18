<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Tests\TestBase;
use Symfony\Component\Console\Input\InputDefinition;

use function defined;
use function file_put_contents;
use function glob;
use function intdiv;
use function is_dir;
use function mkdir;
use function sort;
use function sys_get_temp_dir;
use function unlink;

/**
 * @coversNothing
 */
final class OptionsTest extends TestBase
{
    /** @var Options */
    private $options;
    /** @var array<string, mixed>  */
    private $unfiltered;
    /** @var string */
    private $testCwd;

    public function setUpTest(): void
    {
        $this->unfiltered = [
            '--processes' => 5,
            '--path' => '/path/to/tests',
            '--phpunit' => 'phpunit',
            '--functional' => true,
            '--group' => 'group1',
            '--exclude-group' => 'group2',
            '--bootstrap' => '/path/to/bootstrap',
        ];
        $this->options    = $this->createOptionsFromArgv($this->unfiltered);
        $this->testCwd    = __DIR__ . DS . 'generated-configs';
        if (! is_dir($this->testCwd)) {
            mkdir($this->testCwd, 0777, true);
        }

        $this->cleanUpGeneratedFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanUpGeneratedFiles();
    }

    private function cleanUpGeneratedFiles(): void
    {
        $glob = glob($this->testCwd . DS . '*');
        self::assertNotFalse($glob);
        foreach ($glob as $file) {
            unlink($file);
        }
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
        static::assertEquals($this->unfiltered['--phpunit'], $this->options->phpunit());
        static::assertEquals($this->unfiltered['--functional'], $this->options->functional());
        static::assertEquals([$this->unfiltered['--group']], $this->options->group());
    }

    public function testHalfProcessesMode(): void
    {
        $options = $this->createOptionsFromArgv(['--processes' => 'half']);

        static::assertEquals(intdiv(Options::getNumberOfCPUCores(), 2), $options->processes());
    }

    public function testConfigurationShouldReturnXmlIfConfigNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', $this->testCwd);
    }

    public function testConfigurationShouldReturnXmlDistIfConfigAndXmlNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', $this->testCwd);
    }

    public function testConfigurationShouldReturnSpecifiedConfigurationIfFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit-myconfig.xml', $this->testCwd, 'phpunit-myconfig.xml');
    }

    public function testConfigurationKeyIsNotPresentIfNoConfigGiven(): void
    {
        $options = $this->createOptionsFromArgv([], __DIR__);

        static::assertArrayNotHasKey('configuration', $options->filtered());
    }

    public function testPassthru(): void
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $argv = [
                '--passthru' => '"--prepend" "xdebug-filter.php"',
                '--passthru-php' => '"-d" "zend_extension=xdebug.so"',
            ];
        } else {
            $argv = [
                '--passthru' => "'--prepend' 'xdebug-filter.php'",
                '--passthru-php' => "'-d' 'zend_extension=xdebug.so'",
            ];
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
        $this->assertConfigurationFileFiltered('phpunit.xml', $this->testCwd, $this->testCwd);
    }

    public function testConfigurationShouldReturnXmlDistIfConfigSpecifiedAsDirectoryAndFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', $this->testCwd, $this->testCwd);
    }

    private function assertConfigurationFileFiltered(
        string $configFileName,
        string $path,
        ?string $configurationParameter = null
    ): void {
        file_put_contents($this->testCwd . DS . $configFileName, '<?xml version="1.0" encoding="UTF-8"?><phpunit />');
        $this->unfiltered['path'] = $path;
        if ($configurationParameter !== null) {
            $this->unfiltered['--configuration'] = $configurationParameter;
        }

        $options       = $this->createOptionsFromArgv($this->unfiltered, $this->testCwd);
        $configuration = $options->configuration();
        static::assertNotNull($configuration);
        static::assertEquals(
            $this->testCwd . DS . $configFileName,
            $configuration->filename()
        );
    }

    public function testDefaultOptions(): void
    {
        $options = $this->createOptionsFromArgv([], __DIR__);

        static::assertNull($options->bootstrap());
        static::assertFalse($options->colors());
        static::assertNull($options->configuration());
        static::assertNull($options->coverageClover());
        static::assertNull($options->coverageCrap4j());
        static::assertNull($options->coverageHtml());
        static::assertNull($options->coveragePhp());
        static::assertSame(0, $options->coverageTestLimit());
        static::assertFalse($options->coverageText());
        static::assertNull($options->coverageXml());
        static::assertEmpty($options->excludeGroup());
        static::assertNull($options->filter());
        static::assertFalse($options->functional());
        static::assertEmpty($options->group());
        static::assertNull($options->logJunit());
        static::assertSame(0, $options->maxBatchSize());
        static::assertFalse($options->noTestTokens());
        static::assertFalse($options->parallelSuite());
        static::assertEmpty($options->passthru());
        static::assertEmpty($options->passthruPhp());
        static::assertNull($options->path());
        static::assertEquals(PHPUNIT, $options->phpunit());
        static::assertGreaterThan(0, $options->processes());
        static::assertStringContainsString('Runner', $options->runner());
        static::assertFalse($options->stopOnFailure());
        static::assertEmpty($options->testsuite());
        static::assertSame(sys_get_temp_dir(), $options->tmpDir());
        static::assertNull($options->whitelist());
    }

    public function testProvidedOptions(): void
    {
        $argv = [
            '--bootstrap' => 'BOOTSTRAP',
            '--colors' => true,
            '--configuration' => 'phpunit-ConfigurationTest.xml',
            '--coverage-clover' => 'COVERAGE-CLOVER',
            '--coverage-crap4j' => 'COVERAGE-CRAP4J',
            '--coverage-html' => 'COVERAGE-HTML',
            '--coverage-php' => 'COVERAGE-PHP',
            '--coverage-test-limit' => '3',
            '--coverage-text' => true,
            '--coverage-xml' => 'COVERAGE-XML',
            '--exclude-group' => 'EXCLUDE-GROUP',
            '--filter' => 'FILTER',
            '--functional' => true,
            '--group' => 'GROUP',
            '--log-junit' => 'LOG-JUNIT',
            '--max-batch-size' => '5',
            '--no-test-tokens' => true,
            '--parallel-suite' => true,
            '--passthru' => '-v',
            '--passthru-php' => '-d a=1',
            '--path' => 'PATH',
            '--phpunit' => 'PHPUNIT',
            '--processes' => '999',
            '--runner' => 'MYRUNNER',
            '--stop-on-failure' => true,
            '--testsuite' => 'TESTSUITE',
            '--tmp-dir' => TMP_DIR,
            '--whitelist' => 'WHITELIST',
        ];

        $options = $this->createOptionsFromArgv($argv, __DIR__);

        static::assertSame('BOOTSTRAP', $options->bootstrap());
        static::assertTrue($options->colors());
        static::assertNotNull($options->configuration());
        static::assertSame('COVERAGE-CLOVER', $options->coverageClover());
        static::assertSame('COVERAGE-CRAP4J', $options->coverageCrap4j());
        static::assertSame('COVERAGE-HTML', $options->coverageHtml());
        static::assertSame('COVERAGE-PHP', $options->coveragePhp());
        static::assertSame(3, $options->coverageTestLimit());
        static::assertTrue($options->coverageText());
        static::assertSame('COVERAGE-XML', $options->coverageXml());
        static::assertSame(['EXCLUDE-GROUP'], $options->excludeGroup());
        static::assertSame('FILTER', $options->filter());
        static::assertTrue($options->functional());
        static::assertSame(['GROUP'], $options->group());
        static::assertSame('LOG-JUNIT', $options->logJunit());
        static::assertSame(5, $options->maxBatchSize());
        static::assertTrue($options->noTestTokens());
        static::assertTrue($options->parallelSuite());
        static::assertSame(['-v'], $options->passthru());
        static::assertSame(['-d', 'a=1'], $options->passthruPhp());
        static::assertSame('PATH', $options->path());
        static::assertSame('PHPUNIT', $options->phpunit());
        static::assertSame(999, $options->processes());
        static::assertSame('MYRUNNER', $options->runner());
        static::assertTrue($options->stopOnFailure());
        static::assertSame(['TESTSUITE'], $options->testsuite());
        static::assertSame(TMP_DIR, $options->tmpDir());
        static::assertSame('WHITELIST', $options->whitelist());
    }
}
