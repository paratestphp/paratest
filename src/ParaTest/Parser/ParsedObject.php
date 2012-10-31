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

    public function hasAnnotation($anno, $value = null)
    {
        $pattern = sprintf('/@%s%s/', $anno, 
                           !is_null($value) ? "[\s]+$value" : '\b');
        return (bool) preg_match($pattern, $this->docBlock);
    }
}
