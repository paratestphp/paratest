<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

/**
 * Class Suite.
 *
 * A suite represents an entire PHPUnit Test Suite
 * object - this class is essentially used for running
 * entire test classes in parallel
 */
class Suite extends ExecutableTest
{
    /**
     * A collection of test methods.
     *
     * @var array
     */
    private $functions;

    public function __construct(string $path, array $functions)
    {
        parent::__construct($path);
        $this->functions = $functions;
    }

    /**
     * Return the collection of test methods.
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Get the expected count of tests to be executed.
     *
     * @return int
     */
    public function getTestCount(): int
    {
        return \count($this->functions);
    }
}
