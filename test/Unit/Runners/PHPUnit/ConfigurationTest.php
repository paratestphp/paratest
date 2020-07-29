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
        static::assertEquals($this->path, (string) $this->config);
    }

    /**
     * @return SuitePath[][]
     */
    public function testGetSuitesShouldReturnCorrectNumberOfSuites(): array
    {
        $suites = $this->config->getSuites();
        static::assertCount(2, $suites);

        return $suites;
    }

    public function testHasSuites(): void
    {
        $actual = $this->config->hasSuites();
        static::assertTrue($actual);
    }

    /**
     * @param SuitePath[][] $suites
     *
     * @return  SuitePath[][]
     *
     * @depends testGetSuitesShouldReturnCorrectNumberOfSuites
     */
    public function testSuitesContainSuiteNameAtKey(array $suites): array
    {
        static::assertArrayHasKey('ParaTest Unit Tests', $suites);
        static::assertArrayHasKey('ParaTest Functional Tests', $suites);

        return $suites;
    }

    /**
     * @param SuitePath[][] $suites
     *
     * @depends testSuitesContainSuiteNameAtKey
     */
    public function testSuitesContainPathAsValue(array $suites): void
    {
        $basePath  = getcwd() . DS;
        $unitSuite = $suites['ParaTest Unit Tests'];
        static::assertCount(1, $unitSuite);
        $unitSuitePath = $unitSuite[0];
        static::assertEquals($basePath . 'test' . DS . 'Unit', $unitSuitePath->getPath());
        $functionalSuite = $suites['ParaTest Functional Tests'];
        static::assertCount(1, $functionalSuite);
        $functionalSuitePath = $functionalSuite[0];
        static::assertEquals($basePath . 'test' . DS . 'Functional', $functionalSuitePath->getPath());
    }

    public function testGetEnvironmentVariables(): void
    {
        static::assertCount(4, $this->config->getEnvironmentVariables());
        static::assertArrayHasKey('APP_ENV', $this->config->getEnvironmentVariables());
        static::assertArrayHasKey('CACHE_DRIVER', $this->config->getEnvironmentVariables());
        static::assertArrayHasKey('DB_CONNECTION', $this->config->getEnvironmentVariables());
        static::assertArrayHasKey('DB_DATABASE', $this->config->getEnvironmentVariables());

        $config = new Configuration(realpath(__DIR__ . '/phpunit-ConfigurationTest.xml'));
        static::assertCount(0, $config->getEnvironmentVariables());
    }

    public function testGlobbingSupport(): void
    {
        $basePath      = getcwd() . DS;
        $configuration = new Configuration($this->fixture('phpunit-globbing.xml'));
        /** @var SuitePath[][] $suites */
        $suites = $configuration->getSuites();
        static::assertEquals(
            $basePath . 'test' . DS . 'fixtures' . DS . 'globbing-support-tests' . DS . 'some-dir',
            $suites['ParaTest Fixtures'][0]->getPath()
        );
        static::assertEquals(
            $basePath . 'test' . DS . 'fixtures' . DS . 'globbing-support-tests' . DS . 'some-dir2',
            $suites['ParaTest Fixtures'][1]->getPath()
        );
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

        static::assertNull(
            $e,
            'Could not instantiate Configuration: ' . ($e instanceof Exception ? $e->getMessage() : 'no error given')
        );
    }

    public function testLoadedEnvironmentVariablesWillNotBeOverwritten(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=localhost');

        $config = new Configuration(realpath(__DIR__ . '/phpunit-ConfigurationTest.xml'));

        static::assertSame('mysql', getenv('DB_CONNECTION'));
        static::assertSame('localhost', getenv('DB_DATABASE'));
    }
}
