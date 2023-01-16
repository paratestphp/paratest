<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/**
 * A simple data structure for tracking
 * data associated with a testsuite node
 * in a JUnit xml document
 *
 * @internal
 */
final class TestSuite
{
    /**
     * Nested suites.
     *
     * @var array<string, TestSuite>
     */
    public array $suites = [];

    /**
     * Cases belonging to this suite.
     *
     * @var TestCase[]
     */
    public array $cases = [];

    /**
     * @param array<string, TestSuite> $suites
     * @param TestCase[]               $cases
     */
    public function __construct(
        public string $name,
        public int $tests,
        public int $assertions,
        public int $failures,
        public int $errors,
        public int $warnings,
        public int $risky,
        public int $skipped,
        public float $time,
        public string $file,
        array $suites,
        array $cases
    ) {
        $this->suites = $suites;
        $this->cases  = $cases;
    }

    public static function empty(): self
    {
        return new self(
            '',
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            0.0,
            '',
            [],
            [],
        );
    }
}
