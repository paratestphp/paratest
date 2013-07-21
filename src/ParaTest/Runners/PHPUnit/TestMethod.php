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
     * The name of the method that will be supplied
     * to the --filter switch
     *
     * @var string
     */
    protected $name;

    public function __construct($suitePath, $name)
    {
        $this->path = $suitePath;
        $this->name = $name;
    }

    /**
     * Returns the test method's name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
        $options['filter'] = sprintf("'/\b%s\b/'", $this->name);

        return $options;
    }
}
