<?php namespace ParaTest\Parser;

class Parser
{
    private $path;
    private $refl;

    private static $namespace = '/\bnamespace\b[\s]+([^;]+);/';
    private static $class = '/\bclass\b/';
    private static $testName = '/^test/';
    private static $testAnnotation = '/@test\b/';

    public function __construct($srcPath)
    {
        if(!file_exists($srcPath))
            throw new \InvalidArgumentException("file not found");

        $this->path = $srcPath;

        if(!$this->getNamespace()) {
            $this->refl = $this->getReflectionClassFromFile($this->path);
            return;
        }

        $class = $this->getClassName();
        require_once($this->path);
        $this->refl = new \ReflectionClass($class);
    }

    private function getReflectionClassFromFile($path)
    {
        \PHPUnit_Util_Fileloader::checkAndLoad($path);
        $loadedClasses = $this->getPhpClassesInFile($path);
        $testCaseClass = 'PHPUnit_Framework_TestCase';

        foreach ($loadedClasses as $loadedClass) {
            $class = new \ReflectionClass($loadedClass);

            if ($class->isSubclassOf($testCaseClass)) {
                return $class;
            }
        }

        return null;
    }

    private function getPhpClassesInFile($filepath) {
        $php_code = file_get_contents($filepath);
        $classes = $this->getPhpClasses($php_code);
        return $classes;
    }

    private function getPhpClasses($php_code) {
        $classes = array();
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if (   $tokens[$i - 2][0] == T_CLASS
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $tokens[$i][0] == T_STRING) {

                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }

        return $classes;
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
            if(preg_match(self::$class, $line))
                break;
        }
        fclose($handle);
        return '';
    }
}