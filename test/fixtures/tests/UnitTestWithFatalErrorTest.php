<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'UnitTestWithMethodAnnotationsTest.php';

/**
 * @runParallel
 */
class UnitTestWithFatalErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth()
    {
        die("Hi! I'm Fatal");
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function isItFalse()
    {
        sleep(2);
        $this->assertFalse(false);
    }
}
