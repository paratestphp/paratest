<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_merge;

/** @internal */
final class FullSuite extends ExecutableTest
{
    public function __construct(
        private string $suiteName
    )
    {
        parent::__construct('');
    }

    /** @inheritDoc */
    protected function prepareOptions(array $options): array
    {
        return array_merge(
            $options,
            ['testsuite' => $this->suiteName],
        );
    }

    /** @psalm-return 1 */
    public function getTestCount(): int
    {
        return 1; //There is no simple way of knowing this
    }
}
