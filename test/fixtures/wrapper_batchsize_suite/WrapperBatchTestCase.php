<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\wrapper_batchsize_suite;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function file_put_contents;
use function getenv;
use function getmypid;
use function str_replace;

use const DIRECTORY_SEPARATOR;

abstract class WrapperBatchTestCase extends TestCase
{
    private const TMP_DIR_PATH = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';

    public function testToken(): void
    {
        $tokenFile = self::TMP_DIR_PATH . DIRECTORY_SEPARATOR . 'token' . DIRECTORY_SEPARATOR . $this->getFileSuffix();
        $token     = getenv('TEST_TOKEN');
        self::assertIsString($token);
        file_put_contents($tokenFile, $token);
        self::assertStringEqualsFile($tokenFile, $token);
    }

    public function testPid(): void
    {
        $pidFile =  self::TMP_DIR_PATH . DIRECTORY_SEPARATOR . 'pid' . DIRECTORY_SEPARATOR . $this->getFileSuffix();
        $pid     = (string) getmypid();
        file_put_contents($pidFile, $pid);

        self::assertStringEqualsFile($pidFile, $pid);
    }

    private function getFileSuffix(): string
    {
        $refClass = new ReflectionClass(static::class);

        return str_replace(['\\', '/'], '_', $refClass->getName());
    }
}
