<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Util;

use Generator;
use ParaTest\Util\PhpstormHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_values;
use function sprintf;
use function uniqid;

/** @internal */
#[CoversClass(PhpstormHelper::class)]
final class PhpstormHelperTest extends TestCase
{
    public function testThrowExceptionWithInvalidArgv(): void
    {
        $argv = [];

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Missing path');

        PhpstormHelper::handleArgvFromPhpstorm($argv, 'some-paratest-binary');
    }

    /**
     * @param array<int, string> $argv
     * @param array<int, string> $expectedArgv
     */
    #[DataProvider('providePhpstormCases')]
    public function testPhpStormHelper(
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

    public static function providePhpstormCases(): Generator
    {
        $phpStormHelperBinary = sprintf('%s/bin/paratest_for_phpstorm', uniqid());
        $paratestBinary       = sprintf('%s/vendor/brianium/paratest/bin/paratest', uniqid());
        $phpunitBinary        = sprintf('%s/vendor/phpunit/phpunit/phpunit', uniqid());

        /**
         * The format of the command PHPStorm runs when minimally configured (no
         * runner, no coverage, no filter) as in the README is as follows:
         * $PATH_TO_PHP_BINARY
         * argv:
         *  $phpStormHelperBinary
         *  $phpunitBinary
         *  --configuration
         *  $PATH_TO_PHPUNIT_XML
         *  --teamcity
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpStormHelperBinary;
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $paratestBinary;

        yield 'baseline configuration' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * Adding test-runner options such as --runner WrapperRunner places the
         * argument immediately after $phpunitBinary
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--runner';
        $argv[] = 'WrapperRunner';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpStormHelperBinary;
        $expectedArgv[] = '--runner';
        $expectedArgv[] = 'WrapperRunner';
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $paratestBinary;

        yield 'with --wrapper' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * Adding --filter, such as when re-running failed tests, places the
         * filter arguments immediately after $phpunitBinary. In addition,
         * the helper should return $phpunitBinary instead of
         * $paratestBinary when running a subset of tests
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--filter';
        $argv[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpunitBinary;
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--filter';
        $expectedArgv[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $phpunitBinary;

        yield 'with --filter' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * When using --filter, all additional arguments before --configuration
         * or --bootstrap should be unset
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = $phpunitBinary;
        $argv[] = '--colors';
        $argv[] = 'auto';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--filter';
        $argv[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpunitBinary;
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--filter';
        $expectedArgv[] = '"/MyTests\\MyTest::testFalse( .*)?$$/"';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $phpunitBinary;

        yield 'with additional arguments passed to --filter' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * Running with coverage inserts the corresponding -d flag immediately
         * after $phpStormHelperBinary and before $phpunitBinary
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = '-dxdebug.mode=coverage';
        $argv[] = $phpunitBinary;
        $argv[] = '--coverage-clover';
        $argv[] = '/home/user/repos/test/coverage.xml';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpStormHelperBinary;
        $expectedArgv[] = '--coverage-clover';
        $expectedArgv[] = '/home/user/repos/test/coverage.xml';
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $paratestBinary;

        yield 'with -dxdebug.mode=coverage' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * Additionally, with PCov, the --passthru-php option must be used to
         * enable the sub-processes to report coverage - see README.md#pcov
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = '-dpcov.enabled=1';
        $argv[] = $phpunitBinary;
        $argv[] = '--passthru-php=\'-d\' \'pcov.enabled=1\'';
        $argv[] = '--coverage-clover';
        $argv[] = '/home/user/repos/test/coverage.xml';
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpStormHelperBinary;
        $expectedArgv[] = '--passthru-php=\'-d\' \'pcov.enabled=1\'';
        $expectedArgv[] = '--coverage-clover';
        $expectedArgv[] = '/home/user/repos/test/coverage.xml';
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $paratestBinary;

        yield 'with -dpcov.enabled=1' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];

        /**
         * Sometimes, the phpunit binary is not in the expected location, so it
         * needs to be able to locate it elsewhere
         */
        $argv   = [];
        $argv[] = $phpStormHelperBinary;
        $argv[] = sprintf('%s/bin/phpunit', uniqid());
        $argv[] = '--configuration';
        $argv[] = '/home/user/repos/test/phpunit.xml';
        $argv[] = '--teamcity';

        $expectedArgv   = [];
        $expectedArgv[] = $phpStormHelperBinary;
        $expectedArgv[] = '--configuration';
        $expectedArgv[] = '/home/user/repos/test/phpunit.xml';
        $expectedArgv[] = '--teamcity';

        $expectedBinary = $paratestBinary;

        yield 'with phpunit binary under bin/phpunit' => [
            $argv,
            $expectedArgv,
            $paratestBinary,
            $expectedBinary,
        ];
    }
}
