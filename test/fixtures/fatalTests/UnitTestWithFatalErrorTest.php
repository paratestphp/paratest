<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'UnitTestWithMethodAnnotationsTest.php';

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
        return $this->testTruth();
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
