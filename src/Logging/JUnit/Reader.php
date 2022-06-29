<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use InvalidArgumentException;
use ParaTest\Logging\MetaProviderInterface;
use SimpleXMLElement;

use function array_fill;
use function array_map;
use function array_merge;
use function array_sum;
use function assert;
use function count;
use function file_exists;
use function file_get_contents;
use function filesize;
use function unlink;

/**
 * @internal
 */
final class Reader implements MetaProviderInterface
{
    /** @var TestSuite */
    private $suite;

    /** @var string */
    private $logFile;

    public function __construct(string $logFile)
    {
        if (! file_exists($logFile)) {
            throw new InvalidArgumentException("Log file {$logFile} does not exist");
        }

        if (filesize($logFile) === 0) {
            throw new InvalidArgumentException(
                "Log file {$logFile} is empty. This means a PHPUnit process has crashed."
            );
        }

        $this->logFile   = $logFile;
        $logFileContents = file_get_contents($this->logFile);
        assert($logFileContents !== false);

        $node        = new SimpleXMLElement($logFileContents);
        $this->suite = $this->parseTestSuite($node, true);
    }

    private function parseTestSuite(SimpleXMLElement $node, bool $isRootSuite): TestSuite
    {
        if ($isRootSuite) {
            foreach ($node->testsuite as $singleTestSuiteXml) {
                return $this->parseTestSuite($singleTestSuiteXml, false);
            }
        }

        $suites = [];
        foreach ($node->testsuite as $singleTestSuiteXml) {
            $testSuite                = $this->parseTestSuite($singleTestSuiteXml, false);
            $suites[$testSuite->name] = $testSuite;
        }

        $cases = [];
        foreach ($node->testcase as $singleTestCase) {
            $cases[] = TestCase::caseFromNode($singleTestCase);
        }

        $risky  = array_sum(array_map(static function (TestCase $testCase): int {
            return count($testCase->risky);
        }, $cases));
        $risky += array_sum(array_map(static function (TestSuite $testSuite): int {
            return $testSuite->risky;
        }, $suites));

        return new TestSuite(
            (string) $node['name'],
            (int) $node['tests'],
            (int) $node['assertions'],
            (int) $node['failures'],
            (int) $node['errors'],
            (int) $node['warnings'],
            $risky,
            (int) $node['skipped'],
            (float) $node['time'],
            (string) $node['file'],
            $suites,
            $cases,
        );
    }

    public function getSuite(): TestSuite
    {
        return $this->suite;
    }

    /**
     * @return string[]
     * @psalm-return list<string>
     */
    public function getFeedback(): array
    {
        return array_merge(
            array_fill(0, $this->suite->errors, 'E'),
            array_fill(0, $this->suite->warnings, 'W'),
            array_fill(0, $this->suite->failures, 'F'),
            array_fill(0, $this->suite->risky, 'R'),
            array_fill(0, $this->suite->skipped, 'S'),
            array_fill(
                0,
                $this->suite->tests
                - $this->suite->errors
                - $this->suite->warnings
                - $this->suite->failures
                - $this->suite->risky
                - $this->suite->skipped,
                '.'
            ),
        );
    }

    public function removeLog(): void
    {
        unlink($this->logFile);
    }

    public function getTotalTests(): int
    {
        return $this->suite->tests;
    }

    public function getTotalAssertions(): int
    {
        return $this->suite->assertions;
    }

    public function getTotalErrors(): int
    {
        return $this->suite->errors;
    }

    public function getTotalFailures(): int
    {
        return $this->suite->failures;
    }

    public function getTotalWarnings(): int
    {
        return $this->suite->warnings;
    }

    public function getTotalSkipped(): int
    {
        return $this->suite->skipped;
    }

    public function getTotalTime(): float
    {
        return $this->suite->time;
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        return $this->getMessagesOfType($this->suite, static function (TestCase $case): array {
            return $case->errors;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getWarnings(): array
    {
        return $this->getMessagesOfType($this->suite, static function (TestCase $case): array {
            return $case->warnings;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getFailures(): array
    {
        return $this->getMessagesOfType($this->suite, static function (TestCase $case): array {
            return $case->failures;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getRisky(): array
    {
        return $this->getMessagesOfType($this->suite, static function (TestCase $case): array {
            return $case->risky;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getSkipped(): array
    {
        return $this->getMessagesOfType($this->suite, static function (TestCase $case): array {
            return $case->skipped;
        });
    }

    /**
     * @return string[]
     */
    private function getMessagesOfType(TestSuite $testSuite, callable $callback): array
    {
        $messages = [];
        foreach ($testSuite->suites as $suite) {
            $messages = array_merge($messages, $this->getMessagesOfType($suite, $callback));
        }

        foreach ($testSuite->cases as $case) {
            $messages = array_merge($messages, array_map(static function (array $msg): string {
                return $msg['text'];
            }, $callback($case)));
        }

        return $messages;
    }
}
