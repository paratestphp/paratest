<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\unexpected_output;

use PHPUnit\Framework\TestCase;

/** @internal */
final class UnexpectedOutputTest extends TestCase
{
    public function testInvalidLogic(): void
    {
        echo 'foobar';

        $this->assertTrue(true);
    }
}
