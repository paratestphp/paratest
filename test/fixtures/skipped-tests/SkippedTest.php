<?php

class SkippedTest extends \PHPUnit_Framework_TestCase
{
    public function testSkipped()
    {
        $this->markTestSkipped();
    }
}
