<?php

declare(strict_types=1);

namespace ParaTest\Parser;

abstract class ParsedObject
{
    /**
     * @var string
     */
    protected $docBlock;

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $doc, string $name)
    {
        $this->docBlock = $doc;
        $this->name = $name;
    }

    /**
     * Get the name of a parsed object.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the doc block comments of a parsed object.
     *
     * @return string
     */
    public function getDocBlock()
    {
        return $this->docBlock;
    }

    /**
     * Returns whether or not the parsed object
     * has an annotation matching the name and value
     * if provided.
     *
     * @param string $anno
     * @param mixed  $value
     *
     * @return bool
     */
    public function hasAnnotation(string $annotation, string $value = null): bool
    {
        $pattern = \sprintf(
            '/@%s%s/',
            $annotation,
            null !== $value ? "[\s]+$value" : '\b'
        );

        return 1 === \preg_match($pattern, $this->docBlock);
    }
}
