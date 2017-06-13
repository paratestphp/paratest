<?php

new class() extends PHPUnit\Framework\TestCase {
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
