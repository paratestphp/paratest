<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\passthru_tests;

use ParaTest\Tests\Unit\Runners\PHPUnit\RunnerTestCase;
use PHPUnit\Framework\TestCase;

use function ini_get;

/**
 * @internal
 */
final class PassthruTest extends TestCase
{
    public function testExit(): void
    {
        static::assertSame(RunnerTestCase::PASSTHRU_PHP_CUSTOM, ini_get('highlight.comment'));
        static::assertSame(RunnerTestCase::PASSTHRU_PHPUNIT_CUSTOM, ini_get('highlight.string'));
    }
}
