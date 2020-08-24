<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\WrapperRunner;

/**
 * @internal
 *
 * @requires OSFAMILY Linux
 * @covers \ParaTest\Runners\PHPUnit\BaseWrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 * @covers \ParaTest\Runners\PHPUnit\Worker\WrapperWorker
 */
final class WrapperRunnerTest extends RunnerTestCase
{
    /** {@inheritdoc } */
    protected $runnerClass = WrapperRunner::class;

    public function testWrapperRunnerNotAvailableInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--functional'] = true;

        $this->expectException(InvalidArgumentException::class);

        $this->runRunner();
    }
}
