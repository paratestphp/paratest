<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use LogicException;

use function array_fill_keys;
use function array_filter;
use function array_find_key;
use function array_keys;
use function array_shift;
use function assert;
use function count;
use function min;
use function strval;

/** @internal */
final class PendingTestQueue
{
    /** @var array<int, string> */
    private array $workerToAffinity = [];
    /** @var array<int> */
    private array $affinityToWorkerCount;

    /** @param array<list<non-empty-string>> $pending */
    public function __construct(
        private array $pending,
    ) {
        $this->pending               = array_filter($this->pending, static fn (array $pending) => count($pending) > 0);
        $this->affinityToWorkerCount = array_fill_keys(array_keys($this->pending), 0);
    }

    /** @param list<non-empty-string> $tests */
    public static function fromTests(array $tests): self
    {
        return new self(['' => $tests]);
    }

    public static function fromSuite(Suite $suite): self
    {
        return new self($suite->getTestsPerAffinity());
    }

    public function clear(): void
    {
        $this->pending               = [];
        $this->affinityToWorkerCount = [];
        $this->workerToAffinity      = [];
    }

    public function empty(): bool
    {
        return count($this->pending) === 0;
    }

    private function assignWorkerToAffinity(int $workerToken): string
    {
        if (count($this->affinityToWorkerCount) === 0) {
            throw new LogicException('Method only expected to be called when there are still tests pending');
        }

        // Returns affinity with the least amount of workers assigned to it.
        $min      = min($this->affinityToWorkerCount);
        $affinity = array_find_key(
            $this->affinityToWorkerCount,
            static fn (int $workerCount) => $workerCount === $min,
        );
        assert($affinity !== null);
        $affinity = strval($affinity);

        $this->workerToAffinity[$workerToken]    = $affinity;
        $this->affinityToWorkerCount[$affinity] += 1;

        return $affinity;
    }

    /** @return non-empty-string|null */
    public function dequeue(int $workerToken): ?string
    {
        if (count($this->pending) === 0) {
            return null;
        }

        $workerAffinity = $this->workerToAffinity[$workerToken] ?? null;
        if ($workerAffinity === null || isset($this->pending[$workerAffinity]) === false) {
            $workerAffinity = $this->assignWorkerToAffinity($workerToken);
        }

        // Will always be non-null, as each entry is a non-empty-list.
        $pending = array_shift($this->pending[$workerAffinity]);
        // Remove the entry if it becomes an empty list.
        if (count($this->pending[$workerAffinity]) === 0) {
            unset($this->pending[$workerAffinity]);
            unset($this->affinityToWorkerCount[$workerAffinity]);
        }

        return $pending;
    }
}
