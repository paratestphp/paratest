<?php

class ExcludedFailingTest extends PHPUnit\Framework\TestCase
{
    public function testFail()
    {
        $this->assertTrue(false);
    }
}
