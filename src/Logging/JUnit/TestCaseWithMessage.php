<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/**
 * @internal
 *
 * @readonly
 */
abstract class TestCaseWithMessage extends TestCase
{
    public readonly ?string $type;
    public readonly string $text;
    public readonly ?string $systemOutput;

    public function __construct(
        string $name,
        string $class,
        string $file,
        int $line,
        int $assertions,
        float $time,
        ?string $type,
        string $text,
        ?string $systemOutput
    ) {
        parent::__construct($name, $class, $file, $line, $assertions, $time);

        $this->type         = $type;
        $this->text         = $text;
        $this->systemOutput = $systemOutput;
    }

    abstract public function getXmlTagName(): string;
}
