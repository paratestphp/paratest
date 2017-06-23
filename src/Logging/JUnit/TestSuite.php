<?php
namespace ParaTest\Logging\JUnit;

/**
 * Class TestSuite
 *
 * A simple data structure for tracking
 * data associated with a testsuite node
 * in a JUnit xml document
 *
 * @package ParaTest\Logging\JUnit
 */
class TestSuite
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $tests;

    /**
     * @var int
     */
    public $assertions;

    /**
     * @var int
     */
    public $failures;

    /**
     * @var int
     */
    public $errors;

    /**
     * @var int
     */
    public $skipped;

    /**
     * @var float
     */
    public $time;

    /**
     * @var string
     */
    public $file;

    /**
     * Nested suites
     *
     * @var array
     */
    public $suites = [];

    /**
     * Cases belonging to this suite
     *
     * @var array
     */
    public $cases = [];

    /**
     * @param string $name
     * @param int $tests
     * @param int $assertions
     * @param int $failures
     * @param int $skipped
     * @param float $time
     * @param string|null $file
     */
    public function __construct(
        $name,
        $tests,
        $assertions,
        $failures,
        $errors,
        $skipped,
        $time,
        $file = null
    ) {
        $this->name = $name;
        $this->tests = $tests;
        $this->assertions = $assertions;
        $this->failures = $failures;
        $this->skipped = $skipped;
        $this->errors = $errors;
        $this->time = $time;
        $this->file = $file;
    }

    /**
     * Create a TestSuite from an associative
     * array
     *
     * @param array $arr
     * @return TestSuite
     */
    public static function suiteFromArray($arr)
    {
        return new TestSuite(
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
     * Create a TestSuite from a SimpleXMLElement
     *
     * @param \SimpleXMLElement $node
     * @return TestSuite
     */
    public static function suiteFromNode(\SimpleXMLElement $node)
    {
        return new TestSuite(
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
