<?php

/**
 * @todo SkippedOrIncompleteTest can't be used in default mode with group filter
 *       (not implemented yet) so we have to split tests per file.
 */
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

    public function testSkippedInFunctionalMode()
    {
        $proc = $this->invoker->execute(array(
            "functional" => null,
            "filter"     => "testSkipped"
        ));

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testIncompleteInFunctionalMode()
    {
        $proc = $this->invoker->execute(array(
            "functional" => null,
            "filter"     => "testIncomplete"
        ));

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testDataProviderWithSkippedInFunctionalMode()
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

    public function testSkippedInDefaultMode()
    {
        // amount of tests is known, based on amount of methods, so
        // we can identify skipped tests

        $this->invoker = new ParaTestInvoker(
            $this->fixture('skipped-tests/SkippedTest.php'),
            BOOTSTRAP
        );

        $proc = $this->invoker->execute();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testIncompleteInDefaultMode()
    {
        // amount of tests is known, based on amount of methods, so
        // we can identify skipped tests

        $this->invoker = new ParaTestInvoker(
            $this->fixture('skipped-tests/IncompleteTest.php'),
            BOOTSTRAP
        );

        $proc = $this->invoker->execute();

        $expected = "OK, but incomplete, skipped, or risky tests!\n"
                  . "Tests: 1, Assertions: 0, Incomplete: 1.";
        $this->assertContains($expected, $proc->getOutput());

        $this->assertEquals(1, substr_count($proc->getOutput(), "S"));
    }

    public function testDataProviderWithSkippedInDefaultMode()
    {
        // amount of tests is known, but based on amount of methods,
        // but test has more actual tests from data provider so
        // we can't identify skipped tests

        $this->invoker = new ParaTestInvoker(
            $this->fixture('skipped-tests/SkippedAndIncompleteDataProviderTest.php'),
            BOOTSTRAP
        );

        $proc = $this->invoker->execute();

        $expected = "OK (33 tests, 33 assertions)";
        $this->assertContains($expected, $proc->getOutput());
    }
}
