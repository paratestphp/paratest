<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\WrapperWorker;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(WrapperWorker::class)]
final class WrapperWorkerTest extends TestBase
{
    public function testDisablesTestRunHistoryWithoutDeprecatedOption(): void
    {
        $output  = new BufferedOutput();
        $options = $this->createOptionsFromArgv([
            'path' => __FILE__,
            '--verbose' => true,
        ]);

        new WrapperWorker($output, $options, ['phpunit-wrapper'], 1);

        $command = $output->fetch();
        self::assertStringContainsString('--do-not-record-test-run-history', $command);
        self::assertStringNotContainsString('--do-not-cache-result', $command);
    }
}
