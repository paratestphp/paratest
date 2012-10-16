<?php
/**
 * @runParallel
 */
class UnitTestWithErrorTest extends PHPUnit_Framework_TestCase
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
        sleep(2);
        $this->assertFalse(false);
    }
    
}
