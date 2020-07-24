<?php

declare(strict_types=1);

class TestOfUnits extends PHPUnit\Framework\TestCase
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group fixtures
     */
    public function testArrayLength(): void
    {
        $elems = [1, 2, 3, 4, 5];
        $this->assertEquals(5, sizeof($elems));
    }
}
