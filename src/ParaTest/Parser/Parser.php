<?php namespace ParaTest\Parser;

class Parser
{
    private $tokens;

    private static $visibilityTokens = array(T_PUBLIC, T_PRIVATE, T_PROTECTED);

    public function __construct($src)
    {
        if(!file_exists($src))
            throw new \InvalidArgumentException("file not found");

        $this->tokens = token_get_all(file_get_contents($src));
    }

    public function getClassAnnotatedWith($param)
    {
        return $this->parseClassWithAnnotation($param);    
    }

    private function parseClassWithAnnotation($annotation)
    {
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
            $shouldContinue = array_search($token[0], $continueOn) === false;
            if($shouldContinue) continue;
            $doc = ($token[0] === T_DOC_COMMENT) ? $token[1] : ''; 
            $vis = (array_search($token[0], self::$visibilityTokens) !== false) ? $token[1] : ''; 
            while(list( , $next) = each($this->tokens)) {
                if($next[0] === T_FUNCTION) {
                    while(list( , $string) = each($this->tokens)) {
                        if($string[0] === T_STRING) {
                            $functions[] = new ParsedFunction($doc, $vis, $string[1]);
                            break 2;
                        }
                    }
                }

                if(array_search($next[0], self::$visibilityTokens) !== false) {
                    $vis = $next[1];
                }
            }
        }
        return new ParsedClass($docBlock, $name, $functions);
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
}

//Sausage - PHP testing with saucelabs - parallel testing
//test sausage
//testing in parallel and reporting results
//pick a php winner - make it great
//behat