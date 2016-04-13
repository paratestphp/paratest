<?php

class DataProviderTest extends FunctionalTestBase
{
    /** @var ParatestInvoker */
    private $invoker;

    public function setUp()
    {
        parent::setUp();
        $this->invoker = new ParaTestInvoker(
            $this->fixture('dataprovider-tests/DataProviderTest.php'),
            BOOTSTRAP
        );
    }

    public function testFunctionalMode()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
        ));
        $this->assertRegExp('/OK \(1100 tests, 1100 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSetInFunctionalModeWithMethodFilter()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter" => "testNumericDataProvider50"
        ));
        $this->assertRegExp('/OK \(50 tests, 50 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSetInFunctionalModeWithCustomFilter()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter" => "testNumericDataProvider50.*1"
        ));
        $this->assertRegExp('/OK \(14 tests, 14 assertions\)/', $proc->getOutput());
    }

    public function testNamedDataSetInFunctionalModeWithMethodFilter()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter" => "testNamedDataProvider50"
        ));
        $this->assertRegExp('/OK \(50 tests, 50 assertions\)/', $proc->getOutput());
    }

    public function testNamedDataSetInFunctionalModeWithCustomFilter()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter" => "testNamedDataProvider50.*name_of_test_.*1"
        ));
        $this->assertRegExp('/OK \(14 tests, 14 assertions\)/', $proc->getOutput());
    }

    public function testNumericDataSet1000InFunctionalModeWithFilterAndMaxBatchSize()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter" => "testNumericDataProvider1000"
        ));
        $this->assertRegExp('/OK \(1000 tests, 1000 assertions\)/', $proc->getOutput());
    }
}
