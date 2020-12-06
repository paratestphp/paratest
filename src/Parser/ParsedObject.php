<?php

declare(strict_types=1);

namespace ParaTest\Parser;

abstract class ParsedObject
{
    /** @var string */
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the name of a parsed object.
     */
    final public function getName(): string
    {
        return $this->name;
    }
}
