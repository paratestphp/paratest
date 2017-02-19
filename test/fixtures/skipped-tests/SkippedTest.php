<?php

class SkippedTest extends \PHPUnit\Framework\TestCase
{
    public function testSkipped()
    {
        $this->markTestSkipped();
    }
}
