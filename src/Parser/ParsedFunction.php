<?php

declare(strict_types=1);

namespace ParaTest\Parser;

class ParsedFunction extends ParsedObject
{
    /**
     * @var string
     */
    private $visibility;

    public function __construct(string $doc, string $visibility, string $name)
    {
        parent::__construct($doc, $name);
        $this->visibility = $visibility;
    }

    /**
     * Returns the accessibility level of the parsed
     * method - i.e public, private, protected.
     *
     * @return string
     */
    public function getVisibility(): string
    {
        return $this->visibility;
    }
}
