<?php

new class() extends PHPUnit_Framework_TestCase {
    /**
     * @return class
     */
    public function testAnonymousClass()
    {
        return new class()
        {
        };
    }
};
