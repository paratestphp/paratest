<?php namespace ParaTest\Runners\PHPUnit;

class ConfigurationTest extends \TestBase
{
    protected $path;
    protected $config;

    public function setUp()
    {
        $this->path = realpath('phpunit.xml.dist');
        $this->config = new Configuration($this->path);
    }

    public function testToStringReturnsPath()
    {
        $this->assertEquals($this->path, (string)$this->config);
    }

    public function test_getSuitesShouldReturnCorrectNumberOfSuites()
    {
        $suites = $this->config->getSuites();
        $this->assertEquals(3, sizeof($suites));
        return $suites;
    }

    /**
     * @depends test_getSuitesShouldReturnCorrectNumberOfSuites
     */
    public function testSuitesContainSuiteNameAtKey($suites)
    {
        $this->assertTrue(array_key_exists("ParaTest Unit Tests", $suites));
        $this->assertTrue(array_key_exists("ParaTest Integration Tests", $suites));
        $this->assertTrue(array_key_exists("ParaTest Functional Tests", $suites));
        return $suites;
    }

    /**
     * @depends testSuitesContainSuiteNameAtKey
     */
    public function testSuitesContainPathAsValue($suites)
    {
        $basePath = getcwd() . DS;
        $this->assertEquals(array($basePath . 'test' . DS . 'ParaTest'), $suites["ParaTest Unit Tests"]);
        $this->assertEquals(array($basePath . 'it' . DS . 'ParaTest'), $suites["ParaTest Integration Tests"]);
        $this->assertEquals(array($basePath . 'functional'), $suites["ParaTest Functional Tests"]);
    }

    public function testLoadConfigEvenIfLibXmlEntityLoaderIsDisabled()
    {
        $before = libxml_disable_entity_loader();
        $e = null;

        try {
            $this->config = new Configuration($this->path);
        } catch (\Exception $exc) {
            $e = $exc;
        }

        libxml_disable_entity_loader($before);

        $this->assertNull($e, 'Could not instantiate Configuration: ' . ($e instanceof \Exception ? $e->getMessage() : 'no error given'));
    }
}
