<?php

declare(strict_types=1);

namespace ParaTest;

/**
 * Interface that can be implemented on phpunit test classes.
 * Tests which share an affinity (any string) are more likely to be scheduled on the same worker process.
 * This can improve performance when in-memory test setup or exclusive locks are used.
 * This affinity is only a hint, and not a guarantee.
 */
interface AffinityAware
{
    public function getAffinity(): string|null;
}
