<?php namespace ParaTest\Runners\PHPUnit;

/**
 * Class Suite
 *
 * A suite represents an entire PHPUnit Test Suite
 * object - this class is essentially used for running
 * entire test classes in parallel
 *
 * @package ParaTest\Runners\PHPUnit
 */
class Suite extends ExecutableTest
{
    /**
     * A collection of test methods
     *
     * @var array
     */
    private $functions;

    public function __construct($path, $functions, $fullyQualifiedClassName = null)
    {
        parent::__construct($path, $fullyQualifiedClassName);
        $this->functions = $functions;
    }

    /**
     * Return the collection of test methods
     *
     * @return array
     */
    public function getFunctions()
    {
        return $this->functions;
    }

    /**
     * Get the expected count of tests to be executed
     *
     * @return int
     */
    public function getTestCount()
    {
        return count($this->functions);
    }
}
