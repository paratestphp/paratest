<?php

class OutputTest extends FunctionalTestBase
{

    public function setUp()
    {
        parent::setUp();
        $this->paratest = new ParaTestInvoker(
            $this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'),
            BOOTSTRAP
        );
    }

    public function testDefaultMessagesDisplayed()
    {
        $output = $this->paratest->execute()->getOutput();
        $this->assertContains("Running phpunit in 5 processes with " . PHPUNIT, $output);
        $this->assertContains("Configuration read from " . getcwd() . DS . 'phpunit.xml.dist', $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied()
    {
        $output = $this->paratest
            ->execute(array('configuration' => 'nope.xml'))
            ->getOutput();
        $this->assertContains('Could not read "nope.xml"', $output);
    }

    public function testMessagePrintedWhenFunctionalModeIsOn()
    {
        $output = $this->paratest
            ->execute(array('functional'))
            ->getOutput();
        $this->assertContains("Running phpunit in 5 processes with " . PHPUNIT, $output);
        $this->assertContains("Functional mode is ON.", $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }

    public function testProcCountIsReportedWithProcOption()
    {
        $output = $this->paratest->execute(array('p'=>1))
            ->getOutput();
        $this->assertContains("Running phpunit in 1 process with " . PHPUNIT, $output);
        $this->assertRegExp('/[.F]{4}/', $output);
    }
}
