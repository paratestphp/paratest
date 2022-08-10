<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Util;

use Generator;
use ParaTest\Util\PhpstormHelper;
use PHPUnit\Framework\TestCase;

use function array_values;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Util\PhpstormHelper
 */
final class PhpstormHelperTest extends TestCase
{
    /**
     * @param array<int, string> $argv
     * @param array<int, string> $expectedArgv
     *
     * @dataProvider providePhpstormCases
     */
    public function testWithoutFilterRunParaTest(
        array $argv,
        array $expectedArgv,
        string $paratestBinary,
        string $expectedBinary
    ): void {
        $actualBinary = PhpstormHelper::handleArgvFromPhpstorm($argv, $paratestBinary);
        $argv         = array_values($argv);

        static::assertSame($expectedArgv, $argv);
        self::assertSame($expectedBinary, $actualBinary);
    }

    public function providePhpstormCases(): Generator
    {
        $paratestBinary = uniqid('paratest_');
        $phpunitBinary  = uniqid('phpunit_');

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--no-coverage';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--no-coverage';
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--teamcity';

        yield 'without --filter run ParaTest' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--no-coverage';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--filter';
        $argv[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $phpunitBinary;
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--filter';
        $expected[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $expected[] = '--teamcity';

        yield 'with --filter run PHPUnit' => [
            $argv,
            $expected,
            $paratestBinary,
            $phpunitBinary,
        ];

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '-dxdebug.mode=coverage';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--teamcity';

        yield 'with -dxdebug.mode=coverage run ParaTest' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '-dxdebug.mode=coverage';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--teamcity';

        yield 'with -dxdebug.mode=coverage and no wrapper run ParaTest' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '-dpcov.enabled=1';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--teamcity';

        yield 'with -dpcov.enabled=1 run ParaTest' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];
    }
}
