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

    public function getMethods()
    {
        return $this->methods;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }
}