<?php namespace ParaTest\Parser;

class ParsedFunction extends ParsedObject
{
    /**
     * @var string
     */
    private $visibility;

    public function __construct($doc, $visibility, $name)
    {
        parent::__construct($doc, $name);
        $this->visibility = $visibility;
    }

    /**
     * Returns the accessibility level of the parsed
     * method - i.e public, private, protected
     *
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }
}