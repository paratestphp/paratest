<?php

class ErrorTest extends \PHPUnit_Framework_TestCase
{
    public function testError()
    {
        throw new \Exception('Error here!');
    }
}
