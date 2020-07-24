<?php

declare(strict_types=1);

namespace SomeNamespace;

use PHPUnit\Framework\TestCase;

// Test that it gives the class matching the file name priority.
class SomeOtherClass extends TestCase
{
}

class ParserTestClass extends TestCase
{
}

class AnotherClass extends TestCase
{
}
