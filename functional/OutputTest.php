<?php

class OutputTest extends FunctionalTestBase
{

    public function setUp()
    {
        parent::setUp();
        $this->path = FIXTURES . DS . "tests" . DS . "UnitTestWithClassAnnotationTest.php";
    }

    public function testInstantFeedbackIsDisplayed()
    {
        $output = $this->getParaTestOutput();
        $this->assertRegExp(
            '/^\nRunning phpunit in 5 processes with ' . 
            $this->getPhpUnitForRegEx() .
            '\n\nConfiguration read from ' . str_replace('/', '\\/', getcwd() . DS . 'phpunit\.xml\.dist') .
            '\n\n\.F\.\./', $output);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied()
    {
        $output = $this->getParaTestOutput(false, array('configuration' => 'nope.xml'));
        $this->assertRegExp('/^Could not read "nope\.xml"\.\n$/', $output);
    }

    public function testInstantFeedbackIsDisplayedWhenAndFunctionalModeDsiplayed()
    {
        $output = $this->getParaTestOutput(true);
        $this->assertFunctionalModeIsOnWithFeedback($output);
    }

    public function testFunctionalModeIsDisplayedWithShortFunctionalOption()
    {
        $output = $this->getParaTestOutput(false, array('f' => ''));
        $this->assertFunctionalModeIsOnWithFeedback($output);
    }

    public function testProcCountIsReportedWithShortProcOption()
    {
        $output = $this->getParaTestOutput(false, array('p' => '1'));
        $this->assertRegExp(
            '/^\nRunning phpunit in 1 process with ' . 
            $this->getPhpUnitForRegEx() .
            '\n\nConfiguration read from ' . str_replace('/', '\\/', getcwd() . DS . 'phpunit\.xml\.dist') .
            '\n\n\.F\.\./', $output);
    }

    protected function assertFunctionalModeIsOnWithFeedback($output)
    {
        $this->assertRegExp(
            '/^\nRunning phpunit in 5 processes with ' .
            $this->getPhpUnitForRegEx() .
            '. Functional mode is on' .
            '\n\n[.FE]*/', $output);
    }

    protected function getPhpUnitForRegEx()
    {
        return str_replace("/", "\\/", PHPUNIT);
    }
}