<?php

declare(strict_types=1);

namespace ParaTest\Logging;

/** @internal */
interface MetaProviderInterface
{
    public function getTotalTests(): int;

    public function getTotalAssertions(): int;

    public function getTotalErrors(): int;

    public function getTotalWarnings(): int;

    public function getTotalFailures(): int;

    public function getTotalSkipped(): int;

    public function getTotalTime(): float;

    /** @return string[] */
    public function getErrors(): array;

    /** @return string[] */
    public function getWarnings(): array;

    /** @return string[] */
    public function getFailures(): array;

    /** @return string[] */
    public function getRisky(): array;

    /** @return string[] */
    public function getSkipped(): array;
}
