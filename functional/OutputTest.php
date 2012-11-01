<?php

class OutputTest extends FunctionalTestBase
{
    public function testInstantFeedbackIsDisplayed()
    {
        $this->path = FIXTURES . DS . "tests" . DS . "UnitTestWithClassAnnotationTest.php";
        $output = $this->getParaTestOutput();
        $this->assertRegExp(
            '/^\nRunning phpunit in 5 processes with ' . 
            $this->getPhpUnitForRegEx() .
            '\n\n\.F\.\./', $output);
    }

    public function testInstantFeedbackIsDisplayedWhenFunctional()
    {
        $this->path = FIXTURES . DS . "tests" . DS . "UnitTestWithClassAnnotationTest.php";
        $output = $this->getParaTestOutput(true);
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