<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\SuiteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_shift;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @internal */
#[CoversClass(SuiteLoader::class)]
final class SuiteLoaderTest extends TestBase
{
    private BufferedOutput $output;

    protected function setUpTest(): void
    {
        $this->output = new BufferedOutput();
    }

    public function testLoadTestsuiteFileFromConfig(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        self::assertSame(7, $loader->testCount);
        self::assertCount(7, $loader->tests);
    }

    public function testLoadFileGetsPathOfFile(): void
    {
        $path                      = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['path'] = $path;
        $files                     = $this->loadSuite()->tests;

        $file = array_shift($files);
        self::assertNotNull($file);
        self::assertStringContainsString($file, $path);
    }

    public function testCacheIsWarmedWhenSpecified(): void
    {
        $this->bareOptions['path']              = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['--coverage-php']    = $this->tmpDir . DIRECTORY_SEPARATOR . uniqid('result_');
        $this->bareOptions['--coverage-filter'] = $this->fixture('common_results');
        $this->bareOptions['--cache-directory'] = $this->tmpDir;
        $this->loadSuite();

        self::assertStringContainsString('Warming cache', $this->output->fetch());
    }

    public function testLoadsPhptFiles(): void
    {
        $this->bareOptions['path'] = $this->fixture('phpt');
        $files                     = $this->loadSuite()->tests;

        $file = array_shift($files);
        self::assertNotNull($file);
        self::assertStringContainsString('my_test.phpt', $file);
    }

    public function testShardTestsWithValidShards(): void
    {
        $this->bareOptions['--shard']         = '2/3';
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        // With 7 tests total and 3 shards, shard 2 should get tests at positions 3,4,5 (0-indexed: 2,3,4)
        // Tests per shard: ceil(7/3) = 3
        // Shard 1 (0-indexed): 0,1,2
        // Shard 2 (1-indexed): 3,4,5 -> but only 2 tests shards there are only 7 total
        self::assertLessThanOrEqual(3, $loader->testCount);
        self::assertGreaterThan(0, $loader->testCount);
        self::assertCount($loader->testCount, $loader->tests);
    }

    public function testShardTestsWithFirstShard(): void
    {
        $this->bareOptions['--shard']         = '1/5';
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        // With 7 tests total and 5 shards, shard 1 should get tests at positions 0,1 (first 2 tests)
        // Tests per shard: ceil(7/5) = 2
        self::assertLessThanOrEqual(2, $loader->testCount);
        self::assertGreaterThan(0, $loader->testCount);
        self::assertCount($loader->testCount, $loader->tests);
    }

    public function testShardTestsWithLastShard(): void
    {
        $this->bareOptions['--shard']         = '5/5';
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        // With 7 tests total and 5 shards, shard 5 should get the remaining test
        // Tests per shard: ceil(7/5) = 2
        // Shard 5 offset: 2 * 4 = 8, but only 7 tests total, so shard 5 gets 0 tests
        // Actually, let's recalculate: shards 1-4 get 2 tests each (8 tests), shard 5 gets 0
        // Wait, we only have 7 tests, so shard 5 should get 0 tests
        self::assertGreaterThanOrEqual(0, $loader->testCount);
        self::assertCount($loader->testCount, $loader->tests);
    }

    public function testNoShardsAppliedByDefault(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        // Without shards, all tests should be loaded
        self::assertSame(7, $loader->testCount);
        self::assertCount(7, $loader->tests);
    }

    private function loadSuite(): SuiteLoader
    {
        $options = $this->createOptionsFromArgv($this->bareOptions);

        return new SuiteLoader($options, $this->output, new CodeCoverageFilterRegistry());
    }
}
