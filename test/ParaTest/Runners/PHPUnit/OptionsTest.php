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
            'bootstrap' => '/path/to/bootstrap',
            'configuration' => '/path/to/configuration'
        ); 
        $this->options = new Options($this->unfiltered);
        $this->cleanUpConfigurations();
    }

    public function testFilteredOptionsShouldContainExtraneousOptions()
    {
        $this->assertEquals('group1', $this->options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
        $this->assertEquals('/path/to/configuration', $this->options->filtered['configuration']);
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
        $this->assertEquals(getcwd(), $options->path);
        $this->assertEquals(PHPUNIT, $options->phpunit);
        $this->assertFalse($options->functional);
    }

    public function testGetConfigurationShouldReturnXmlIfConfigNotSpecifiedAndFileExistsInCwd()
    {
        chdir(__DIR__);
        file_put_contents('phpunit.xml', 'XML!!!');
        unset($this->unfiltered['configuration']);
        $options = new Options($this->unfiltered);
        $this->assertEquals(__DIR__ . DS . 'phpunit.xml', $options->getConfiguration());
    }

    public function testGetConfigurationShouldReturnXmlDistIfConfigAndXmlNotSpecifiedAndFileExistsInCwd()
    {
        chdir(__DIR__);
        file_put_contents('phpunit.xml.dist', 'XML!!!');
        unset($this->unfiltered['configuration']);
        $options = new Options($this->unfiltered);
        $this->assertEquals(__DIR__ . DS . 'phpunit.xml.dist', $options->getConfiguration());
    }

    public function testGetConfigurationShouldReturnSpecifiedConfigurationIfFileExists()
    {
        chdir(__DIR__);
        file_put_contents('myconfig.xml', 'XML!!!');
        $this->unfiltered['configuration'] = 'myconfig.xml';
        $options = new Options($this->unfiltered);
        $expected = $options->filtered['configuration'];

        $this->assertEquals($expected, $options->getConfiguration());
    }

    public function testGetConfigurationShouldReturnNullIfSpecifiedFileDoesNotExist()
    {
        $this->assertNull($this->options->getConfiguration());
    }

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
}