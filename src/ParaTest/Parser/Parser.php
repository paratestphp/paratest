<?php namespace ParaTest\Parser;

class Parser
{
    private $path;
    private $src;
    private $refl;

    private static $namespace = '/\bnamespace\b[\s]+([^;]+);/';
    private static $class = '/\bclass\b/';
    private static $className = '/\bclass\b\s+([^\s]+)\s+extends/Usi';
    private static $testName = '/^test/';
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

    private function getClassName()
    {
        $class = str_replace('.php', '', basename($this->path));
        $class = $this->parseClassName($class);
        $namespace = $this->getNamespace();
        if($namespace)
            $class = $namespace . '\\' . $class;
        return $class;
    }

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