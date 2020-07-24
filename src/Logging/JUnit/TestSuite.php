<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use SimpleXMLElement;

/**
 * A simple data structure for tracking
 * data associated with a testsuite node
 * in a JUnit xml document
 */
class TestSuite
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
    public $skipped;

    /** @var float */
    public $time;

    /** @var string */
    public $file;

    /**
     * Nested suites.
     *
     * @var array
     */
    public $suites = [];

    /**
     * Cases belonging to this suite.
     *
     * @var array
     */
    public $cases = [];

    public function __construct(
        string $name,
        int $tests,
        int $assertions,
        int $failures,
        int $errors,
        int $skipped,
        float $time,
        ?string $file = null
    ) {
        $this->name       = $name;
        $this->tests      = $tests;
        $this->assertions = $assertions;
        $this->failures   = $failures;
        $this->skipped    = $skipped;
        $this->errors     = $errors;
        $this->time       = $time;
        $this->file       = $file;
    }

    /**
     * Create a TestSuite from an associative
     * array.
     *
     * @param array $arr
     *
     * @return TestSuite
     */
    public static function suiteFromArray(array $arr): self
    {
        return new self(
            $arr['name'],
            $arr['tests'],
            $arr['assertions'],
            $arr['failures'],
            $arr['errors'],
            $arr['skipped'],
            $arr['time'],
            $arr['file']
        );
    }

    /**
     * Create a TestSuite from a SimpleXMLElement.
     *
     * @return TestSuite
     */
    public static function suiteFromNode(SimpleXMLElement $node): self
    {
        return new self(
            (string) $node['name'],
            (int) $node['tests'],
            (int) $node['assertions'],
            (int) $node['failures'],
            (int) $node['errors'],
            (int) $node['skipped'],
            (float) $node['time'],
            (string) $node['file']
        );
    }
}
