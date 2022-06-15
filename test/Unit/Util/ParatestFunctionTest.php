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
        $argv   = [];
        $argv[] = './vendor/brianium/paratest/bin/paratest_for_phpstorm';
        $argv[] = '/home/user/repos/test/vendor/phpunit/phpunit/phpunit';
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--no-coverage';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $script     = ParatestFunction::createScriptForParatest($argv, __DIR__);
        $expected   = [];
        $expected[] = './vendor/brianium/paratest/bin/paratest_for_phpstorm';
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--no-coverage';
        $expected[] = '--log-teamcity';
        $expected[] = 'php://stdout';
        static::assertSame($expected, $argv);
        static::assertSame(__DIR__ . '/paratest', $script);
    }
}
