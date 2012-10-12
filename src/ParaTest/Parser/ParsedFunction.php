<?php namespace ParaTest\Parser;

class ParsedFunction extends ParsedObject
{
    private $visibility;

    public function __construct($doc, $visibility, $name)
    {
        parent::__construct($doc, $name);
        $this->visibility = $visibility;
    }

    public function getVisibility()
    {
        return $this->visibility;
    }
}