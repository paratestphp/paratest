<?php namespace ParaTest;

class Tokenizer
{
    private $tokens;

    public function __construct($src)
    {
        if(!file_exists($src))
            throw new Exception("file not found");

        $this->tokens = token_get_all(file_get_contents($src));
    }

    public function getTokens()
    {
        return $this->tokens;
    }

    public function getFunctions()
    {
        $funcs = array();
        while($func = $this->extract(T_FUNCTION))
            $funcs[] = $func;
        return $funcs;
    }

    public function getClassesAnnotatedWith($param)
    {
        return $this->getAnnotatedWith(T_CLASS, $param);
    }

    public function getFunctionsAnnotatedWith($param)
    {
        return $this->getAnnotatedWith(T_FUNCTION, $param);
    }

    public function getAnnotatedWith($type, $param)
    {
        $types = array();
        while(@(list( , $token) = each($this->tokens)) != null) {
            if($token[0] !== T_DOC_COMMENT || !preg_match("/\@$param\b/", $token[1])) continue;
            if($extracted = $this->extract($type))
                $types[] = $extracted;
        }
        return $types;
    }

    /**
     * Rewinds the internal collection of tokens
     * to the first element.
     */
    public function rewind()
    {
        reset($this->tokens);
    }

    private function extract($type)
    {
        $typeDefined = false;
        while(@(list( , $token) = each($this->tokens)) != null) {
            if($token[0] === $type)
                $typeDefined = true;

            if($typeDefined && $token[0] === T_STRING) return $token[1];
        }
    }
}

//Sausage - PHP testing with saucelabs - parallel testing
//test sausage
//testing in parallel and reporting results
//pick a php winner - make it great
//behat