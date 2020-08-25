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
    /** @var Suite */
    protected $failureSuite;
    /** @var Suite */
    protected $otherErrorSuite;
    /** @var Suite */
    protected $mixedSuite;
    /** @var Suite */
    protected $passingSuite;
    /** @var Suite */
    protected $dataProviderSuite;
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
        $this->otherErrorSuite   = $this->getSuiteWithResult('single-werror2.xml', 1);
        $this->failureSuite      = $this->getSuiteWithResult('single-wfailure.xml', 3);
        $this->mixedSuite        = $this->getSuiteWithResult('mixed-results.xml', 7);
        $this->skipped           = $this->getSuiteWithResult('single-skipped.xml', 1);
        $this->passingSuite      = $this->getSuiteWithResult('single-passing.xml', 3);
        $this->dataProviderSuite = $this->getSuiteWithResult('data-provider-result.xml', 50);

        $this->setUpInterpreter();
    }

    abstract protected function setUpInterpreter(): void;

    final protected function getSuiteWithResult(string $result, int $methodCount): Suite
    {
        $functions = [];
        for ($i = 0; $i < $methodCount; ++$i) {
            $functions[] = new TestMethod((string) $i, [], false, TMP_DIR);
        }

        $suite = new Suite('', $functions, false, TMP_DIR);
        file_put_contents($suite->getTempFile(), (string) file_get_contents(FIXTURES . DS . 'results' . DS . $result));

        return $suite;
    }
}
