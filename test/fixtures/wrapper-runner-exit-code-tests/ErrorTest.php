<?php

class ErrorTest extends \PHPUnit\Framework\TestCase
{
    public function testError()
    {
        throw new \Exception('Error here!');
    }
}
