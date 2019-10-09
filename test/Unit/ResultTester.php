<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use ParaTest\Parser\ParsedFunction;
use ParaTest\Runners\PHPUnit\Suite;

abstract class ResultTester extends \ParaTest\Tests\TestBase
{
    protected $errorSuite;
    protected $failureSuite;
    protected $otherErrorSuite;
    protected $mixedSuite;
    protected $passingSuite;
    protected $dataProviderSuite;

    public function setUp(): void
    {
        $this->errorSuite = $this->getSuiteWithResult('single-werror.xml', 1);
        $this->otherErrorSuite = $this->getSuiteWithResult('single-werror2.xml', 1);
        $this->failureSuite = $this->getSuiteWithResult('single-wfailure.xml', 3);
        $this->mixedSuite = $this->getSuiteWithResult('mixed-results.xml', 7);
        $this->passingSuite = $this->getSuiteWithResult('single-passing.xml', 3);
        $this->dataProviderSuite = $this->getSuiteWithResult('data-provider-result.xml', 50);
    }

    public function getSuiteWithResult($result, $methodCount)
    {
        $result = FIXTURES . DS . 'results' . DS . $result;
        $functions = [];
        for ($i = 0; $i < $methodCount; ++$i) {
            $functions[] = $this->mockFunction($i);
        }
        $suite = new Suite('', $functions);
        $suite->setTempFile($result);

        return $suite;
    }

    protected function mockFunction($functionCount)
    {
        return new ParsedFunction('doc', 'public', 'func' . $functionCount);
    }
}
