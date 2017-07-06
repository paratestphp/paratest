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
     * @param string $type
     * @param string $text
     */
    public function addFailure(string $type, string $text)
    {
        $this->addDefect('failures', $type, $text);
    }

    /**
     * @param string $type
     * @param string $text
     */
    public function addError(string $type, string $text)
    {
        $this->addDefect('errors', $type, $text);
    }

    /**
     * @param string $type
     * @param string $text
     */
    public function addSkipped(string $type, string $text)
    {
        $this->addDefect('skipped', $type, $text);
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
            'text' => trim($text),
        ];
    }

    /**
     * Add systemOut result on test (if has failed or have error).
     *
     * @param mixed $node
     *
     * @return mixed
     */
    public static function addSystemOut(\SimpleXMLElement $node): \SimpleXMLElement
    {
        $sys = 'system-out';

        if (!empty($node->failure)) {
            $node->failure = (string) $node->failure . (string) $node->{$sys};
        }

        if (!empty($node->error)) {
            $node->error = (string) $node->error . (string) $node->{$sys};
        }

        return $node;
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

        $node = self::addSystemOut($node);
        $failures = $node->xpath('failure');
        $skipped = $node->xpath('skipped');
        $errors = $node->xpath('error');

        foreach ($failures as $fail) {
            $case->addFailure((string) $fail['type'], (string) $fail);
        }

        foreach ($errors as $err) {
            $case->addError((string) $err['type'], (string) $err);
        }

        foreach ($skipped as $skip) {
            $case->addSkipped((string) $skip['type'], (string) $skip);
        }

        return $case;
    }
}
