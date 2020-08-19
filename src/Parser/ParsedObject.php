<?php

declare(strict_types=1);

namespace ParaTest\Parser;

abstract class ParsedObject
{
    /** @var string */
    protected $name;

    /** @var string */
    private $docBlock;

    public function __construct(string $doc, string $name)
    {
        $this->docBlock = $doc;
        $this->name     = $name;
    }

    /**
     * Get the name of a parsed object.
     */
    final public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the doc block comments of a parsed object.
     */
    final public function getDocBlock(): string
    {
        return $this->docBlock;
    }
}
