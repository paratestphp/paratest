<?php namespace ParaTest\Parser;

abstract class ParsedObject
{
    protected $docBlock;
    protected $name;

    public function __construct($doc, $name)
    {
        $this->docBlock = $doc;
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDocBlock()
    {
        return $this->docBlock;
    }
}
