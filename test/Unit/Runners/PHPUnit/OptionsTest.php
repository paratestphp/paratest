<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Tests\TestBase;
use Symfony\Component\Console\Input\ArrayInput;

use function chdir;
use function defined;
use function file_put_contents;
use function getcwd;
use function glob;
use function intdiv;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

final class OptionsTest extends TestBase
{
    /** @var Options */
    private $options;
    /** @var array<string, mixed>  */
    private $unfiltered;
    /** @var string */
    private $currentCwd;

    public function setUp(): void
    {
        $this->unfiltered = [
            'processes' => 5,
            'path' => '/path/to/tests',
            'phpunit' => 'phpunit',
            'functional' => true,
            'group' => 'group1',
            'exclude-group' => 'group2',
            'bootstrap' => '/path/to/bootstrap',
        ];
        $this->options    = new Options($this->unfiltered);
        $this->currentCwd = getcwd();
        $testCwd          = __DIR__ . DS . 'generated-configs';
        if (! is_dir($testCwd)) {
            mkdir($testCwd, 0777, true);
        }

        chdir($testCwd);
        $this->cleanUpGeneratedFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanUpGeneratedFiles();
        chdir($this->currentCwd);
    }

    private function cleanUpGeneratedFiles(): void
    {
        foreach (glob(getcwd() . DS . '*') as $file) {
            unlink($file);
        }
    }

    public function testFilteredOptionsShouldContainExtraneousOptions(): void
    {
        static::assertEquals('group1', $this->options->filtered['group']);
        static::assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
    }

    public function testFilteredOptionsIsSet(): void
    {
        static::assertEquals($this->unfiltered['processes'], $this->options->processes);
        static::assertEquals($this->unfiltered['path'], $this->options->path);
        static::assertEquals($this->unfiltered['phpunit'], $this->options->phpunit);
        static::assertEquals($this->unfiltered['functional'], $this->options->functional);
        static::assertEquals([$this->unfiltered['group']], $this->options->groups);
    }

    public function testAnnotationsReturnsAnnotations(): void
    {
        static::assertCount(1, $this->options->annotations);
        static::assertEquals('group1', $this->options->annotations['group']);
    }

    public function testAnnotationsDefaultsToEmptyArray(): void
    {
        $options = new Options([]);
        static::assertEmpty($options->annotations);
    }

    public function testDefaults(): void
    {
        $options = new Options();
        static::assertEquals(Options::getNumberOfCPUCores(), $options->processes);
        static::assertEmpty($options->path);
        static::assertEquals(PHPUNIT, $options->phpunit);
        static::assertFalse($options->functional);
    }

    public function testHalfProcessesMode(): void
    {
        $options = new Options(['processes' => 'half']);
        static::assertEquals(intdiv(Options::getNumberOfCPUCores(), 2), $options->processes);
    }

    public function testConfigurationShouldReturnXmlIfConfigNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', getcwd());
    }

    public function testConfigurationShouldReturnXmlDistIfConfigAndXmlNotSpecifiedAndFileExistsInCwd(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', getcwd());
    }

    public function testConfigurationShouldReturnSpecifiedConfigurationIfFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit-myconfig.xml', getcwd(), 'phpunit-myconfig.xml');
    }

    public function testConfigurationKeyIsNotPresentIfNoConfigGiven(): void
    {
        $this->unfiltered['path'] = getcwd();
        $options                  = new Options($this->unfiltered);
        static::assertArrayNotHasKey('configuration', $options->filtered);
    }

    public function testPassthru(): void
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $options = new Options([
                'passthru' => '"--prepend" "xdebug-filter.php"',
                'passthru-php' => '"-d" "zend_extension=xdebug.so"',
            ]);
        } else {
            $options = new Options([
                'passthru' => "'--prepend' 'xdebug-filter.php'",
                'passthru-php' => "'-d' 'zend_extension=xdebug.so'",
            ]);
        }

        $expectedPassthru    = ['--prepend', 'xdebug-filter.php'];
        $expectedPassthruPhp = ['-d', 'zend_extension=xdebug.so'];

        static::assertSame($expectedPassthru, $options->passthru);
        static::assertSame($expectedPassthruPhp, $options->passthruPhp);

        $emptyOptions = new Options(['passthru' => '']);
        static::assertNull($emptyOptions->passthru);
    }

    public function testConfigurationShouldReturnXmlIfConfigSpecifiedAsDirectoryAndFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', getcwd(), getcwd());
    }

    public function testConfigurationShouldReturnXmlDistIfConfigSpecifiedAsDirectoryAndFileExists(): void
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', getcwd(), getcwd());
    }

    private function assertConfigurationFileFiltered(
        string $configFileName,
        string $path,
        ?string $configurationParameter = null
    ): void {
        file_put_contents($configFileName, '<?xml version="1.0" encoding="UTF-8"?><phpunit />');
        $this->unfiltered['path'] = $path;
        if ($configurationParameter !== null) {
            $this->unfiltered['configuration'] = $configurationParameter;
        }

        $options = new Options($this->unfiltered);
        static::assertEquals(
            __DIR__ . DS . 'generated-configs' . DS . $configFileName,
            $options->filtered['configuration']->filename()
        );
    }

    /**
     * @param array<string, string> $options
     *
     * @dataProvider setsCoveragePhpDataProvider
     */
    public function testSetsCoveragePhp(array $options, string $coveragePhp): void
    {
        $c = new ParaTestCommand();

        $input = new ArrayInput([], $c->getDefinition());
        foreach ($options as $key => $value) {
            $input->setOption($key, $value);
        }

        $input->setArgument('path', '.');
        $options = Options::fromConsoleInput($input);

        if ($coveragePhp !== '') {
            static::assertEquals($coveragePhp, $options->filtered['coverage-php']);
        } else {
            static::assertStringStartsWith(sys_get_temp_dir(), $options->filtered['coverage-php']);
        }
    }

    /**
     * @return array<int, array<int, string|array<string, string>>>
     */
    public function setsCoveragePhpDataProvider(): array
    {
        return [
            [
                ['coverage-html' => 'wayne'],
                '',
            ],
            [
                ['coverage-clover' => 'wayne'],
                '',
            ],
            [
                ['coverage-php' => 'notWayne'],
                'notWayne',
            ],
            [
                ['coverage-clover' => 'wayne', 'coverage-php' => 'notWayne'],
                'notWayne',
            ],
        ];
    }

    /**
     * @param array<string, array<string, int|string>> $options
     * @param array<string, array<string, int|string>> $expected
     *
     * @dataProvider getRunnerOptionsDataProvider
     */
    /*
    public function testGetRunnerOptions(array $options, array $expected): void
    {
        $c       = new ParaTestCommand();
        $input   = new ArrayInput($options, $c->getDefinition());

        $options = Options::fromConsoleInput($input);

        // Note:
        // 'coverage-php' contains a random, temporary string.
        // has to be refactored to be testable but I'll leave that as a
        // TODO
        if (array_key_exists('coverage-php', $options)) {
            unset($options['coverage-php']);
        }

        static::assertEquals($expected, $options);
    }
    */

    /**
     * @return array<string, array<string, array<string, array<int, string>|int|string>>>
     */
    /*
    public function getRunnerOptionsDataProvider(): array
    {
        return [
            'default' => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                ],
            ],
            'accepts all defined options' => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                    '--functional' => 1,
                    '--no-test-tokens' => 1,
//                    '--help' => "",
                    '--coverage-clover' => 'clover',
                    '--coverage-crap4j' => 'xml',
                    '--coverage-html' => 'html',
                    '--coverage-text' => 'text',
                    '--coverage-xml' => 'xml',
                    '--max-batch-size' => '5',
                    '--filter' => 'filter',
                    '--parallel-suite' => 'parallel-suite',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                    'functional' => 1,
                    'no-test-tokens' => 1,
                    'coverage-clover' => 'clover',
                    'coverage-crap4j' => 'xml',
                    'coverage-html' => 'html',
                    'coverage-text' => 'text',
                    'coverage-xml' => 'xml',
                    'max-batch-size' => '5',
                    'filter' => 'filter',
                    'parallel-suite' => 'parallel-suite',
                ],
            ],
            "splits testsuite on ','" => [
                'input' => [
                    'path' => 'bar',
                    '--processes' => '10',
                    '--testsuite' => 't1,t2',
                ],
                'expected' => [
                    'path' => 'bar',
                    'processes' => '10',
                    'testsuite' => [
                        't1',
                        't2',
                    ],
                ],
            ],
        ];
    }
    */
}
