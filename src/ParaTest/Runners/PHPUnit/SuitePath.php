<?php
namespace ParaTest\Runners\PHPUnit;

/**
 * Representation of test suite paths found in phpunit.xml
 */
class SuitePath
{
    const DEFAULT_SUFFIX = 'Test.php';
    
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @var string[]s
     */
    protected $excludedPaths;
    
    public function __construct($path, $excludedPaths, $suffix)
    {
        if ($suffix === null) {
            $suffix = self::DEFAULT_SUFFIX;
        }
        $this->path = $path;
        $this->excludedPaths = $excludedPaths;
        $this->suffix = $suffix;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getExcludedPaths()
    {
        return $this->excludedPaths;
    }

    /**
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix;
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return '|'. preg_quote($this->getSuffix()) . '$|';
    }
}
