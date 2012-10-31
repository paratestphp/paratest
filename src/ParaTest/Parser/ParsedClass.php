<?php namespace ParaTest\Parser;

class ParsedClass extends ParsedObject
{
    private $namespace;
    private $functions;

    public function __construct($doc, $name, $namespace, $methods = array())
    {
        parent::__construct($doc, $name);
        $this->namespace = $namespace;
        $this->methods = $methods;
    }

    public function getMethods($annotations = array())
    {
        $methods = array_filter($this->methods, function($m) use($annotations){
            foreach($annotations as $a => $v)
                return $m->hasAnnotation($a, $v);
        });
        return $methods ? $methods : $this->methods;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }
}