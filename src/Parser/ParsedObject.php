<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use function preg_match;
use function sprintf;

abstract class ParsedObject
{
    /** @var string */
    protected $docBlock;

    /** @var string */
    protected $name;

    public function __construct(string $doc, string $name)
    {
        $this->docBlock = $doc;
        $this->name     = $name;
    }

    /**
     * Get the name of a parsed object.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the doc block comments of a parsed object.
     */
    public function getDocBlock(): string
    {
        return $this->docBlock;
    }

    /**
     * Returns whether or not the parsed object
     * has an annotation matching the name and value
     * if provided.
     */
    public function hasAnnotation(string $annotation, ?string $value = null): bool
    {
        $pattern = sprintf(
            '/@%s%s/',
            $annotation,
            $value !== null ? "[\s]+$value" : '\b'
        );

        return preg_match($pattern, $this->docBlock) === 1;
    }
}
