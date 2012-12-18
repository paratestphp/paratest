<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'UnitTestWithMethodAnnotationsTest.php';

/**
 * @runParallel
 */
class UnitTestWithFatalParseErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth()
    {
        I will fail fataly because this is not a php statement.
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
