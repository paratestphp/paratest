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
    /** @var string */
    public $name;

    /** @var int */
    public $tests;

    /** @var int */
    public $assertions;

    /** @var int */
    public $failures;

    /** @var int */
    public $errors;

    /** @var int */
    public $warnings;

    /** @var int */
    public $risky;

    /** @var int */
    public $skipped;

    /** @var float */
    public $time;

    /** @var string */
    public $file;

    /**
     * Nested suites.
     *
     * @var array<string, TestSuite>
     */
    public $suites = [];

    /**
     * Cases belonging to this suite.
     *
     * @var TestCase[]
     */
    public $cases = [];

    /**
     * @param array<string, TestSuite> $suites
     * @param TestCase[]               $cases
     */
    public function __construct(
        string $name,
        int $tests,
        int $assertions,
        int $failures,
        int $errors,
        int $warnings,
        int $risky,
        int $skipped,
        float $time,
        string $file,
        array $suites,
        array $cases
    ) {
        $this->name       = $name;
        $this->tests      = $tests;
        $this->assertions = $assertions;
        $this->failures   = $failures;
        $this->skipped    = $skipped;
        $this->errors     = $errors;
        $this->warnings   = $warnings;
        $this->time       = $time;
        $this->file       = $file;
        $this->suites     = $suites;
        $this->cases      = $cases;
        $this->risky      = $risky;
    }
}
