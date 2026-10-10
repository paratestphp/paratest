<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\private_tmp_dir;

use PHPUnit\Framework\TestCase;

use function array_search;
use function basename;
use function dirname;
use function fileperms;

/** @internal */
final class PrivateTmpDirTest extends TestCase
{
    public function testWorkerFilesAreInAPrivateDirectory(): void
    {
        $statusFileKey = array_search('--status-file', $_SERVER['argv'], true);
        $this->assertIsInt($statusFileKey);

        $runTmpDir = dirname($_SERVER['argv'][$statusFileKey + 1]);

        $this->assertStringStartsWith('paratest_', basename($runTmpDir));
        $this->assertSame(0700, fileperms($runTmpDir) & 0777);
    }
}
