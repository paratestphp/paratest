<?php

/**
 * Specifically tests warnings in PHPUnit
 */
class PHPUnitWarningsTest extends FunctionalTestBase
{
    public function testTestsWithWarningsResultInFailure()
    {
        $proc = $this->invokeParatest(
            "warning-tests/HasWarningsTest.php",
            array('bootstrap' => BOOTSTRAP)
        );

        $output = $proc->getOutput();

        $this->assertTrue(version_compare(PHPUnit\Runner\Version::id(), '6.0.0', '>='), 'Expected phpunit 6.0.0+');
        // PHPUnit 5.1+ Changed how it handles test warnings (not E_WARNINGS)
        // PHPUnit 6.0 changed it back to non-zero exit code : https://github.com/sebastianbergmann/phpunit/issues/2446
        // TODO: Does this have any consequences for paratest?
        $this->assertContains("Warnings", $output, "Test should output warnings");
        $this->assertEquals(1, $proc->getExitCode(), "Test suite should succeed with 0");
    }
}
