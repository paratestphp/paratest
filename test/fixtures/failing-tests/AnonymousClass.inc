<?php

new class() {
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
