<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use function preg_match;
use function sprintf;

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

    /**
     * Returns whether or not the parsed object
     * has an annotation matching the name and value
     * if provided.
     */
    final public function hasAnnotation(string $annotation, ?string $value = null): bool
    {
        $pattern = sprintf(
            '/@%s%s/',
            $annotation,
            $value !== null ? "[\\s]+{$value}" : '\b'
        );

        return preg_match($pattern, $this->docBlock) === 1;
    }
}
