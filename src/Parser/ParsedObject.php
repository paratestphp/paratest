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

    public function __construct($doc, $name)
    {
        $this->docBlock = $doc;
        $this->name = $name;
    }

    /**
     * Get the name of a parsed object.
     *
     * @return string
     */
    public function getName()
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
    public function hasAnnotation($anno, $value = null)
    {
        $pattern = sprintf(
            '/@%s%s/',
            $anno,
            null !== $value ? "[\s]+$value" : '\b'
        );

        return false !== $this->docBlock && 1 === preg_match($pattern, $this->docBlock);
    }
}
