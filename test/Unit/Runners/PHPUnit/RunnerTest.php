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

    public function testFunctionalMode(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;

        $this->assertTestsPassed($this->runRunner(), '1150', '1150');
    }

    public function testNumericDataSetInFunctionalModeWithMethodFilter(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;
        $this->bareOptions['--filter']         = 'testNumericDataProvider50';

        $this->assertTestsPassed($this->runRunner(), '50', '50');
    }

    public function testNumericDataSetInFunctionalModeWithCustomFilter(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;
        $this->bareOptions['--filter']         = 'testNumericDataProvider50.*1';

        $this->assertTestsPassed($this->runRunner(), '14', '14');
    }

    public function testNamedDataSetInFunctionalModeWithMethodFilter(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;
        $this->bareOptions['--filter']         = 'testNamedDataProvider50';

        $this->assertTestsPassed($this->runRunner(), '50', '50');
    }

    public function testNamedDataSetInFunctionalModeWithCustomFilter(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;
        $this->bareOptions['--filter']         = 'testNamedDataProvider50.*name_of_test_.*1';

        $this->assertTestsPassed($this->runRunner(), '14', '14');
    }

    public function testNumericDataSet1000InFunctionalModeWithFilterAndMaxBatchSize(): void
    {
        $this->bareOptions['--path']           = $this->fixture('dataprovider-tests' . DS . 'DataProviderTest.php');
        $this->bareOptions['--functional']     = true;
        $this->bareOptions['--max-batch-size'] = 50;
        $this->bareOptions['--filter']         = 'testNumericDataProvider1000';

        $this->assertTestsPassed($this->runRunner(), '1000', '1000');
    }

    public function testSkippedInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('skipped-tests' . DS . 'SkippedOrIncompleteTest.php');
        $this->bareOptions['--functional'] = true;
        $this->bareOptions['--filter']     = 'testSkipped';

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Incomplete: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    public function testIncompleteInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('skipped-tests' . DS . 'SkippedOrIncompleteTest.php');
        $this->bareOptions['--functional'] = true;
        $this->bareOptions['--filter']     = 'testIncomplete';

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 1, Assertions: 0, Incomplete: 1.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(1, $runnerResult->getOutput());
    }

    public function testDataProviderWithSkippedInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('skipped-tests' . DS . 'SkippedOrIncompleteTest.php');
        $this->bareOptions['--functional'] = true;
        $this->bareOptions['--filter']     = 'testDataProviderWithSkipped';

        $runnerResult = $this->runRunner();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
            . 'Tests: 100, Assertions: 33, Incomplete: 67.';
        static::assertStringContainsString($expected, $runnerResult->getOutput());
        $this->assertContainsNSkippedTests(67, $runnerResult->getOutput());
    }
}
