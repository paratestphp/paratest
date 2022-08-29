<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function array_merge;

/** @internal */
final class FullSuite extends ExecutableTest
{
    /** @var string */
    private $suiteName;

    public function __construct(string $suiteName, bool $needsCoverage, bool $needsTeamcity, string $tmpDir)
    {
        parent::__construct('', $needsCoverage, $needsTeamcity, $tmpDir);

        $this->suiteName = $suiteName;
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
