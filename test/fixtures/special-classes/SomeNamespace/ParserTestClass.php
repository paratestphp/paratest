<?php

declare(strict_types=1);

namespace SomeNamespace;

use PHPUnit\Framework\TestCase;

// Test that it gives the class matching the file name priority.
final class NonTestClass
{
}

final class SomeOtherClass extends TestCase
{
}

final class AnotherClass extends TestCase
{
}

final class ParserTestClass extends TestCase
{
}
