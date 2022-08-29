<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use ReflectionMethod;

/** @internal */
final class ParsedClass
{
    /** @var class-string */
    private $name;

    /**
     * A collection of methods belonging
     * to the parsed class.
     *
     * @var ReflectionMethod[]
     */
    private $methods;

    /** @var int */
    private $parentsCount;

    /**
     * @param class-string       $name
     * @param ReflectionMethod[] $methods
     */
    public function __construct(string $name, array $methods, int $parentsCount)
    {
        $this->name         = $name;
        $this->methods      = $methods;
        $this->parentsCount = $parentsCount;
    }

    /**
     * Get the name of a parsed object.
     *
     * @return class-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the methods of this parsed class
     * optionally filtering on annotations present
     * on a method.
     *
     * @return ReflectionMethod[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getParentsCount(): int
    {
        return $this->parentsCount;
    }
}
