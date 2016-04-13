<?php namespace ParaTest\Runners\PHPUnit;

class OptionsTest extends \TestBase
{
    protected $options;
    protected $unfiltered;

    public function setUp()
    {
        $this->unfiltered = array(
            'processes' => 5,
            'path' => '/path/to/tests',
            'phpunit' => 'phpunit',
            'functional' => true,
            'group' => 'group1',
            'bootstrap' => '/path/to/bootstrap'
        ); 
        $this->options = new Options($this->unfiltered);
        $this->cleanUpConfigurations();
    }

    public function testFilteredOptionsShouldContainExtraneousOptions()
    {
        $this->assertEquals('group1', $this->options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
    }

    public function testAnnotationsReturnsAnnotations()
    {
        $this->assertEquals(1, sizeof($this->options->annotations));
        $this->assertEquals('group1', $this->options->annotations['group']);
    }

    public function testAnnotationsDefaultsToEmptyArray()
    {
        $options = new Options(array());
        $this->assertEmpty($options->annotations);
    }

    public function testDefaults()
    {
        $options = new Options();
        $this->assertEquals(5, $options->processes);
        $this->assertEmpty($options->path);
        $this->assertEquals(PHPUNIT, $options->phpunit);
        $this->assertFalse($options->functional);
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
        $this->assertFalse(array_key_exists('configuration', $options->filtered));
    }

    /**
     * Sets the current working directory to this source
     * directory so we can test configuration details without
     * using ParaTest's own configuration
     *
     * Performs any cleanup to make sure no config files are
     * present when a test starts
     */
    protected function cleanUpConfigurations()
    {
        chdir(__DIR__);
        if(file_exists('phpunit.xml'))
            unlink('phpunit.xml');
        if(file_exists('phpunit.xml.dist'))
            unlink('phpunit.xml.dist');
        if(file_exists('myconfig.xml'))
            unlink('myconfig.xml');
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
