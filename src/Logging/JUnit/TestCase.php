<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/**
 * Class TestCase.
 *
 * A simple data structure for tracking
 * the results of a testcase node in a
 * JUnit xml document
 */
class TestCase
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    public $file;

    /**
     * @var int
     */
    public $line;

    /**
     * @var int
     */
    public $assertions;

    /**
     * @var string|float (a stringified float, from phpunit XML output)
     */
    public $time;

    /**
     * List of failures in this test case.
     *
     * @var array
     */
    public $failures = [];

    /**
     * List of errors in this test case.
     *
     * @var array
     */
    public $errors = [];

    /**
     * List of warnings in this test case.
     *
     * @var array
     */
    public $warnings = [];

    /** @var array */
    public $skipped = [];

    /**
     * @param string $name
     * @param string $class
     * @param string $file
     * @param int    $line
     * @param int    $assertions
     * @param string $time
     */
    public function __construct(
        string $name,
        string $class,
        string $file,
        int $line,
        int $assertions,
        string $time
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->file = $file;
        $this->line = $line;
        $this->assertions = $assertions;
        $this->time = $time;
    }

    /**
     * Add a defect type (error or failure).
     *
     * @param string $collName the name of the collection to add to
     * @param $type
     * @param $text
     */
    protected function addDefect(string $collName, string $type, string $text)
    {
        $this->{$collName}[] = [
            'type' => $type,
            'text' => \trim($text),
        ];
    }

    /**
     * Factory method that creates a TestCase object
     * from a SimpleXMLElement.
     *
     * @param \SimpleXMLElement $node
     *
     * @return TestCase
     */
    public static function caseFromNode(\SimpleXMLElement $node): self
    {
        $case = new self(
            (string) $node['name'],
            (string) $node['class'],
            (string) $node['file'],
            (int) $node['line'],
            (int) $node['assertions'],
            (string) $node['time']
        );

        $system_output = $node->{'system-out'};
        $defect_groups = [
            'failures' => (array) $node->xpath('failure'),
            'errors' => (array) $node->xpath('error'),
            'warnings' => (array) $node->xpath('warning'),
            'skipped' => (array) $node->xpath('skipped'),
        ];

        foreach ($defect_groups as $group => $defects) {
            foreach ($defects as $defect) {
                $message = (string) $defect;
                $message .= (string) $system_output;
                $case->addDefect($group, (string) $defect['type'], $message);
            }
        }

        return $case;
    }
}
