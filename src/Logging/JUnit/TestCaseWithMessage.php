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
    /** @var string|null */
    public $type;

    /** @var string */
    public $text;

    /** @var string|null */
    public $systemOutput;

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
