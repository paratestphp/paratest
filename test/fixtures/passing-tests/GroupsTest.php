<?php

declare(strict_types=1);

/**
 * @group group4
 */
class GroupsTest extends PHPUnit\Framework\TestCase
{
    /**
     * @group group1
     */
    public function testTruth(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @group group1
     */
    public function testFalsehood(): void
    {
        $this->assertFalse(false);
    }

    /**
     * @group group2
     */
    public function testArrayLength(): void
    {
        $values = [1, 3, 4, 7];
        $this->assertEquals(4, count($values));
    }

    /**
     * @group group2
     * @group group3
     */
    public function testStringLength(): void
    {
        $string = 'hello';
        $this->assertEquals(5, strlen($string));
    }

    public function testAddition(): void
    {
        $vals = 1 + 1;
        $this->assertEquals(2, $vals);
    }
}
