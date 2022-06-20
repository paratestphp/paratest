<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\parallel_suite;

use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function file_put_contents;
use function getenv;
use function is_file;
use function str_replace;

abstract class ParallelBase extends TestCase
{
    final public function testToken(): void
    {
        $refClass = new ReflectionClass(static::class);
        $file     = (new TmpDirCreator())->create() . DS . 'token_' . str_replace(['\\', '/'], '_', $refClass->getNamespaceName());

        $token = getenv('TEST_TOKEN');
        static::assertIsString($token);

        if (! is_file($file)) {
            file_put_contents($file, $token);
        }

        static::assertStringEqualsFile($file, $token);
    }
}
