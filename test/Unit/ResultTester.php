<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\Runners\PHPUnit\Suite;
use ParaTest\Runners\PHPUnit\TestMethod;
use ParaTest\Tests\TestBase;

use function file_get_contents;
use function file_put_contents;

abstract class ResultTester extends TestBase
{
    protected const SINGLE_PASSING_TEAMCITY_OUTPUT = <<<'EOF'
##teamcity[testCount count='3' flowId='118852']

##teamcity[testSuiteStarted name='ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest' locationHint='php_qn:///repos/paratest/test/fixtures/passing_tests/level1/AnotherUnitTestInSubLevelTest.php::\ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest' flowId='118852']

##teamcity[testStarted name='testTruth' locationHint='php_qn:///repos/paratest/test/fixtures/passing_tests/level1/AnotherUnitTestInSubLevelTest.php::\ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest::testTruth' flowId='118852']

##teamcity[testFinished name='testTruth' duration='1' flowId='118852']

##teamcity[testStarted name='testFalsehood' locationHint='php_qn:///repos/paratest/test/fixtures/passing_tests/level1/AnotherUnitTestInSubLevelTest.php::\ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest::testFalsehood' flowId='118852']

##teamcity[testFinished name='testFalsehood' duration='0' flowId='118852']

##teamcity[testStarted name='testArrayLength' locationHint='php_qn:///repos/paratest/test/fixtures/passing_tests/level1/AnotherUnitTestInSubLevelTest.php::\ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest::testArrayLength' flowId='118852']

##teamcity[testFinished name='testArrayLength' duration='0' flowId='118852']

##teamcity[testSuiteFinished name='ParaTest\Tests\fixtures\passthru_tests\level1\AnotherUnitTestInSubLevelTest' flowId='118852']
EOF;


    /** @var Suite */
    protected $failureSuite;
    /** @var Suite */
    protected $otherFailureSuite;
    /** @var Suite */
    protected $mixedSuite;
    /** @var Suite */
    protected $passingSuite;
    /** @var Suite */
    protected $errorSuite;
    /** @var Suite */
    protected $warningSuite;
    /** @var Suite */
    protected $skipped;

    final public function setUpTest(): void
    {
        $this->errorSuite        = $this->getSuiteWithResult('single-werror.xml', 1);
        $this->warningSuite      = $this->getSuiteWithResult('single-warning.xml', 1);
        $this->failureSuite      = $this->getSuiteWithResult('single-wfailure.xml', 3);
        $this->otherFailureSuite = $this->getSuiteWithResult('single-wfailure2.xml', 3);
        $this->mixedSuite        = $this->getSuiteWithResult('mixed-results.xml', 7);
        $this->skipped           = $this->getSuiteWithResult('single-skipped.xml', 1);
        $this->passingSuite      = $this->getSuiteWithResult('single-passing.xml', 3);

        $this->setUpInterpreter();
    }

    abstract protected function setUpInterpreter(): void;

    final protected function getSuiteWithResult(string $result, int $methodCount): Suite
    {
        $functions = [];
        for ($i = 0; $i < $methodCount; ++$i) {
            $functions[] = new TestMethod((string) $i, ['testMe'], false, true, $this->tmpDir);
        }

        $suite = new Suite('', $functions, false, true, $this->tmpDir);
        file_put_contents($suite->getTempFile(), (string) file_get_contents(FIXTURES . DS . 'results' . DS . $result));
        $teamcityData = 'no data';
        if ($result === 'single-passing.xml') {
            $teamcityData = self::SINGLE_PASSING_TEAMCITY_OUTPUT;
        }

        file_put_contents($suite->getTeamcityTempFile(), $teamcityData);

        return $suite;
    }
}
