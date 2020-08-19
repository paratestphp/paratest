<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use function preg_match;

/**
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 * @covers \ParaTest\Runners\PHPUnit\Runner
 * @covers \ParaTest\Runners\PHPUnit\Worker\RunnerWorker
 */
final class RunnerTest extends RunnerTestCase
{
    public function testStopOnFailureEndsRunBeforeWholeTestSuite(): void
    {
        $this->bareOptions['--path'] = $this->fixture('failing-tests');
        $runnerResult                = $this->runRunner();

        $regexp = '/Tests: \d+, Assertions: \d+, Failures: \d+, Errors: \d+\./';
        static::assertSame(1, preg_match($regexp, $runnerResult->getOutput(), $matchesOnFullRun));

        $this->bareOptions['--stop-on-failure'] = true;
        $runnerResult                           = $this->runRunner();

        static::assertSame(1, preg_match($regexp, $runnerResult->getOutput(), $matchesOnPartialRun));

        static::assertNotEquals($matchesOnFullRun[0], $matchesOnPartialRun[0]);
    }
}
