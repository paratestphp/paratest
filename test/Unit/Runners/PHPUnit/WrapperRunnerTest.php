<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\WrapperRunner;

use function array_diff;
use function array_unique;
use function file_get_contents;
use function min;
use function scandir;
use function unlink;

use const FIXTURES;

/**
 * @internal
 *
 * @covers \ParaTest\Runners\PHPUnit\BaseRunner
 * @covers \ParaTest\Runners\PHPUnit\WrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\WrapperWorker
 * @covers \ParaTest\Runners\PHPUnit\WorkerCrashedException
 */
final class WrapperRunnerTest extends RunnerTestCase
{
    protected const NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE = 4;
    protected const UNPROCESSABLE_FILENAMES              =  ['..', '.', '.gitignore'];

    public function testWrapperRunnerNotAvailableInFunctionalMode(): void
    {
        $this->bareOptions['--path']       = $this->fixture('passing_tests' . DS . 'GroupsTest.php');
        $this->bareOptions['--functional'] = true;

        $this->expectException(InvalidArgumentException::class);

        $this->runRunner();
    }

  /**
   * @see github.com/paratestphp/paratest/pull/540
   * we test that everything is okey with few tests
   * was problem that phpunit reset global variables in phpunit-wrapper, and tests fails
   */
    public function testWrapperRunnerWorksWellWithManyTests(): void
    {
        $this->bareOptions['--path']          = $this->fixture('passing_tests' . DS . 'level1' . DS . 'level2');
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-parallel-suite-with-globals.xml');

        $this->runRunner();
    }

    /** @dataProvider provideForWrapperRunnerHandlesBatchSize */
    public function testWrapperRunnerHandlesBatchSize(int $processes, ?int $batchSize, int $expectedPidCount): void
    {
        $this->bareOptions['--path']          = $this->fixture('wrapper_batchsize_suite');
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-wrapper-batchsize-suite.xml');
        $this->bareOptions['--processes']     = (string) $processes;
        if ($batchSize !== null) {
            $this->bareOptions['--max-batch-size'] = (string) $batchSize;
        }

        $tmpDir        = FIXTURES . DS . 'wrapper_batchsize_suite' . DS . 'tmp';
        $pidFilesDir   = $tmpDir . DS . 'pid';
        $tokenFilesDir = $tmpDir . DS . 'token';

        $this->cleanContentFromDir($pidFilesDir);
        $this->cleanContentFromDir($tokenFilesDir);

        $this->runRunner();

        self::assertCount($expectedPidCount, $this->extractContentFromDirFiles($pidFilesDir));
        self::assertCount(min([self::NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE, $processes]), $this->extractContentFromDirFiles($tokenFilesDir));
    }

    /** @return iterable<array{int,?int,int}> */
    public static function provideForWrapperRunnerHandlesBatchSize(): iterable
    {
        yield 'One process with batchsize = null should have 1 pids and 1 token' =>  [1, null, 1];
        yield 'One process with batchsize = 0 should have 1 pids and 1 token' =>  [1, 0, 1];
        yield 'One process with batchsize = 1 should have 4 pids and 1 token' =>  [1, 1, 4];
        yield 'One process with batchsize = 2 should have 2 pids and 1 token' =>  [1, 2, 2];
        yield 'Two processes with batchsize = 2 should have 2 pids and 2 tokens' =>  [2, 2, 2];
    }

    private function cleanContentFromDir(string $path): void
    {
        $cleanableFiles = array_diff(scandir($path), self::UNPROCESSABLE_FILENAMES);
        foreach ($cleanableFiles as $cleanableFile) {
            unlink($path . DS . $cleanableFile);
        }
    }

    /** @return array<string> */
    private function extractContentFromDirFiles(string $path): array
    {
        $res              = [];
        $processableFiles = array_diff(scandir($path), self::UNPROCESSABLE_FILENAMES);
        self::assertCount(self::NUMBER_OF_CLASS_TESTS_FOR_BATCH_SIZE, $processableFiles);
        foreach ($processableFiles as $processableFile) {
            $res[] = file_get_contents($path . DS . $processableFile);
        }

        return array_unique($res);
    }
}
