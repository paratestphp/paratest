<?php
namespace SomeNamespace;

// Test that it gives the class matching the file name priority.
class SomeOtherClass extends \PHPUnit_Framework_TestCase{}

class ParserTestClass extends \PHPUnit_Framework_TestCase{}

class AnotherClass extends \PHPUnit_Framework_TestCase{}
