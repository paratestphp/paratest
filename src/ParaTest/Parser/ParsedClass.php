<?php namespace ParaTest\Parser;

class ParsedClass extends ParsedObject
{
    private $namespace;
    private $functions;

    public function __construct($doc, $name, $namespace, $functions = array())
    {
        parent::__construct($doc, $name);
        $this->namespace = $namespace;
        $this->functions = $functions;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function getNamespace()
    {
        return $this->namespace;
    }
}