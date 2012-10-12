<?php namespace ParaTest\Parser;

class ParsedClass
{
    private $docBlock;
    private $name;
    private $functions;

    public function __construct($doc, $name, $functions = array())
    {
        $this->docBlock = $doc;
        $this->name = $name;
        $this->functions = $functions;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDocBlock()
    {
        return $this->docBlock;
    }

    public function getFunctions()
    {
        return $this->functions;
    }
}