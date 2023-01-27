<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use SimpleXMLElement;

use function assert;
use function count;
use function current;
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
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly string $file,
        public readonly int $line,
        public readonly int $assertions,
        public readonly float $time
    ) {
    }

    /**
     * Factory method that creates a TestCase object
     * from a SimpleXMLElement.
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

            if ($type === 'PHPUnit\\Framework\\RiskyTest') {
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
