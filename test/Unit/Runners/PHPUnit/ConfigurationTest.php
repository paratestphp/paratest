<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use Exception;
use ParaTest\Runners\PHPUnit\Configuration;
use ParaTest\Runners\PHPUnit\SuitePath;
use ParaTest\Tests\TestBase;
use Throwable;

use function getcwd;
use function getenv;
use function libxml_disable_entity_loader;
use function putenv;
use function realpath;

class ConfigurationTest extends TestBase
{
    /** @var string */
    protected $path;
    /** @var Configuration */
    protected $config;

    public function setUp(): void
    {
        $this->path   = realpath(PARATEST_ROOT . '/phpunit.xml.dist');
        $this->config = new Configuration($this->path);
    }

    public function testToStringReturnsPath(): void
    {
        $this->assertEquals($this->path, (string) $this->config);
    }

    public function testGetSuitesShouldReturnCorrectNumberOfSuites()
    {
        $suites = $this->config->getSuites();
        $this->assertCount(2, $suites);

        return $suites;
    }

    public function testHasSuites(): void
    {
        $actual = $this->config->hasSuites();
        $this->assertTrue($actual);
    }

    public function testGlobbingSupport()
    {
        $basePath      = getcwd() . DS;
        $configuration = new Configuration($this->fixture('phpunit-globbing.xml'));
        /** @var SuitePath[][] $suites */
        $suites = $configuration->getSuites();
        $this->assertEquals(
            $basePath . 'test' . DS . 'fixtures' . DS . 'globbing-support-tests' . DS . 'some-dir',
            $suites['ParaTest Fixtures'][0]->getPath()
        );
        $this->assertEquals(
            $basePath . 'test' . DS . 'fixtures' . DS . 'globbing-support-tests' . DS . 'some-dir2',
            $suites['ParaTest Fixtures'][1]->getPath()
        );

        return $suites;
    }

    /**
     * @param mixed $suites
     *
     * @depends testGetSuitesShouldReturnCorrectNumberOfSuites
     */
    public function testSuitesContainSuiteNameAtKey($suites)
    {
        $this->assertArrayHasKey('ParaTest Unit Tests', $suites);
        $this->assertArrayHasKey('ParaTest Functional Tests', $suites);

        return $suites;
    }

    /**
     * @param mixed $suites
     *
     * @depends testSuitesContainSuiteNameAtKey
     */
    public function testSuitesContainPathAsValue($suites): void
    {
        $basePath  = getcwd() . DS;
        $unitSuite = $suites['ParaTest Unit Tests'];
        $this->assertIsArray($unitSuite);
        $this->assertCount(1, $unitSuite);
        $unitSuitePath = $unitSuite[0];
        $this->assertInstanceOf(SuitePath::class, $unitSuitePath);
        $this->assertEquals($basePath . 'test' . DS . 'Unit', $unitSuitePath->getPath());
        $functionalSuite = $suites['ParaTest Functional Tests'];
        $this->assertIsArray($functionalSuite);
        $this->assertCount(1, $functionalSuite);
        $functionalSuitePath = $functionalSuite[0];
        $this->assertInstanceOf(SuitePath::class, $functionalSuitePath);
        $this->assertEquals($basePath . 'test' . DS . 'Functional', $functionalSuitePath->getPath());
    }

    public function testGetEnvironmentVariables(): void
    {
        $this->assertCount(4, $this->config->getEnvironmentVariables());
        $this->assertArrayHasKey('APP_ENV', $this->config->getEnvironmentVariables());
        $this->assertArrayHasKey('CACHE_DRIVER', $this->config->getEnvironmentVariables());
        $this->assertArrayHasKey('DB_CONNECTION', $this->config->getEnvironmentVariables());
        $this->assertArrayHasKey('DB_DATABASE', $this->config->getEnvironmentVariables());

        $config = new Configuration(realpath(__DIR__ . '/phpunit-ConfigurationTest.xml'));
        $this->assertCount(0, $config->getEnvironmentVariables());
    }

    public function testLoadConfigEvenIfLibXmlEntityLoaderIsDisabled(): void
    {
        $before = libxml_disable_entity_loader();
        $e      = null;

        try {
            $this->config = new Configuration($this->path);
        } catch (Throwable $exc) {
            $e = $exc;
        }

        libxml_disable_entity_loader($before);

        $this->assertNull(
            $e,
            'Could not instantiate Configuration: ' . ($e instanceof Exception ? $e->getMessage() : 'no error given')
        );
    }

    public function testLoadedEnvironmentVariablesWillNotBeOverwritten(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=localhost');

        $config = new Configuration(realpath(__DIR__ . '/phpunit-ConfigurationTest.xml'));

        $this->assertSame('mysql', getenv('DB_CONNECTION'));
        $this->assertSame('localhost', getenv('DB_DATABASE'));
    }
}
