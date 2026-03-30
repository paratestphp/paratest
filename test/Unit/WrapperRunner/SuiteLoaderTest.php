<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\ShardDistribution;
use ParaTest\WrapperRunner\SuiteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_map;
use function array_shift;
use function basename;
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

    public function testNoShardsAppliedByDefault(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('phpunit-common_results.xml');

        $loader = $this->loadSuite();

        // Without shards, all tests should be loaded
        self::assertSame(7, $loader->testCount);
        self::assertCount(7, $loader->tests);
    }

    /** @return iterable<string, array{string, string, list<list<string>>}> */
    public static function shardDistributionProvider(): iterable
    {
        yield 'sequential, 2 shards' => [
            ShardDistribution::Sequential->value,
            '2',
            [
                ['ErrorTest.php', 'FailureTest.php', 'IncompleteTest.php', 'RiskyTest.php'],
                ['SkippedTest.php', 'SuccessTest.php', 'WarningTest.php'],
            ],
        ];

        yield 'sequential, 3 shards' => [
            ShardDistribution::Sequential->value,
            '3',
            [
                ['ErrorTest.php', 'FailureTest.php', 'IncompleteTest.php'],
                ['RiskyTest.php', 'SkippedTest.php', 'SuccessTest.php'],
                ['WarningTest.php'],
            ],
        ];

        yield 'sequential, 5 shards' => [
            ShardDistribution::Sequential->value,
            '5',
            [
                ['ErrorTest.php', 'FailureTest.php'],
                ['IncompleteTest.php', 'RiskyTest.php'],
                ['SkippedTest.php', 'SuccessTest.php'],
                ['WarningTest.php'],
                [],
            ],
        ];

        yield 'round-robin, 2 shards' => [
            ShardDistribution::RoundRobin->value,
            '2',
            [
                ['ErrorTest.php', 'IncompleteTest.php', 'SkippedTest.php', 'WarningTest.php'],
                ['FailureTest.php', 'RiskyTest.php', 'SuccessTest.php'],
            ],
        ];

        yield 'round-robin, 3 shards' => [
            ShardDistribution::RoundRobin->value,
            '3',
            [
                ['ErrorTest.php', 'RiskyTest.php', 'WarningTest.php'],
                ['FailureTest.php', 'SkippedTest.php'],
                ['IncompleteTest.php', 'SuccessTest.php'],
            ],
        ];

        yield 'round-robin, 5 shards' => [
            ShardDistribution::RoundRobin->value,
            '5',
            [
                ['ErrorTest.php', 'SuccessTest.php'],
                ['FailureTest.php', 'WarningTest.php'],
                ['IncompleteTest.php'],
                ['RiskyTest.php'],
                ['SkippedTest.php'],
            ],
        ];
    }

    /**
     * @param non-empty-string   $distribution
     * @param non-empty-string   $totalShards
     * @param list<list<string>> $expectedPerShard
     */
    #[DataProvider('shardDistributionProvider')]
    public function testShardDistribution(string $distribution, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('phpunit-common_results.xml');
        $this->bareOptions['--shard-test-distribution'] = $distribution;

        foreach ($expectedPerShard as $index => $expected) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            self::assertSame($expected, $this->loadSuiteFileNames());
        }
    }

    /** @return iterable<string, array{non-empty-string, string, list<list<string>>}> */
    public static function randomShardDistributionProvider(): iterable
    {
        yield '2 shards, seed 42' => [
            '42',
            '2',
            [
                ['RiskyTest.php', 'ErrorTest.php', 'WarningTest.php', 'FailureTest.php'],
                ['SkippedTest.php', 'IncompleteTest.php', 'SuccessTest.php'],
            ],
        ];

        yield '3 shards, seed 42' => [
            '42',
            '3',
            [
                ['RiskyTest.php', 'IncompleteTest.php', 'FailureTest.php'],
                ['SkippedTest.php', 'WarningTest.php'],
                ['ErrorTest.php', 'SuccessTest.php'],
            ],
        ];

        yield '5 shards, seed 42' => [
            '42',
            '5',
            [
                ['RiskyTest.php', 'SuccessTest.php'],
                ['SkippedTest.php', 'FailureTest.php'],
                ['ErrorTest.php'],
                ['IncompleteTest.php'],
                ['WarningTest.php'],
            ],
        ];
    }

    /**
     * @param non-empty-string   $seed
     * @param non-empty-string   $totalShards
     * @param list<list<string>> $expectedPerShard
     */
    #[DataProvider('randomShardDistributionProvider')]
    public function testRandomShardDistribution(string $seed, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']                = $this->fixture('phpunit-common_results.xml');
        $this->bareOptions['--shard-test-distribution']      = ShardDistribution::Random->value;
        $this->bareOptions['--shard-test-distribution-seed'] = $seed;

        foreach ($expectedPerShard as $index => $expected) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            self::assertSame($expected, $this->loadSuiteFileNames());
        }
    }

    public function testRandomShardDistributionWithDefaultSeed(): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('phpunit-common_results.xml');
        $this->bareOptions['--shard-test-distribution'] = ShardDistribution::Random->value;
        $this->bareOptions['--shard']                   = '1/2';

        self::assertSame(
            ['WarningTest.php', 'FailureTest.php', 'SuccessTest.php', 'SkippedTest.php'],
            $this->loadSuiteFileNames(),
        );
    }

    public function testRandomShardDistributionIsDeterministicWithSameSeed(): void
    {
        $this->bareOptions['--configuration']                = $this->fixture('phpunit-common_results.xml');
        $this->bareOptions['--shard-test-distribution']      = ShardDistribution::Random->value;
        $this->bareOptions['--shard-test-distribution-seed'] = '99';
        $this->bareOptions['--shard']                        = '1/2';

        $firstRun  = $this->loadSuiteFileNames();
        $secondRun = $this->loadSuiteFileNames();

        self::assertSame($firstRun, $secondRun);
    }

    /** @return list<string> */
    private function loadSuiteFileNames(): array
    {
        return array_map(basename(...), $this->loadSuite()->tests);
    }

    private function loadSuite(): SuiteLoader
    {
        $options = $this->createOptionsFromArgv($this->bareOptions);

        return new SuiteLoader($options, $this->output, new CodeCoverageFilterRegistry());
    }
}
