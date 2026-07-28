<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Tests\TestBase;
use ParaTest\WrapperRunner\ShardDistribution;
use ParaTest\WrapperRunner\Suite;
use ParaTest\WrapperRunner\SuiteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_key_first;
use function array_map;
use function array_shift;
use function basename;
use function preg_match;
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

        self::assertSame(7, $loader->getTestCount());
        self::assertCount(7, $loader->getTests());
    }

    public function testLoadFileGetsPathOfFile(): void
    {
        $path                      = $this->fixture('common_results' . DIRECTORY_SEPARATOR . 'SuccessTest.php');
        $this->bareOptions['path'] = $path;
        $files                     = $this->loadSuite()->getTests();

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
        $files                     = $this->loadSuite()->getTests();

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
            self::assertSame($expectedFiles, array_map(basename(...), $loader->getTests()));
            self::assertSame($expectedTestCount, $loader->getTestCount());
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
            self::assertSame($expectedFiles, array_map(basename(...), $loader->getTests()));
            self::assertSame($expectedTestCount, $loader->getTestCount());
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

    public function testLoadsAffinityTests(): void
    {
        $this->bareOptions['path'] = $this->fixture('affinity');

        $suite       = $this->loadSuite();
        $perAffinity = $suite->getTestsPerAffinity();
        self::assertCount(3, $perAffinity);
        self::assertSame('A', array_key_first($perAffinity));
        self::assertCount(2, $perAffinity['A']);
    }

    public function testLoadsAffinityTestsFunctional(): void
    {
        $this->bareOptions['path']         = $this->fixture('affinity');
        $this->bareOptions['--functional'] = true;

        $suite       = $this->loadSuite();
        $perAffinity = $suite->getTestsPerAffinity();
        self::assertCount(3, $perAffinity);
        self::assertSame('A', array_key_first($perAffinity));
        self::assertCount(3, $perAffinity['A']);
    }

    /** @return list<string> */
    private function loadSuiteFileNames(): array
    {
        return array_map(basename(...), $this->loadSuite()->getTests());
    }

    /** @return list<string> */
    private function loadSuiteMethodNames(): array
    {
        return array_map(static function (string $test): string {
            $result = preg_match('/\/(\w+)\$/', $test, $matches);
            self::assertSame(1, $result);

            return $matches[1];
        }, $this->loadSuite()->getTests());
    }

    private function loadSuite(): Suite
    {
        $options = $this->createOptionsFromArgv($this->bareOptions);

        return new SuiteLoader($options, new CodeCoverageFilterRegistry())->load($this->output);
    }
}
