<?php namespace ParaTest\Parser;

class Parser
{
    private $path;
    private $src;
    private $refl;

    private static $namespace = '/\bnamespace\b[\s]+([^;]+);/';
    private static $class = '/\bclass\b/';

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
            $fn = new ParsedFunction($method->getDocComment(), 'public', $method->getName());
            if(preg_match('/^test/', $fn->getName()) || $fn->hasAnnotation('test'))
                $tests[] = $fn;
        }
        return $tests;
    }

    private function getClassName()
    {
        $class = str_replace('.php', '', basename($this->path));
        $namespace = $this->getNamespace();
        if($namespace)
            $class = $namespace . '\\' . $class;
        return $class;
    }

    private function getNamespace()
    {
        $handle = fopen($this->path, 'r');
        while($line = fgets($handle)) {
            if(preg_match(self::$namespace, $line, $matches))
                return $matches[1];
            if(preg_match(self::$namespace, $line))
                break;
        }
        fclose($handle);
        return '';
    }
}