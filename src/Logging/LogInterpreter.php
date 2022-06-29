<?php

declare(strict_types=1);

namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestCase;
use ParaTest\Logging\JUnit\TestSuite;

use function array_merge;
use function array_reduce;
use function array_values;
use function count;

/**
 * @internal
 */
final class LogInterpreter implements MetaProviderInterface
{
    /**
     * A collection of Reader objects
     * to aggregate results from.
     *
     * @var Reader[]
     */
    private $readers = [];

    /**
     * Add a new Reader to be included
     * in the final results.
     */
    public function addReader(Reader $reader): void
    {
        $this->readers[] = $reader;
    }

    /**
     * Return all Reader objects associated
     * with the LogInterpreter.
     *
     * @return Reader[]
     */
    public function getReaders(): array
    {
        return $this->readers;
    }

    /**
     * Returns true if total errors and failures
     * equals 0, false otherwise
     * TODO: Remove this comment if we don't care about skipped tests in callers.
     */
    public function isSuccessful(): bool
    {
        return $this->getTotalFailures() === 0 && $this->getTotalErrors() === 0;
    }

    /**
     * Flattens all cases into their respective suites.
     */
    public function mergeReaders(): TestSuite
    {
        $suites = [];
        foreach ($this->readers as $reader) {
            $suites[] = $reader->getSuite();
        }
        if (1 === count($suites)) {
            return current($suites);
        }

        $mainSuite = TestSuite::empty();
        foreach ($suites as $suite) {
            $mainSuite->tests += $suite->tests;
            $mainSuite->assertions += $suite->assertions;
            $mainSuite->failures += $suite->failures;
            $mainSuite->errors += $suite->errors;
            $mainSuite->warnings += $suite->warnings;
            $mainSuite->risky += $suite->risky;
            $mainSuite->skipped += $suite->skipped;
            $mainSuite->time += $suite->time;
        }
        $mainSuite->suites = array_values($suites);
            
        return $mainSuite;
    }

    /**
     * @return TestCase[]
     */
    private function getCases(TestSuite $testSuite): array
    {
        $cases = $testSuite->cases;
        foreach ($testSuite->suites as $suite) {
            $cases = array_merge($cases, $this->getCases($suite));
        }

        return $cases;
    }

    public function getTotalTests(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalTests();
        }, 0);
    }

    public function getTotalAssertions(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalAssertions();
        }, 0);
    }

    public function getTotalErrors(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalErrors();
        }, 0);
    }

    public function getTotalFailures(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalFailures();
        }, 0);
    }

    public function getTotalWarnings(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalWarnings();
        }, 0);
    }

    public function getTotalSkipped(): int
    {
        return array_reduce($this->readers, static function (int $result, Reader $reader): int {
            return $result + $reader->getTotalSkipped();
        }, 0);
    }

    public function getTotalTime(): float
    {
        return array_reduce($this->readers, static function (float $result, Reader $reader): float {
            return $result + $reader->getTotalTime();
        }, 0.0);
    }

    /**
     * {@inheritDoc}
     */
    public function getErrors(): array
    {
        $messages = [];
        foreach ($this->readers as $reader) {
            $messages = array_merge($messages, $reader->getErrors());
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getWarnings(): array
    {
        $messages = [];
        foreach ($this->readers as $reader) {
            $messages = array_merge($messages, $reader->getWarnings());
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getFailures(): array
    {
        $messages = [];
        foreach ($this->readers as $reader) {
            $messages = array_merge($messages, $reader->getFailures());
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getRisky(): array
    {
        $messages = [];
        foreach ($this->readers as $reader) {
            $messages = array_merge($messages, $reader->getRisky());
        }

        return $messages;
    }

    /**
     * {@inheritDoc}
     */
    public function getSkipped(): array
    {
        $messages = [];
        foreach ($this->readers as $reader) {
            $messages = array_merge($messages, $reader->getSkipped());
        }

        return $messages;
    }
}
