<?php namespace ParaTest\Parser;

class ParsedClass extends ParsedObject
{
    private $functions;

    public function __construct($doc, $name, $functions = array())
    {
        parent::__construct($doc, $name);
        $this->functions = $functions;
    }

    public function getFunctions()
    {
        return $this->functions;
    }
}