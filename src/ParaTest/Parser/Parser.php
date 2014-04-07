<?php namespace ParaTest\Parser;

class Parser
{
    /**
     * The path to the source file to parse
     *
     * @var string
     */
    private $path;

    /**
     * @var \ReflectionClass
     */
    private $refl;

    /**
     * Matches a test method beginning with the conventional "test"
     * word
     *
     * @var string
     */
    private static $testName = '/^test/';

    /**
     * A pattern for matching test methods that use the @test annotation
     *
     * @var string
     */
    private static $testAnnotation = '/@test\b/';

    public function __construct($srcPath)
    {
        if(!file_exists($srcPath))
            throw new \InvalidArgumentException("file not found");

        $this->path = $srcPath;
        $declaredClasses = get_declared_classes();
        require_once($this->path);
        $class = $this->getClassName($this->path, $declaredClasses);
        $this->refl = new \ReflectionClass($class);
    }

    /**
     * Returns the fully constructed class
     * with methods or null if the class is abstract
     *
     * @return null|ParsedClass
     */
    public function getClass()
    {
        return ($this->refl->isAbstract())
            ? null
            : new ParsedClass(
                $this->refl->getDocComment(),
                $this->refl->getName(),
                $this->refl->getNamespaceName(),
                $this->getMethods());
    }

    /**
     * Return all test methods present in the file
     *
     * @return array
     */
    private function getMethods()
    {
        $tests = array();
        $methods = $this->refl->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach($methods as $method) {
            if(preg_match(self::$testName, $method->getName()) || preg_match(self::$testAnnotation, $method->getDocComment()))
                $tests[] = new ParsedFunction($method->getDocComment(), 'public', $method->getName());
        }
        return $tests;
    }

    /**
     * Return the class name of the class contained
     * in the file
     *
     * @return string
     */
    private function getClassName($filename, $previousDeclaredClasses)
    {
        $filename = realpath($filename);
        $classes = get_declared_classes();
        $newClasses = array_values(array_diff($classes, $previousDeclaredClasses));

        foreach ($newClasses as $className) {
            $class = new \ReflectionClass($className);
            if ($class->getFileName() == $filename) {
                return $className;
            }
        }

        // Test class was loaded before somehow (referenced from other test class, or explicitly loaded)
        foreach ($classes as $className) {
            $class = new \ReflectionClass($className);
            if ($class->getFileName() == $filename) {
                return $className;
            }
        }
    }
}