<?php

declare(strict_types=1);

namespace ParaTest\Parser;

class ParsedClass extends ParsedObject
{
    /**
     * @var string
     */
    private $namespace;

    /**
     * A collection of methods belonging
     * to the parsed class.
     *
     * @var array
     */
    private $methods;

    public function __construct(string $doc, string $name, string $namespace, array $methods = [])
    {
        parent::__construct($doc, $name);
        $this->namespace = $namespace;
        $this->methods = $methods;
    }

    /**
     * Return the methods of this parsed class
     * optionally filtering on annotations present
     * on a method.
     *
     * @param array $annotations
     *
     * @return array
     */
    public function getMethods(array $annotations = []): array
    {
        $methods = \array_filter($this->methods, function (ParsedFunction $method) use ($annotations): bool {
            foreach ($annotations as $a => $v) {
                foreach (\explode(',', $v) as $subValue) {
                    if ($method->hasAnnotation($a, $subValue)) {
                        return true;
                    }
                }
            }

            return false;
        });

        return $methods ?: $this->methods;
    }

    /**
     * Return the namespace of the parsed class.
     *
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }
}
