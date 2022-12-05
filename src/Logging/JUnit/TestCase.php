<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use PHPUnit\Framework\RiskyTestError;
use SimpleXMLElement;

use function assert;
use function class_exists;
use function count;
use function current;
use function is_subclass_of;
use function iterator_to_array;
use function sprintf;

/**
 * A simple data structure for tracking
 * the results of a testcase node in a
 * JUnit xml document
 *
 * @internal
 *
 * @readonly
 */
abstract class TestCase
{
    /** @var string */
    public $name;

    /** @var string */
    public $class;

    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var int */
    public $assertions;

    /** @var float */
    public $time;

    public function __construct(
        string $name,
        string $class,
        string $file,
        int $line,
        int $assertions,
        float $time
    ) {
        $this->name       = $name;
        $this->class      = $class;
        $this->file       = $file;
        $this->line       = $line;
        $this->assertions = $assertions;
        $this->time       = $time;
    }

    /**
     * Factory method that creates a TestCase object
     * from a SimpleXMLElement.
     *
     * @return TestCase
     */
    final public static function caseFromNode(SimpleXMLElement $node): self
    {
        $systemOutput  = null;
        $systemOutputs = $node->xpath('system-out');
        if ($systemOutputs !== null && $systemOutputs !== []) {
            assert(count($systemOutputs) === 1);
            $systemOutput = (string) current($systemOutputs);
        }

        $getFirstNode = static function (array $nodes): SimpleXMLElement {
            assert(count($nodes) === 1);
            $node = current($nodes);
            assert($node instanceof SimpleXMLElement);

            return $node;
        };
        $getType      = static function (SimpleXMLElement $node): string {
            $element = $node->attributes();
            assert($element !== null);
            $attributes = iterator_to_array($element);
            assert($attributes !== []);

            return (string) $attributes['type'];
        };

        if (($errors = $node->xpath('error')) !== null && $errors !== []) {
            $error = $getFirstNode($errors);
            $type  = $getType($error);
            $text  = (string) $error;

            if (
                class_exists($type)
                && ($type === RiskyTestError::class || is_subclass_of($type, RiskyTestError::class))
            ) {
                return new RiskyTestCase(
                    (string) $node['name'],
                    (string) $node['class'],
                    (string) $node['file'],
                    (int) $node['line'],
                    (int) $node['assertions'],
                    (float) $node['time'],
                    $type,
                    $text,
                    $systemOutput,
                );
            }

            return new ErrorTestCase(
                (string) $node['name'],
                (string) $node['class'],
                (string) $node['file'],
                (int) $node['line'],
                (int) $node['assertions'],
                (float) $node['time'],
                $type,
                $text,
                $systemOutput,
            );
        }

        if (($warnings = $node->xpath('warning')) !== null && $warnings !== []) {
            $warning = $getFirstNode($warnings);
            $type    = $getType($warning);
            $text    = (string) $warning;

            return new WarningTestCase(
                (string) $node['name'],
                (string) $node['class'],
                (string) $node['file'],
                (int) $node['line'],
                (int) $node['assertions'],
                (float) $node['time'],
                $type,
                $text,
                $systemOutput,
            );
        }

        if (($failures = $node->xpath('failure')) !== null && $failures !== []) {
            $failure = $getFirstNode($failures);
            $type    = $getType($failure);
            $text    = (string) $failure;

            return new FailureTestCase(
                (string) $node['name'],
                (string) $node['class'],
                (string) $node['file'],
                (int) $node['line'],
                (int) $node['assertions'],
                (float) $node['time'],
                $type,
                $text,
                $systemOutput,
            );
        }

        if ($node->xpath('skipped') !== []) {
            $text = (string) $node['name'];
            if ((string) $node['class'] !== '') {
                $text = sprintf(
                    "%s::%s\n\n%s:%s",
                    $node['class'],
                    $node['name'],
                    $node['file'],
                    $node['line'],
                );
            }

            return new SkippedTestCase(
                (string) $node['name'],
                (string) $node['class'],
                (string) $node['file'],
                (int) $node['line'],
                (int) $node['assertions'],
                (float) $node['time'],
                null,
                $text,
                $systemOutput,
            );
        }

        return new SuccessTestCase(
            (string) $node['name'],
            (string) $node['class'],
            (string) $node['file'],
            (int) $node['line'],
            (int) $node['assertions'],
            (float) $node['time'],
        );
    }
}
