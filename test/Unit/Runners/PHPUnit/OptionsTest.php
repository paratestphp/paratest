<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Tests\TestBase;

use function chdir;
use function file_put_contents;
use function getcwd;
use function glob;
use function intdiv;
use function is_dir;
use function mkdir;
use function unlink;

class OptionsTest extends TestBase
{
    protected $options;
    protected $unfiltered;
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
        $this->assertEquals('group1', $this->options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
    }

    public function testFilteredOptionsIsSet(): void
    {
        $this->assertEquals($this->unfiltered['processes'], $this->options->processes);
        $this->assertEquals($this->unfiltered['path'], $this->options->path);
        $this->assertEquals($this->unfiltered['phpunit'], $this->options->phpunit);
        $this->assertEquals($this->unfiltered['functional'], $this->options->functional);
        $this->assertEquals([$this->unfiltered['group']], $this->options->groups);
    }

    public function testAnnotationsReturnsAnnotations(): void
    {
        $this->assertCount(1, $this->options->annotations);
        $this->assertEquals('group1', $this->options->annotations['group']);
    }

    public function testAnnotationsDefaultsToEmptyArray(): void
    {
        $options = new Options([]);
        $this->assertEmpty($options->annotations);
    }

    public function testDefaults(): void
    {
        $options = new Options();
        $this->assertEquals(Options::getNumberOfCPUCores(), $options->processes);
        $this->assertEmpty($options->path);
        $this->assertEquals(PHPUNIT, $options->phpunit);
        $this->assertFalse($options->functional);
    }

    public function testHalfProcessesMode(): void
    {
        $options = new Options(['processes' => 'half']);
        $this->assertEquals(intdiv(Options::getNumberOfCPUCores(), 2), $options->processes);
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

    public function testConfigurationShouldBeSetEvenIfFileDoesNotExist(): void
    {
        $this->unfiltered['path']          = getcwd();
        $this->unfiltered['configuration'] = '/path/to/config';
        $options                           = new Options($this->unfiltered);
        $this->assertEquals('/path/to/config', $options->filtered['configuration']->getPath());
    }

    public function testConfigurationKeyIsNotPresentIfNoConfigGiven(): void
    {
        $this->unfiltered['path'] = getcwd();
        $options                  = new Options($this->unfiltered);
        $this->assertArrayNotHasKey('configuration', $options->filtered);
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
        $this->assertEquals(
            __DIR__ . DS . 'generated-configs' . DS . $configFileName,
            $options->filtered['configuration']->getPath()
        );
    }
}
