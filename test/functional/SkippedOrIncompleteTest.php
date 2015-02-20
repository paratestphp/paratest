<?php

class SkippedOrIncompleteTest extends FunctionalTestBase
{
    /** @var ParatestInvoker */
    private $invoker;

    public function setUp()
    {
        parent::setUp();
        $this->invoker = new ParaTestInvoker(
            $this->fixture('skipped-tests/SkippedOrIncompleteTest.php'),
            BOOTSTRAP
        );
    }

    public function testSkipped()
    {
        $proc = $this->invoker->execute(array(
            "filter" => "testSkipped"
        ));

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testIncomplete()
    {
        $proc = $this->invoker->execute(array(
            "filter" => "testSkipped"
        ));

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testDataProviderWithSkipped()
    {
        $proc = $this->invoker->execute(array(
            "functional"     => null,
            "max-batch-size" => 50,
            "filter"         => "testDataProviderWithSkipped"
        ));

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 100, Assertions: 33, Incomplete: 67.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(67, substr_count($proc->getOutput(), "S"));
    }
}
