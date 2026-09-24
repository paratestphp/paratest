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
use function array_merge;
use function array_shift;
use function array_unique;
use function array_values;
use function basename;
use function explode;
use function preg_match;
use function sort;
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
        $files                     = array_merge(...$this->loadSuite()->tests);

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
        $files                     = array_merge(...$this->loadSuite()->tests);

        $file = array_shift($files);
        self::assertNotNull($file);
        self::assertStringContainsString('my_test.phpt', $file);
    }

    /** @return iterable<string, array{string, string, list<array{list<string>, int}>}> */
    public static function shardDistributionProvider(): iterable
    {
        // Method counts: Alpha=3, Bravo=2, Charlie=1, Delta=2, Echo=1, Foxtrot=3, Golf=2
        yield 'sequential, 2 shards' => [
            ShardDistribution::Sequential->value,
            '2',
            [
                [['AlphaTest.php', 'BravoTest.php', 'CharlieTest.php', 'DeltaTest.php'], 8],
                [['EchoTest.php', 'FoxtrotTest.php', 'GolfTest.php'], 6],
            ],
        ];

        yield 'sequential, 3 shards' => [
            ShardDistribution::Sequential->value,
            '3',
            [
                [['AlphaTest.php', 'BravoTest.php', 'CharlieTest.php'], 6],
                [['DeltaTest.php', 'EchoTest.php', 'FoxtrotTest.php'], 6],
                [['GolfTest.php'], 2],
            ],
        ];

        yield 'sequential, 5 shards' => [
            ShardDistribution::Sequential->value,
            '5',
            [
                [['AlphaTest.php', 'BravoTest.php'], 5],
                [['CharlieTest.php', 'DeltaTest.php'], 3],
                [['EchoTest.php', 'FoxtrotTest.php'], 4],
                [['GolfTest.php'], 2],
                [[], 0],
            ],
        ];

        yield 'round-robin, 2 shards' => [
            ShardDistribution::RoundRobin->value,
            '2',
            [
                [['AlphaTest.php', 'CharlieTest.php', 'EchoTest.php', 'GolfTest.php'], 7],
                [['BravoTest.php', 'DeltaTest.php', 'FoxtrotTest.php'], 7],
            ],
        ];

        yield 'round-robin, 3 shards' => [
            ShardDistribution::RoundRobin->value,
            '3',
            [
                [['AlphaTest.php', 'DeltaTest.php', 'GolfTest.php'], 7],
                [['BravoTest.php', 'EchoTest.php'], 3],
                [['CharlieTest.php', 'FoxtrotTest.php'], 4],
            ],
        ];

        yield 'round-robin, 5 shards' => [
            ShardDistribution::RoundRobin->value,
            '5',
            [
                [['AlphaTest.php', 'FoxtrotTest.php'], 6],
                [['BravoTest.php', 'GolfTest.php'], 4],
                [['CharlieTest.php'], 1],
                [['DeltaTest.php'], 2],
                [['EchoTest.php'], 1],
            ],
        ];
    }

    /**
     * @param non-empty-string               $distribution
     * @param non-empty-string               $totalShards
     * @param list<array{list<string>, int}> $expectedPerShard
     */
    #[DataProvider('shardDistributionProvider')]
    public function testShardDistribution(string $distribution, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution'] = $distribution;

        foreach ($expectedPerShard as $index => [$expectedFiles, $expectedTestCount]) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            $loader                       = $this->loadSuite();
            self::assertSame($expectedFiles, array_map(basename(...), array_merge(...$loader->tests)));
            self::assertSame($expectedTestCount, $loader->testCount);
        }
    }

    /** @return iterable<string, array{non-empty-string, string, list<array{list<string>, int}>}> */
    public static function randomShardDistributionProvider(): iterable
    {
        // Method counts: Alpha=3, Bravo=2, Charlie=1, Delta=2, Echo=1, Foxtrot=3, Golf=2
        yield '2 shards, seed 42' => [
            '42',
            '2',
            [
                [['DeltaTest.php', 'AlphaTest.php', 'GolfTest.php', 'BravoTest.php'], 9],
                [['EchoTest.php', 'CharlieTest.php', 'FoxtrotTest.php'], 5],
            ],
        ];

        yield '3 shards, seed 42' => [
            '42',
            '3',
            [
                [['DeltaTest.php', 'CharlieTest.php', 'BravoTest.php'], 5],
                [['EchoTest.php', 'GolfTest.php'], 3],
                [['AlphaTest.php', 'FoxtrotTest.php'], 6],
            ],
        ];

        yield '5 shards, seed 42' => [
            '42',
            '5',
            [
                [['DeltaTest.php', 'FoxtrotTest.php'], 5],
                [['EchoTest.php', 'BravoTest.php'], 3],
                [['AlphaTest.php'], 3],
                [['CharlieTest.php'], 1],
                [['GolfTest.php'], 2],
            ],
        ];
    }

    /**
     * @param non-empty-string               $seed
     * @param non-empty-string               $totalShards
     * @param list<array{list<string>, int}> $expectedPerShard
     */
    #[DataProvider('randomShardDistributionProvider')]
    public function testRandomShardDistribution(string $seed, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']                = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution']      = ShardDistribution::Random->value;
        $this->bareOptions['--shard-test-distribution-seed'] = $seed;

        foreach ($expectedPerShard as $index => [$expectedFiles, $expectedTestCount]) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            $loader                       = $this->loadSuite();
            self::assertSame($expectedFiles, array_map(basename(...), array_merge(...$loader->tests)));
            self::assertSame($expectedTestCount, $loader->testCount);
        }
    }

    public function testRandomShardDistributionWithDefaultSeed(): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution'] = ShardDistribution::Random->value;
        $this->bareOptions['--shard']                   = '1/2';

        self::assertSame(
            ['GolfTest.php', 'BravoTest.php', 'FoxtrotTest.php', 'EchoTest.php'],
            $this->loadSuiteFileNames(),
        );
    }

    public function testRandomShardDistributionIsDeterministicWithSameSeed(): void
    {
        $this->bareOptions['--configuration']                = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution']      = ShardDistribution::Random->value;
        $this->bareOptions['--shard-test-distribution-seed'] = '99';
        $this->bareOptions['--shard']                        = '1/2';

        $firstRun  = $this->loadSuiteFileNames();
        $secondRun = $this->loadSuiteFileNames();

        self::assertSame($firstRun, $secondRun);
    }

    /** @return iterable<string, array{string, string, list<list<string>>}> */
    public static function functionalShardDistributionProvider(): iterable
    {
        yield 'sequential, 2 shards' => [
            ShardDistribution::Sequential->value,
            '2',
            [
                ['testOne', 'testTwo', 'testThree', 'testFour', 'testFive', 'testSix', 'testSeven'],
                ['testEight', 'testNine', 'testTen', 'testEleven', 'testTwelve', 'testThirteen', 'testFourteen'],
            ],
        ];

        yield 'sequential, 3 shards' => [
            ShardDistribution::Sequential->value,
            '3',
            [
                ['testOne', 'testTwo', 'testThree', 'testFour', 'testFive'],
                ['testSix', 'testSeven', 'testEight', 'testNine', 'testTen'],
                ['testEleven', 'testTwelve', 'testThirteen', 'testFourteen'],
            ],
        ];

        yield 'round-robin, 2 shards' => [
            ShardDistribution::RoundRobin->value,
            '2',
            [
                ['testOne', 'testThree', 'testFive', 'testSeven', 'testNine', 'testEleven', 'testThirteen'],
                ['testTwo', 'testFour', 'testSix', 'testEight', 'testTen', 'testTwelve', 'testFourteen'],
            ],
        ];

        yield 'round-robin, 3 shards' => [
            ShardDistribution::RoundRobin->value,
            '3',
            [
                ['testOne', 'testFour', 'testSeven', 'testTen', 'testThirteen'],
                ['testTwo', 'testFive', 'testEight', 'testEleven', 'testFourteen'],
                ['testThree', 'testSix', 'testNine', 'testTwelve'],
            ],
        ];
    }

    /**
     * @param non-empty-string   $distribution
     * @param non-empty-string   $totalShards
     * @param list<list<string>> $expectedPerShard
     */
    #[DataProvider('functionalShardDistributionProvider')]
    public function testFunctionalShardDistribution(string $distribution, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution'] = $distribution;
        $this->bareOptions['--functional']              = true;

        foreach ($expectedPerShard as $index => $expected) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            self::assertSame($expected, $this->loadSuiteMethodNames());
        }
    }

    /** @return iterable<string, array{non-empty-string, string, list<list<string>>}> */
    public static function functionalRandomShardDistributionProvider(): iterable
    {
        yield '2 shards, seed 42' => [
            '42',
            '2',
            [
                ['testTen', 'testTwo', 'testEleven', 'testSix', 'testThree', 'testFour', 'testFourteen'],
                ['testOne', 'testThirteen', 'testEight', 'testTwelve', 'testSeven', 'testFive', 'testNine'],
            ],
        ];

        yield '3 shards, seed 42' => [
            '42',
            '3',
            [
                ['testTen', 'testThirteen', 'testSix', 'testSeven', 'testFourteen'],
                ['testOne', 'testEleven', 'testTwelve', 'testFour', 'testNine'],
                ['testTwo', 'testEight', 'testThree', 'testFive'],
            ],
        ];
    }

    /**
     * @param non-empty-string   $seed
     * @param non-empty-string   $totalShards
     * @param list<list<string>> $expectedPerShard
     */
    #[DataProvider('functionalRandomShardDistributionProvider')]
    public function testFunctionalRandomShardDistribution(string $seed, string $totalShards, array $expectedPerShard): void
    {
        $this->bareOptions['--configuration']                = $this->fixture('phpunit-multi_method_tests.xml');
        $this->bareOptions['--shard-test-distribution']      = ShardDistribution::Random->value;
        $this->bareOptions['--shard-test-distribution-seed'] = $seed;
        $this->bareOptions['--functional']                   = true;

        foreach ($expectedPerShard as $index => $expected) {
            $this->bareOptions['--shard'] = ($index + 1) . '/' . $totalShards;
            self::assertSame($expected, $this->loadSuiteMethodNames());
        }
    }

    public function testGroupsFilesDependingOnEachOtherInDependencyOrder(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('depends_across_items' . DIRECTORY_SEPARATOR . 'phpunit.xml');

        self::assertSame([
            ['BProducerTest', 'AConsumerTest'],
            ['DClassProducerTest', 'CClassConsumerTest'],
            ['GChainFirstTest', 'FChainSecondTest', 'EChainThirdTest'],
            ['IDataProviderProducerTest', 'HDataProviderConsumerTest'],
            ['JIndependentTest'],
            ['KProducerTest', 'LConsumerTest'],
        ], $this->loadSuiteGroups());
    }

    public function testGroupsTestsDependingOnEachOtherInDependencyOrder(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('depends_across_items' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--functional']    = true;

        self::assertSame([
            ['BProducerTest::testProduce', 'AConsumerTest::testConsume'],
            ['DClassProducerTest::testOne', 'DClassProducerTest::testTwo', 'CClassConsumerTest::testConsume'],
            ['GChainFirstTest::testFirst', 'FChainSecondTest::testSecond', 'EChainThirdTest::testThird'],
            ['IDataProviderProducerTest::testProduce with data set "first"', 'IDataProviderProducerTest::testProduce with data set "second"', 'HDataProviderConsumerTest::testConsume'],
            ['JIndependentTest::testOne'],
            ['JIndependentTest::testTwo'],
            ['KProducerTest::testProduce', 'LConsumerTest::testConsume'],
        ], $this->loadSuiteGroups());
    }

    public function testDependencyExcludedByFilterIsNotGrouped(): void
    {
        $this->bareOptions['--configuration'] = $this->fixture('depends_across_items' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--filter']        = 'AConsumerTest';

        self::assertSame([['AConsumerTest']], $this->loadSuiteGroups());
    }

    /**
     * @param non-empty-string $distribution
     * @param non-empty-string $totalShards
     */
    #[DataProvider('provideShardsOfGroups')]
    public function testShardsKeepGroupsTogether(string $distribution, string $totalShards, bool $functional): void
    {
        $this->bareOptions['--configuration']           = $this->fixture('depends_across_items' . DIRECTORY_SEPARATOR . 'phpunit.xml');
        $this->bareOptions['--shard-test-distribution'] = $distribution;
        if ($functional) {
            $this->bareOptions['--functional'] = true;
        }

        $expectedGroups = $this->loadSuiteGroups();

        $shardedGroups = [];
        for ($shard = 1; $shard <= (int) $totalShards; ++$shard) {
            $this->bareOptions['--shard'] = $shard . '/' . $totalShards;
            $groups                       = $this->loadSuiteGroups();
            self::assertNotSame([], $groups);

            $shardedGroups = array_merge($shardedGroups, $groups);
        }

        // Sharding with --functional flattens the suite: within a group, the order of the data sets
        // of a test can then differ, see the ResolveDependencies stage of PHPUnit
        self::assertSame(self::canonicalize($expectedGroups), self::canonicalize($shardedGroups));
    }

    /**
     * @param list<list<string>> $groups
     *
     * @return list<list<string>>
     */
    private static function canonicalize(array $groups): array
    {
        foreach ($groups as $index => $group) {
            sort($group);
            $groups[$index] = $group;
        }

        sort($groups);

        return $groups;
    }

    /** @return iterable<string, array{non-empty-string, non-empty-string, bool}> */
    public static function provideShardsOfGroups(): iterable
    {
        foreach (ShardDistribution::cases() as $distribution) {
            foreach (['2', '3'] as $totalShards) {
                yield "{$distribution->value}, $totalShards shards" => [$distribution->value, $totalShards, false];
                yield "functional, {$distribution->value}, $totalShards shards" => [$distribution->value, $totalShards, true];
            }
        }
    }

    public function testFunctionalQueuesEachRepeatedOrRetriedTestOnce(): void
    {
        $this->bareOptions['path']         = $this->fixture('repeat_retry');
        $this->bareOptions['--functional'] = true;

        $loader = $this->loadSuite();
        $tests  = array_merge(...$loader->tests);

        self::assertSame(11, $loader->testCount);
        self::assertCount(6, $tests);
        self::assertSame(array_values(array_unique($tests)), $tests);
    }

    /**
     * Groups as test file names, or as test names with --functional
     *
     * @return list<list<string>>
     */
    private function loadSuiteGroups(): array
    {
        return array_map(static fn (array $group): array => array_map(static function (string $workItem): string {
            $parts = explode("\0", $workItem);
            $name  = basename($parts[0], '.php');
            if (isset($parts[1])) {
                self::assertSame(1, preg_match('/^\/::(.+)\$\/$/', $parts[1], $matches));
                $name .= '::' . $matches[1];
            }

            return $name;
        }, $group), $this->loadSuite()->tests);
    }

    /** @return list<string> */
    private function loadSuiteFileNames(): array
    {
        return array_map(basename(...), array_merge(...$this->loadSuite()->tests));
    }

    /** @return list<string> */
    private function loadSuiteMethodNames(): array
    {
        return array_map(static function (string $test): string {
            $result = preg_match('/::(\w+)\$/', $test, $matches);
            self::assertSame(1, $result);

            return $matches[1];
        }, array_merge(...$this->loadSuite()->tests));
    }

    private function loadSuite(): SuiteLoader
    {
        $options = $this->createOptionsFromArgv($this->bareOptions);

        return new SuiteLoader($options, $this->output, new CodeCoverageFilterRegistry());
    }
}
