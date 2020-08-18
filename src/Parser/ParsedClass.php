<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use function array_filter;
use function count;

/**
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

    /**
     * @param ParsedFunction[] $methods
     */
    public function __construct(string $doc, string $name, string $namespace, array $methods)
    {
        parent::__construct($doc, $name);
        $this->namespace = $namespace;
        $this->methods   = $methods;
    }

    /**
     * Return the methods of this parsed class
     * optionally filtering on annotations present
     * on a method.
     *
     * @param string[] $groups
     *
     * @return ParsedFunction[]
     */
    public function getMethods(array $groups): array
    {
        if (count($groups) === 0) {
            return $this->methods;
        }

        $groupAnnotation = 'group';
        foreach ($groups as $group) {
            if ($this->hasAnnotation($groupAnnotation, $group)) {
                return $this->methods;
            }
        }

        return array_filter($this->methods, static function (ParsedFunction $method) use ($groupAnnotation, $groups): bool {
            foreach ($groups as $group) {
                if ($method->hasAnnotation($groupAnnotation, $group)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Return the namespace of the parsed class.
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
