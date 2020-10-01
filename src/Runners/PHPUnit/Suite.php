<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function count;

/**
 * A suite represents an entire PHPUnit Test Suite
 * object - this class is essentially used for running
 * entire test classes in parallel
 *
 * @internal
 */
final class Suite extends ExecutableTest
{
    /**
     * A collection of test methods.
     *
     * @var TestMethod[]
     */
    private $functions;

    /**
     * @param TestMethod[] $functions
     */
    public function __construct(string $path, array $functions, bool $needsCoverage, bool $needsTeamcity, string $tmpDir)
    {
        parent::__construct($path, $needsCoverage, $needsTeamcity, $tmpDir);
        $this->functions = $functions;
    }

    /**
     * Return the collection of test methods.
     *
     * @return TestMethod[]
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Get the expected count of tests to be executed.
     */
    public function getTestCount(): int
    {
        return count($this->functions);
    }

    /**
     * @inheritDoc
     */
    protected function prepareOptions(array $options): array
    {
        return $options;
    }
}
