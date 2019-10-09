<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;

class OptionsTest extends \ParaTest\Tests\TestBase
{
    protected $options;
    protected $unfiltered;

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
        $this->options = new Options($this->unfiltered);
        $this->cleanUpConfigurations();
    }

    public function testFilteredOptionsShouldContainExtraneousOptions()
    {
        $this->assertEquals('group1', $this->options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
    }

    public function testFilteredOptionsIsSet()
    {
        $this->assertEquals($this->unfiltered['processes'], $this->options->processes);
        $this->assertEquals($this->unfiltered['path'], $this->options->path);
        $this->assertEquals($this->unfiltered['phpunit'], $this->options->phpunit);
        $this->assertEquals($this->unfiltered['functional'], $this->options->functional);
        $this->assertEquals([$this->unfiltered['group']], $this->options->groups);
    }

    public function testAnnotationsReturnsAnnotations()
    {
        $this->assertCount(1, $this->options->annotations);
        $this->assertEquals('group1', $this->options->annotations['group']);
    }

    public function testAnnotationsDefaultsToEmptyArray()
    {
        $options = new Options([]);
        $this->assertEmpty($options->annotations);
    }

    public function testDefaults()
    {
        $options = new Options();
        $this->assertEquals(Options::getNumberOfCPUCores(), $options->processes);
        $this->assertEmpty($options->path);
        $this->assertEquals(PHPUNIT, $options->phpunit);
        $this->assertFalse($options->functional);
    }

    public function testHalfProcessesMode()
    {
        $options = new Options(['processes' => 'half']);
        $this->assertEquals(intdiv(Options::getNumberOfCPUCores(), 2), $options->processes);
    }

    public function testConfigurationShouldReturnXmlIfConfigNotSpecifiedAndFileExistsInCwd()
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', getcwd());
    }

    public function testConfigurationShouldReturnXmlDistIfConfigAndXmlNotSpecifiedAndFileExistsInCwd()
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', getcwd());
    }

    public function testConfigurationShouldReturnSpecifiedConfigurationIfFileExists()
    {
        $this->assertConfigurationFileFiltered('myconfig.xml', getcwd(), 'myconfig.xml');
    }

    public function testConfigurationShouldBeSetEvenIfFileDoesNotExist()
    {
        $this->unfiltered['path'] = getcwd();
        $this->unfiltered['configuration'] = '/path/to/config';
        $options = new Options($this->unfiltered);
        $this->assertEquals('/path/to/config', $options->filtered['configuration']->getPath());
    }

    public function testConfigurationKeyIsNotPresentIfNoConfigGiven()
    {
        $this->unfiltered['path'] = getcwd();
        $options = new Options($this->unfiltered);
        $this->assertArrayNotHasKey('configuration', $options->filtered);
    }

    /**
     * Sets the current working directory to this source
     * directory so we can test configuration details without
     * using ParaTest's own configuration.
     *
     * Performs any cleanup to make sure no config files are
     * present when a test starts
     */
    protected function cleanUpConfigurations()
    {
        chdir(__DIR__);
        if (file_exists('phpunit.xml')) {
            unlink('phpunit.xml');
        }
        if (file_exists('phpunit.xml.dist')) {
            unlink('phpunit.xml.dist');
        }
        if (file_exists('myconfig.xml')) {
            unlink('myconfig.xml');
        }
    }

    public function testConfigurationShouldReturnXmlIfConfigSpecifiedAsDirectoryAndFileExists()
    {
        $this->assertConfigurationFileFiltered('phpunit.xml', getcwd(), getcwd());
    }

    public function testConfigurationShouldReturnXmlDistIfConfigSpecifiedAsDirectoryAndFileExists()
    {
        $this->assertConfigurationFileFiltered('phpunit.xml.dist', getcwd(), getcwd());
    }

    /**
     * @param $configFileName
     * @param $path
     * @param $configurationParameter
     */
    private function assertConfigurationFileFiltered($configFileName, $path, $configurationParameter = null)
    {
        file_put_contents($configFileName, '<root />');
        $this->unfiltered['path'] = $path;
        if ($configurationParameter !== null) {
            $this->unfiltered['configuration'] = $configurationParameter;
        }
        $options = new Options($this->unfiltered);
        $this->assertEquals(__DIR__ . DS . $configFileName, $options->filtered['configuration']->getPath());
    }
}
