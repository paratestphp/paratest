<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\WrapperRunner;

use function array_map;
use function implode;
use function sha1;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\WrapperWorker
 * @covers \ParaTest\Runners\PHPUnit\WorkerCrashedException
 */
final class WrapperRunnerTest extends RunnerTestCase
{
    /** {@inheritdoc } */
    protected $runnerClass = WrapperRunner::class;

    /**
     * Wrapper runner will use a TestSuite wrapper class based on the sha1 hash
     * of the class name.
     *
     * @param array<class-string> $classes
     */
    protected function expectExceptionMessageContainsClasses(array $classes): void
    {
        $classes = array_map(static function (string $class): string {
            return 'TestSuite' . sha1($class);
        }, $classes);

        $this->expectExceptionMessageMatches('/' . implode('|', $classes) . '/');
    }

    public function testWrapperRunnerNotAvailableInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--functional'] = true;

        $this->expectException(InvalidArgumentException::class);

        $this->runRunner();
    }

  /**
   * @see github.com/paratestphp/paratest/pull/540
   * we test that everything is okey with few tests
   * was problem that phpunit reset global variables in phpunit-wrapper, and tests fails
   */
    public function testWrapperRunnerWorksWellWithManyTests(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing_tests' . DS . 'level1' . DS . 'level2');
        $this->bareOptions['--configuration'] = $this->bareOptions['--configuration']  = $this->fixture('phpunit-parallel-suite-with-globals.xml');

        $this->runRunner();
    }
}
