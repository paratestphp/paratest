<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use Generator;
use ParaTest\Tests\fixtures\depends_across_items\AConsumerTest;
use ParaTest\Tests\fixtures\depends_across_items\BProducerTest;
use ParaTest\Tests\fixtures\depends_across_items\CClassConsumerTest;
use ParaTest\Tests\fixtures\depends_across_items\DClassProducerTest;
use ParaTest\Tests\fixtures\depends_across_items\EChainThirdTest;
use ParaTest\Tests\fixtures\depends_across_items\FChainSecondTest;
use ParaTest\Tests\fixtures\depends_across_items\GChainFirstTest;
use ParaTest\Tests\fixtures\depends_across_items\HDataProviderConsumerTest;
use ParaTest\Tests\fixtures\depends_across_items\IDataProviderProducerTest;
use ParaTest\Tests\fixtures\depends_across_items\JIndependentTest;
use ParaTest\Tests\fixtures\depends_across_items\KProducerTest;
use ParaTest\Tests\fixtures\depends_across_items\LConsumerTest;
use ParaTest\WrapperRunner\DependencyGrouper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExecutionOrderDependency;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Phpt\TestCase as PhptTestCase;

use function array_values;

/** @internal */
#[CoversClass(DependencyGrouper::class)]
final class DependencyGrouperTest extends TestCase
{
    public function testItemsWithoutDependenciesAreAlone(): void
    {
        self::assertSame([['a'], ['b']], self::partition(self::items([
            ['a', new BProducerTest('testProduce')],
            ['b', new JIndependentTest('testOne')],
        ])));
    }

    public function testItemIsGroupedWithTheItemOfItsDependency(): void
    {
        self::assertSame([['a', 'b'], ['c']], self::partition(self::items([
            ['a', self::dependingOn(new AConsumerTest('testConsume'), new ExecutionOrderDependency(BProducerTest::class, 'testProduce'))],
            ['b', new BProducerTest('testProduce')],
            ['c', new JIndependentTest('testOne')],
        ])));
    }

    public function testDependencyOnAClassGroupsAllTheItemsOfTheClass(): void
    {
        self::assertSame([['a', 'b', 'c'], ['d']], self::partition(self::items([
            ['a', self::dependingOn(new CClassConsumerTest('testConsume'), new ExecutionOrderDependency(DClassProducerTest::class))],
            ['b', new DClassProducerTest('testOne')],
            ['c', new DClassProducerTest('testTwo')],
            ['d', new JIndependentTest('testOne')],
        ])));
    }

    public function testDependencyOnATestWithDataProviderGroupsAllItsDataSets(): void
    {
        $first = new IDataProviderProducerTest('testProduce');
        $first->setData('first', [true]);
        $second = new IDataProviderProducerTest('testProduce');
        $second->setData('second', [true]);

        self::assertSame([['a', 'b', 'c']], self::partition(self::items([
            ['a', self::dependingOn(new HDataProviderConsumerTest('testConsume'), new ExecutionOrderDependency(IDataProviderProducerTest::class, 'testProduce'))],
            ['b', $first],
            ['c', $second],
        ])));
    }

    public function testDependenciesAreTransitive(): void
    {
        self::assertSame([['a', 'b', 'd'], ['c']], self::partition(self::items([
            ['a', self::dependingOn(new EChainThirdTest('testThird'), new ExecutionOrderDependency(FChainSecondTest::class, 'testSecond'))],
            ['b', self::dependingOn(new FChainSecondTest('testSecond'), new ExecutionOrderDependency(GChainFirstTest::class, 'testFirst'))],
            ['c', new JIndependentTest('testOne')],
            ['d', new GChainFirstTest('testFirst')],
        ])));
    }

    public function testItemsDependingOnEachOtherAreGrouped(): void
    {
        self::assertSame([['a', 'b']], self::partition(self::items([
            ['a', self::dependingOn(new KProducerTest('testProduce'), new ExecutionOrderDependency(LConsumerTest::class, 'testConsume'))],
            ['b', self::dependingOn(new LConsumerTest('testConsume'), new ExecutionOrderDependency(KProducerTest::class, 'testProduce'))],
        ])));
    }

    public function testDependenciesOnTestsThatAreNotLoadedAreIgnored(): void
    {
        self::assertSame([['a'], ['b']], self::partition(self::items([
            ['a', self::dependingOn(new AConsumerTest('testConsume'), new ExecutionOrderDependency(BProducerTest::class, 'testProduce'), ExecutionOrderDependency::invalid())],
            ['b', new JIndependentTest('testOne')],
        ])));
    }

    public function testItemHoldingMultipleTestsIsGroupedAsAWhole(): void
    {
        self::assertSame([['a', 'b'], ['c']], self::partition(self::items([
            ['a', new DClassProducerTest('testOne')],
            ['a', new DClassProducerTest('testTwo')],
            ['b', self::dependingOn(new CClassConsumerTest('testConsume'), new ExecutionOrderDependency(DClassProducerTest::class, 'testTwo'))],
            ['c', new JIndependentTest('testOne')],
        ])));
    }

    public function testItemsOfPhptTestsAreAlone(): void
    {
        self::assertSame([['a'], ['b', 'c']], self::partition(self::items([
            ['a', new PhptTestCase('my_test.phpt')],
            ['b', self::dependingOn(new AConsumerTest('testConsume'), new ExecutionOrderDependency(BProducerTest::class, 'testProduce'))],
            ['c', new BProducerTest('testProduce')],
        ])));
    }

    private static function dependingOn(TestCase $test, ExecutionOrderDependency ...$dependencies): TestCase
    {
        $test->setDependencies(array_values($dependencies));

        return $test;
    }

    /**
     * @param list<array{non-empty-string, Test}> $items
     *
     * @return Generator<non-empty-string, Test>
     */
    private static function items(array $items): Generator
    {
        foreach ($items as [$item, $test]) {
            yield $item => $test;
        }
    }

    /**
     * @param iterable<non-empty-string, Test> $testsByItem
     *
     * @return list<list<non-empty-string>> The items of each group, in order of appearance
     */
    private static function partition(iterable $testsByItem): array
    {
        $partition = [];
        foreach (DependencyGrouper::group($testsByItem) as $item => $group) {
            $partition[$group][] = $item;
        }

        return array_values($partition);
    }
}
