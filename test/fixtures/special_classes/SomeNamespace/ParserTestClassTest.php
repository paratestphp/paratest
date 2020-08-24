<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\special_classes\SomeNamespace;

use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Classes.ClassFileName.NoMatch,PSR1.Classes.ClassDeclaration.MultipleClasses

// Test that it gives the class matching the file name priority.
/**
 * @internal
 */
final class NonTestClassTest
{
}

/**
 * @internal
 */
final class SomeOtherClassTest extends TestCase
{
}

/**
 * @internal
 */
final class AnotherClassTest extends TestCase
{
}

/**
 * @internal
 */
final class ParserTestClassTest extends TestCase
{
}

// phpcs:enable
