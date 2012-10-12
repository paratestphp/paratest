<?php namespace ParaTest\Parser;

class ParsedFunction
{
    private $docBlock;
    private $isAbstract;
    private $visibility;
    private $name;

    public function __construct($doc, $isAbstract, $visibility, $name)
    {
        $this->docBlock = $doc;
        $this->isAbstract = $isAbstract;
        $this->visibility = $visibility;
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