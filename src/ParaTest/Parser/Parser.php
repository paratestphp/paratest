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
        return new ParsedClass(
            $this->refl->getDocComment(), 
            $this->refl->getName(),
            $this->refl->getNamespaceName());
        //return $this->buildParsedClass('', $this->extractClass());
    }

    public function getClassAnnotatedWith($param)
    {
        return $this->parseClassWithAnnotation($param);    
    }

    private function parseClassWithAnnotation($annotation)
    {
        $class = null;
        while(@(list( , $token) = each($this->tokens)) != null) {
            if($token[0] !== T_DOC_COMMENT || !preg_match("/\@$annotation\b/", $token[1])) continue;
            if($extracted = $this->extractClass()) 
                $class = $this->buildParsedClass($token[1], $extracted);
        }
        return $class;
    }

    private function buildParsedClass($docBlock, $name)
    {
        $continueOn = $tokens = array_merge(array(T_DOC_COMMENT), self::$visibilityTokens);
        $functions = array();
        while(list( , $token) = each($this->tokens)) {
            if(array_search($token[0], $continueOn) === false) continue;
            $doc = ($token[0] === T_DOC_COMMENT) ? $token[1] : null; 
            $vis = (array_search($token[0], self::$visibilityTokens) !== false) ? $token[1] : ''; 
            while(list( , $next) = each($this->tokens)) {
                if($next[0] === T_FUNCTION && $functions[] = $this->extractFunction($doc, $vis)) break;
                if(array_search($next[0], self::$visibilityTokens) !== false) $vis = $next[1];
            }
        }
        return new ParsedClass($docBlock, $name, $this->getNamespace(), $functions);
    }

    private function extractFunction($doc, $vis)
    {
        while(list( , $string) = each($this->tokens)) {
            if($string[0] === T_STRING)
                return new ParsedFunction($doc, $vis, $string[1]);
        }
    }

    private function extractClass()
    {
        $classDefined = false;
        while(@(list( , $token) = each($this->tokens)) != null) {
            if($token[0] === T_CLASS)
                $classDefined = true;

            if($classDefined && $token[0] === T_STRING) return $token[1];
        }
    }

    public function getClassName()
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