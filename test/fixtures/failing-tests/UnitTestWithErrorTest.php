<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'UnitTestWithMethodAnnotationsTest.php';

/**
 * @runParallel
 */
class UnitTestWithErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth()
    {
        throw new Exception("Error!!!");
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function isItFalse()
    {
        $this->assertFalse(false);
    }
}
