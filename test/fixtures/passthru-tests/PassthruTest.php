<?php

declare(strict_types=1);

use ParaTest\Tests\Unit\Runners\PHPUnit\RunnerTestCase;

final class PassthruTest extends \PHPUnit\Framework\TestCase
{
    public function testExit(): void
    {
        static::assertSame(RunnerTestCase::PASSTHRU_PHP_CUSTOM, ini_get('highlight.comment'));
        static::assertSame(RunnerTestCase::PASSTHRU_PHPUNIT_CUSTOM, ini_get('highlight.string'));
    }
}
