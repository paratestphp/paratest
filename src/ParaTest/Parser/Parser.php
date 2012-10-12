<?php namespace ParaTest\Parser;

class Parser
{
    private $tokens;

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
        $visibility = array(T_PUBLIC, T_PRIVATE, T_PROTECTED);
        $canPass = array_merge(array(T_ABSTRACT, T_WHITESPACE), $visibility);
        $functions = array();
        while(list( , $token) = each($this->tokens)) {
            //lets look ahead until we find a function
            if($token[0] === T_DOC_COMMENT) {
                $abstract = false;
                $visible = 'public';
                while(list( , $next) = each($this->tokens)) {
                    if(array_search($next[0], $canPass) === false) {
                        if($next[0] === T_FUNCTION) {
                            while(list( , $string) = each($this->tokens)) {
                                if($string[0] === T_STRING) {
                                    $functions[] = new ParsedFunction($token[1], $abstract, $visible, $string[1]);
                                    break;
                                }
                            }
                        }
                        break;
                    } else if ($next[0] === T_ABSTRACT) {
                        $abstract = true;
                    } else if (array_search($next[0], $visibility)) {
                        $visible = $next[1];
                    }
                }
            } else if ($token[0] === T_ABSTRACT || array_search($token[0], $visibility) !== false) {
                $abstract = ($token[0] === T_ABSTRACT); 
                $visible = 'public';
                if(array_search($token[0], $visibility) !== false) $visible = $token[1];
                while(list( , $next) = each($this->tokens)) {
                    if(array_search($next[0], $canPass) === false) {
                        if($next[0] === T_FUNCTION) {
                            while(list( , $string) = each($this->tokens)) {
                                if($string[0] === T_STRING) {
                                    $functions[] = new ParsedFunction('', $abstract, $visible, $string[1]);
                                    break;
                                }
                            }
                        }
                        break;
                    } else if (array_search($next[0], $visibility)) {
                        $visible = $next[1];
                    }
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