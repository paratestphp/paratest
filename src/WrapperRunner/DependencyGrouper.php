<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * PHPUnit checks the dependencies of a test against the tests that already passed in the same process:
 * the work items holding tests that depend on each other must therefore run in the same worker.
 *
 * @internal
 */
final class DependencyGrouper
{
    /** @var array<non-empty-string, non-empty-string> */
    private array $parents = [];

    /**
     * @param iterable<non-empty-string, Test> $testsByItem Each test keyed by the work item that runs it,
     *                                                     an item can hold multiple tests
     *
     * @return array<non-empty-string, non-empty-string> Each work item mapped to the work item representing its group
     */
    public static function group(iterable $testsByItem): array
    {
        $grouper = new self();

        $itemsByTarget = [];
        $requirements  = [];
        foreach ($testsByItem as $item => $test) {
            $grouper->parents[$item] = $item;

            if (! $test instanceof TestCase) {
                continue;
            }

            $itemsByTarget[$test::class . '::' . $test->name()][$item] = $item;
            $itemsByTarget[$test::class . '::class'][$item]            = $item;

            foreach ($test->requires() as $dependency) {
                $requirements[$item][] = $dependency->getTarget();
            }
        }

        foreach ($requirements as $item => $targets) {
            foreach ($targets as $target) {
                foreach ($itemsByTarget[$target] ?? [] as $providingItem) {
                    $grouper->union($item, $providingItem);
                }
            }
        }

        $groups = [];
        foreach (array_keys($grouper->parents) as $item) {
            $groups[$item] = $grouper->find($item);
        }

        return $groups;
    }

    /**
     * @param non-empty-string $item
     *
     * @return non-empty-string
     */
    private function find(string $item): string
    {
        while ($this->parents[$item] !== $item) {
            $item = $this->parents[$item];
        }

        return $item;
    }

    /**
     * @param non-empty-string $item
     * @param non-empty-string $otherItem
     */
    private function union(string $item, string $otherItem): void
    {
        $this->parents[$this->find($item)] = $this->find($otherItem);
    }
}
