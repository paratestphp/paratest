<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passthru_tests;

use ParaTest\Tests\Unit\WrapperRunner\WrapperRunnerTest;
use PHPUnit\Framework\TestCase;

use function ini_get;

/** @internal */
final class PassthruTest extends TestCase
{
    public function testExit(): void
    {
        self::assertSame(WrapperRunnerTest::PASSTHRU_PHP_CUSTOM, ini_get('highlight.comment'));
    }
}
