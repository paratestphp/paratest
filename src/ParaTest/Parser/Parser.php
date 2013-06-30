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
     * A pattern for matching namespace syntax
     *
     * @var string
     */
    private static $namespace = '/\bnamespace\b[\s]+([^;]+);/';

    /**
     * A pattern for matching class syntax
     *
     * @var string
     */
    private static $class = '/\bclass\b/';

    /**
     * A pattern for matching class syntax and extension
     * defaulting to ungreedy matches, case insensitivity, and
     * dot matches all
     *
     * @var string
     */
    private static $className = '/\bclass\b\s+([^\s]+)\s+extends/Usi';

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
        $class = $this->getClassName();
        require_once($this->path);
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
    private function getClassName()
    {
        $class = str_replace('.php', '', basename($this->path));
        $class = $this->parseClassName($class);
        $namespace = $this->getNamespace();
        if($namespace)
            $class = $namespace . '\\' . $class;
        return $class;
    }

    /**
     * Reads just enough of the source file to
     * get the class name
     *
     * @param $fallbackClassName
     * @return mixed
     */
    private function parseClassName($fallbackClassName)
    {
        $handle = fopen($this->path, 'r');
        while($line = fgets($handle)) {
            if(preg_match(self::$className, $line, $matches))
                return $matches[1];
        }
        fclose($handle);
        return $fallbackClassName;
    }

    /**
     * Reads just enough of the source file to get the namespace
     * of the source file
     *
     * @return string
     */
    private function getNamespace()
    {
        $handle = fopen($this->path, 'r');
        while($line = fgets($handle)) {
            if(preg_match(self::$namespace, $line, $matches))
                return $matches[1];
            if(preg_match(self::$class, $line))
                break;
        }
        fclose($handle);
        return '';
    }
}