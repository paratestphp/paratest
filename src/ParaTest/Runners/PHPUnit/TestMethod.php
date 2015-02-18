<?php namespace ParaTest\Runners\PHPUnit;

/**
 * Class TestMethod
 *
 * Represents an individual test method
 * used for running ParaTest in functional mode
 *
 * @package ParaTest\Runners\PHPUnit
 */
class TestMethod extends ExecutableTest
{
    /**
     * The path to the test suite that
     * contains this method. This patch
     * is executed using the --filter clause
     *
     * @var string
     */
    protected $path;

    /**
     * A set of filters for test, they are merged into phpunit's --filter option
     *
     * @var string[]
     */
    protected $filters;

    public function __construct($testPath, $filters)
    {
        $this->path = $testPath;
        $this->filters = (array)$filters;
    }

    /**
     * Returns the test method's name
     *
     * @return string
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Returns the test method's filters
     *
     * @return string
     */
    public function getName()
    {
        return implode("|", $this->filters);
    }

    /**
     * Additional processing for options being
     * passed to PHPUnit. This sets up the --filter
     * switch used to run a single PHPUnit test method
     *
     * @param array $options
     * @return array
     */
    protected function prepareOptions($options)
    {
        $re = array_reduce($this->filters, function ($r, $v) {
            return ($r ? $r . "|" : "") . preg_quote($v, "/") . "\$";
        });
        $options['filter'] = "/" . $re . "/";

        return $options;
    }

    /**
     * Get the expected count of tests or testmethods
     * to be executed in this test
     *
     * @return int
     */
    public function getTestMethodCount()
    {
        return count($this->filters);
    }
}
