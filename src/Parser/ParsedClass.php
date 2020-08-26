<?php

declare(strict_types=1);

namespace ParaTest\Parser;

/**
 * @internal
 *
 * @method class-string getName()
 */
final class ParsedClass extends ParsedObject
{
    /** @var class-string */
    protected $name;

    /** @var string */
    private $namespace;

    /**
     * A collection of methods belonging
     * to the parsed class.
     *
     * @var ParsedFunction[]
     */
    private $methods;
    /** @var int */
    private $parentsCount;

    /**
     * @param ParsedFunction[] $methods
     */
    public function __construct(string $doc, string $name, string $namespace, array $methods, int $parentsCount)
    {
        parent::__construct($doc, $name);
        $this->namespace    = $namespace;
        $this->methods      = $methods;
        $this->parentsCount = $parentsCount;
    }

    /**
     * Return the methods of this parsed class
     * optionally filtering on annotations present
     * on a method.
     *
     * @return ParsedFunction[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Return the namespace of the parsed class.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getParentsCount(): int
    {
        return $this->parentsCount;
    }
}
