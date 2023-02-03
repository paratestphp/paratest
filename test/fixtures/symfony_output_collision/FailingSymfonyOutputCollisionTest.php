<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\symfony_output_collision;

use PHPUnit\Framework\TestCase;

/** @internal */
final class FailingSymfonyOutputCollisionTest extends TestCase
{
    public function testInvalidLogic(): void
    {
        $this->assertSame('<bg=%s> </>', '');
    }
}
