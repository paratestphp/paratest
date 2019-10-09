<?php

namespace SomeNamespace;

// Test that it gives the class matching the file name priority.
class SomeOtherClass extends \PHPUnit\Framework\TestCase
{
}

class ParserTestClass extends \PHPUnit\Framework\TestCase
{
}

class AnotherClass extends \PHPUnit\Framework\TestCase
{
}
