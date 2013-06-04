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
    public function testSuitesContainFinderAsValue($suites)
    {
        $this->assertInstanceOf('Symfony\\Component\\Finder\\Finder', $suites["ParaTest Unit Tests"]);
        $this->assertInstanceOf('Symfony\\Component\\Finder\\Finder', $suites["ParaTest Integration Tests"]);
        $this->assertInstanceOf('Symfony\\Component\\Finder\\Finder', $suites["ParaTest Functional Tests"]);
    }
}
