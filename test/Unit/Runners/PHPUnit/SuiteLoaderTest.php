<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\ExecutableTest;
use ParaTest\Runners\PHPUnit\FullSuite;
use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\SuiteLoader;
use ParaTest\Tests\TestBase;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_keys;
use function array_shift;
use function count;
use function preg_match;
use function strstr;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\SuiteLoader
 */
final class SuiteLoaderTest extends TestBase
{
    /** @var BufferedOutput */
    private $output;

    protected function setUpTest(): void
    {
        $this->output = new BufferedOutput();
    }

    public function testLoadThrowsExceptionWithInvalidPath(): void
    {
        $loader = new SuiteLoader($this->createOptionsFromArgv(['--path' => '/path/to/nowhere']), $this->output);

        $this->expectException(RuntimeException::class);

        $loader->load();
    }

    public function testLoadBarePathWithNoPathAndNoConfiguration(): void
    {
        $loader = new SuiteLoader($this->createOptionsFromArgv([], __DIR__), $this->output);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No path or configuration provided (tests must end with Test.php)');

        $loader->load();
    }

    public function testLoadTestsuiteFileFromConfig(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-file.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = 1;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromConfigWhileIgnoringExcludeTag(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-excluded-including-file.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = 1;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromDirFromConfigWhileRespectingExcludeTag(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-excluded-including-dir.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = 2;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteFilesFromConfig(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-multifile.xml'),
            '--group' => 'fixtures,group4',
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        static::assertCount(3, $files);
    }

    public function testLoadTestsuiteWithDirectory(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithDirectories(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-multidir.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests')) +
            count($this->findTests(FIXTURES . DS . 'failing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithFilesDirsMixed(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-files-dirs-mix.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'failing_tests')) + 2;
        static::assertCount($expected, $files);
    }

    public function testLoadTestsuiteWithDuplicateFilesDirMixed(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-files-dirs-mix-duplicates.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests')) + 2;
        static::assertCount($expected, $files);
    }

    public function testLoadSomeTestsuite(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-parallel-suite.xml'),
            '--testsuite' => 'Suite 1',
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'parallel_suite' . DS . 'One'));
        static::assertCount($expected, $files);
    }

    public function testLoadSuiteFromConfig(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-passing.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadSuiteFromConfigWithMultipleDirs(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-multidir.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $files = $this->getObjectValue($loader, 'files');

        $expected = count($this->findTests(FIXTURES . DS . 'passing_tests')) +
            count($this->findTests(FIXTURES . DS . 'failing_tests'));
        static::assertCount($expected, $files);
    }

    public function testLoadSuiteFromConfigWithBadSuitePath(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-non-existent-testsuite-dir.xml'),
            '--testsuite' => uniqid(),
        ]);
        $loader  = new SuiteLoader($options, $this->output);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Suite path \w+ could not be found/');

        $loader->load();
    }

    public function testLoadFileGetsPathOfFile(): void
    {
        $path  = $this->fixture('failing_tests' . DS . 'UnitTestWithClassAnnotationTest.php');
        $paths = $this->getLoadedPaths($path);
        static::assertEquals($path, array_shift($paths));
    }

    /**
     * @return string[]
     */
    private function getLoadedPaths(string $path, ?SuiteLoader $loader = null): array
    {
        $loader = $loader ?? new SuiteLoader($this->createOptionsFromArgv(['--path' => $path]), $this->output);
        $loader->load();
        $loaded = $this->getObjectValue($loader, 'loadedSuites');

        return array_keys($loaded);
    }

    public function testLoadFileShouldLoadFileWhereNameDoesNotEndInTest(): void
    {
        $path  = $this->fixture('passing_tests' . DS . 'TestOfUnits.php');
        $paths = $this->getLoadedPaths($path);
        static::assertEquals($path, array_shift($paths));
    }

    /**
     * @return ExecutableTest[]
     */
    public function testLoadDirGetsPathOfAllTestsWithKeys(): array
    {
        $fixturePath = $this->fixture('passing_tests');
        $files       = $this->findTests($fixturePath);

        $loader = new SuiteLoader($this->createOptionsFromArgv(['--path' => $fixturePath]), $this->output);
        $loader->load();
        $loaded = $this->getObjectValue($loader, 'loadedSuites');
        foreach ($loaded as $path => $test) {
            static::assertContains($path, $files);
        }

        return $loaded;
    }

    /**
     * @param ExecutableTest[] $paraSuites
     *
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testFirstParallelSuiteHasCorrectFunctions(array $paraSuites): void
    {
        $first     = $this->suiteByPath('GroupsTest.php', $paraSuites);
        $functions = $first->getFunctions();
        static::assertCount(5, $functions);
        static::assertEquals('testTruth', $functions[0]->getName());
        static::assertEquals('testFalsehood', $functions[1]->getName());
        static::assertEquals('testArrayLength', $functions[2]->getName());
        static::assertEquals('testStringLength', $functions[3]->getName());
        static::assertEquals('testAddition', $functions[4]->getName());
    }

    /**
     * @param ExecutableTest[] $paraSuites
     */
    private function suiteByPath(string $path, array $paraSuites): Suite
    {
        foreach ($paraSuites as $completePath => $suite) {
            if (strstr($completePath, $path) !== false) {
                static::assertInstanceOf(Suite::class, $suite);

                return $suite;
            }
        }

        throw new RuntimeException("Suite {$path} not found.");
    }

    /**
     * @param ExecutableTest[] $paraSuites
     *
     * @depends testLoadDirGetsPathOfAllTestsWithKeys
     */
    public function testSecondParallelSuiteHasCorrectFunctions(array $paraSuites): void
    {
        $second    = $this->suiteByPath('LegacyNamespaceTest.php', $paraSuites);
        $functions = $second->getFunctions();
        static::assertCount(1, $functions);
    }

    public function testGetTestMethodsOnlyReturnsMethodsOfGroupIfOptionIsSpecified(): void
    {
        $options = $this->createOptionsFromArgv([
            '--group' => 'group1',
            '--path' => $this->fixture('passing_tests/GroupsTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $methods = $loader->getTestMethods();
        static::assertCount(2, $methods);
        static::assertEquals('testTruth', $methods[0]->getName());
        static::assertEquals('testFalsehood', $methods[1]->getName());
    }

    public function testGetTestMethodsOnlyReturnsMethodsOfClassGroup(): void
    {
        $options = $this->createOptionsFromArgv([
            '--group' => 'group4',
            '--path' => $this->fixture('passing_tests/GroupsTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        $methods = $loader->getTestMethods();
        static::assertCount(1, $loader->getSuites());
        static::assertCount(5, $methods);
    }

    public function testGetSuitesForNonMatchingGroups(): void
    {
        $options = $this->createOptionsFromArgv([
            '--group' => 'non-existent',
            '--path' => $this->fixture('passing_tests/GroupsTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        static::assertCount(0, $loader->getSuites());
        static::assertCount(0, $loader->getTestMethods());
    }

    public function testLoadIgnoresFilesWithoutClasses(): void
    {
        $options = $this->createOptionsFromArgv([
            '--group' => 'non-existent',
            '--path' => $this->fixture('special_classes/FileWithoutClass.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        static::assertCount(0, $loader->getTestMethods());
    }

    public function testExcludeGroupSwitchDontExecuteThatGroup(): void
    {
        $options = $this->createOptionsFromArgv([
            '--exclude-group' => 'group1',
            '--path' => $this->fixture('passing_tests/GroupsTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        static::assertCount(3, $loader->getTestMethods());
    }

    public function testGroupsSwitchExecutesMultipleGroups(): void
    {
        $options = $this->createOptionsFromArgv([
            '--group' => 'group1,group3',
            '--path' => $this->fixture('passing_tests/GroupsTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();
        static::assertCount(3, $loader->getTestMethods());
    }

    public function testExecutableTestsForFunctionalModeUse(): void
    {
        $loader = new SuiteLoader($this->createOptionsFromArgv([
            '--path' => $this->fixture('passing_tests/DependsOnChain.php'),
        ]), $this->output);
        $loader->load();
        $tests = $loader->getTestMethods();
        static::assertCount(2, $tests);
        $testMethod = $tests[0];
        static::assertEquals('testOneA|testOneBDependsOnA|testOneCDependsOnB', $testMethod->getName());
        $testMethod = $tests[1];
        static::assertEquals('testTwoA|testTwoBDependsOnA', $testMethod->getName());
    }

    public function testParallelSuite(): void
    {
        $loader = new SuiteLoader($this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-parallel-suite.xml'),
            '--parallel-suite' => true,
            '--processes' => 2,
        ]), $this->output);
        $loader->load();

        $suites = $loader->getSuites();

        static::assertCount(2, $suites);
        foreach ($suites as $suite) {
            static::assertInstanceOf(FullSuite::class, $suite);
        }
    }

    public function testBatches(): void
    {
        $options = $this->createOptionsFromArgv([
            '--bootstrap' => BOOTSTRAP,
            '--path' => $this->fixture('dataprovider_tests/DataProviderTest.php'),
            '--filter' => 'testNumericDataProvider1000',
            '--functional' => true,
            '--max-batch-size' => 50,
        ], __DIR__);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();

        $suites = $loader->getSuites();

        static::assertCount(1, $suites);

        $suite = array_shift($suites);

        static::assertInstanceOf(Suite::class, $suite);
        static::assertCount(20, $suite->getFunctions());
    }

    public function testRunWithFatalParseErrors(): void
    {
        $options = $this->createOptionsFromArgv([
            '--path' => $this->fixture('fatal_tests' . DS . 'UnitTestWithFatalParseErrorTest.php'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);

        self::expectException(ParseError::class);

        $loader->load();
    }

    public function testCacheIsWarmedWhenSpecified(): void
    {
        $options = $this->createOptionsFromArgv([
            '--configuration' => $this->fixture('phpunit-coverage-cache.xml'),
        ]);
        $loader  = new SuiteLoader($options, $this->output);
        $loader->load();

        static::assertStringContainsString('Warming cache', $this->output->fetch());
    }

    /**
     * @return string[]
     */
    private function findTests(string $dir): array
    {
        $it    = new RecursiveDirectoryIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        $it    = new RecursiveIteratorIterator($it);
        $files = [];
        foreach ($it as $file) {
            $match = preg_match('/Test\.php$/', $file->getPathname());
            self::assertNotFalse($match);
            if ($match === 0) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }
}
