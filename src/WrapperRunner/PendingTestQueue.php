<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use function array_shift;
use function count;

/** @internal */
final class PendingTestQueue
{
    /** @param list<non-empty-string> $pending */
    public function __construct(
        private array $pending,
    ) {
    }

    /** @param list<non-empty-string> $tests */
    public static function fromTests(array $tests): self
    {
        return new self($tests);
    }

    public static function fromSuite(Suite $suite): self
    {
        return new self($suite->getTests());
    }

    public function clear(): void
    {
        $this->pending = [];
    }

    public function empty(): bool
    {
        return count($this->pending) === 0;
    }

    /** @return non-empty-string|null */
    public function dequeue(): ?string
    {
        return array_shift($this->pending);
    }
}
