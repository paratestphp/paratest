<?php namespace ParaTest\Parser;

class ParsedFunction extends ParsedObject
{
    private $isAbstract;
    private $visibility;

    public function __construct($doc, $isAbstract, $visibility, $name)
    {
        parent::__construct($doc, $name);
        $this->isAbstract = $isAbstract;
        $this->visibility = $visibility;
    }
}