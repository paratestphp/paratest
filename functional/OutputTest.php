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
        $this->assertContains("Running phpunit in 5 processes with " . PHPUNIT, $output);
        $this->assertContains("Configuration read from " . getcwd() . DS . 'phpunit.xml.dist', $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied()
    {
        $output = $this->getParaTestOutput(false, array('configuration' => 'nope.xml'));
        $this->assertContains('Could not read "nope.xml"', $output);
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
        $this->assertContains("Running phpunit in 1 process with " . PHPUNIT, $output);
        $this->assertContains("Configuration read from " . getcwd() . DS . 'phpunit.xml.dist', $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }

    protected function assertFunctionalModeIsOnWithFeedback($output)
    {
        $this->assertContains("Running phpunit in 5 processes with " . PHPUNIT, $output);
        $this->assertContains("Functional mode is on", $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }

    protected function getPhpUnitForRegEx()
    {
        return str_replace("/", "\\/", PHPUNIT);
    }
}