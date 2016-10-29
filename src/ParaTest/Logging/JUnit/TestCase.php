<?php
namespace ParaTest\Logging\JUnit;

/**
 * Class TestCase
 *
 * A simple data structure for tracking
 * the results of a testcase node in a
 * JUnit xml document
 *
 * @package ParaTest\Logging\JUnit
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
     * @var string
     */
    public $line;

    /**
     * @var int
     */
    public $assertions;

    /**
     * @var string
     */
    public $time;

    /**
     * Number of failures in this test case
     *
     * @var array
     */
    public $failures = array();

    /**
     * Number of errors in this test case
     *
     * @var array
     */
    public $errors = array();

    public function __construct(
        $name,
        $class,
        $file,
        $line,
        $assertions,
        $time
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
    public function addFailure($type, $text)
    {
        $this->addDefect('failures', $type, $text);
    }

    /**
     * @param string $type
     * @param string $text
     */
    public function addError($type, $text)
    {
        $this->addDefect('errors', $type, $text);
    }

    /**
     * Add a defect type (error or failure)
     *
     * @param string $collName the name of the collection to add to
     * @param $type
     * @param $text
     */
    protected function addDefect($collName, $type, $text)
    {
        $this->{$collName}[] = array(
            'type' => $type,
            'text' => trim($text)
        );
    }

    /**
     * Add systemOut result on test (if has failed or have error)
     *
     * @param mixed $node
     * @return mixed
     */
    public static function addSystemOut($node)
    {
        $sys = 'system-out';

        if(!empty($node->failure)) {
            $node->failure = (string)$node->failure . (string)$node->{$sys};
        }

        if(!empty($node->error)) {
            $node->error = (string)$node->error . (string)$node->{$sys};
        }

        return $node;
    }

    /**
     * Factory method that creates a TestCase object
     * from a SimpleXMLElement
     *
     * @param \SimpleXMLElement $node
     * @return TestCase
     */
    public static function caseFromNode(\SimpleXMLElement $node)
    {
        $case = new TestCase(
            (string) $node['name'],
            (string) $node['class'],
            (string) $node['file'],
            (string) $node['line'],
            (string) $node['assertions'],
            (string) $node['time']
        );

        $node       = self::addSystemOut($node);
        $failures   = $node->xpath('failure');
        $errors     = $node->xpath('error');

        while (list( , $fail) = each($failures)) {
            $case->addFailure((string)$fail['type'], (string)$fail);
        }

        while (list( , $err) = each($errors)) {
            $case->addError((string)$err['type'], (string)$err);
        }

        return $case;
    }
}
