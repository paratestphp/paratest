<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Util;

use Generator;
use ParaTest\Util\PhpstormHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function array_values;
use function sprintf;
use function uniqid;

/**
 * @internal
 *
 * @covers \ParaTest\Util\PhpstormHelper
 */
final class PhpstormHelperTest extends TestCase
{
    public function testThrowExceptionWithInvalidArgv(): void
    {
        $argv              = [];
        $expectedException = null;

        try {
            PhpstormHelper::handleArgvFromPhpstorm($argv, 'some-paratest-binary');
        } catch (Throwable $exception) {
            $expectedException = $exception;
        }

        self::assertInstanceOf(RuntimeException::class, $expectedException);
        self::assertSame("Missing path to 'phpunit'", $expectedException->getMessage());
    }

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

        self::assertSame($expectedArgv, $argv);
        self::assertSame($expectedBinary, $actualBinary);
    }

    public function providePhpstormCases(): Generator
    {
        $paratestBinary = sprintf('%s/vendor/brianium/paratest/bin/paratest', uniqid());
        $phpunitBinary  = sprintf('%s/vendor/phpunit/phpunit/phpunit', uniqid());

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
        $argv[] = '-dxdebug.mode=coverage';
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--coverage-clover';
        $argv[] = '/home/user/repos/test/coverage.xml';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--coverage-clover';
        $expected[] = '/home/user/repos/test/coverage.xml';
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
        $argv[] = '-dxdebug.mode=coverage';
        $argv[] = $phpunitBinary;
        $argv[] = '--coverage-clover';
        $argv[] = '/home/user/repos/test/coverage.xml';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--coverage-clover';
        $expected[] = '/home/user/repos/test/coverage.xml';
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
        $argv[] = '-dpcov.enabled=1';
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--coverage-clover';
        $argv[] = '/home/user/repos/test/coverage.xml';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expected   = [];
        $expected[] = $paratestBinary;
        $expected[] = '--runner';
        $expected[] = 'WrapperRunner';
        $expected[] = '--coverage-clover';
        $expected[] = '/home/user/repos/test/coverage.xml';
        $expected[] = '--configuration';
        $expected[] = '/home/user/repos/test/phpunit.xml';
        $expected[] = '--teamcity';

        yield 'with -dpcov.enabled=1 run ParaTest' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];

        $argv   = [];
        $argv[] = $paratestBinary;
        $argv[] = sprintf('%s/bin/phpunit', uniqid());

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

        yield 'with phpunit binary under bin/phpunit' => [
            $argv,
            $expected,
            $paratestBinary,
            $paratestBinary,
        ];
    }
}
