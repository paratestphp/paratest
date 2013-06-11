<?php
/**
 * @author Manuele Menozzi <mmenozzi@webgriffe.com>
 */

class ErrorTest extends \PHPUnit_Framework_TestCase
{
    public function testError()
    {
        throw new \Exception('Error here!');
    }
}
