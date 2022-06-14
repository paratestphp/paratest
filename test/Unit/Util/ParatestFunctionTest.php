<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Util;

use ParaTest\Util\ParatestFunction;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \ParaTest\Util\ParatestFunction
 */
final class ParatestFunctionTest extends TestCase
{
    public function testCreateScriptForParatest(): void
    {
        self::setDefaultArgvParameter();
        ParatestFunction::createScriptForParatest($_SERVER['argv'], __DIR__);
        $expected   = [];
        $expected[] = './vendor/brianium/paratest/bin/paratest_for_phpstorm';
        $expected[] = '--log-teamcity';
        $expected[] = 'php://stdout';
        static::assertEquals($expected, $_SERVER['argv']);
    }

    private function setDefaultArgvParameter(): void
    {
        unset($_SERVER['argv']);
        $_SERVER['argv'][] = './vendor/brianium/paratest/bin/paratest_for_phpstorm';
        $_SERVER['argv'][] = '/home/user/repos/test/vendor/phpunit/phpunit/phpunit';
        $_SERVER['argv'][] = '--configuration';
        $_SERVER['argv'][] = '/home/user/repos/test/phpunit.xml';
        $_SERVER['argv'][] = '--teamcity';
    }
}
