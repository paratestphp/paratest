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
     * Get all test case objects found within
     * the collection of Reader objects.
     *
     * @return TestCase[]
     */
    public function getCases(): array
    {
        $cases = [];
        foreach ($this->readers as $reader) {
            foreach ($reader->getSuites() as $suite) {
                $cases = array_merge($cases, $suite->cases);
                foreach ($suite->suites as $nested) {
                    $this->extendEmptyCasesFromSuites($nested->cases, $suite);
                    $cases = array_merge($cases, $nested->cases);
                }
            }
        }

        return $cases;
    }

    /**
     * Fix problem with empty testcase from DataProvider.
     *
     * @param TestCase[] $cases
     */
    private function extendEmptyCasesFromSuites(array $cases, TestSuite $suite): void
    {
        $class = $suite->name;
        $file  = $suite->file;

        foreach ($cases as $case) {
            if ($case->class === '') {
                $case->class = $class;
            }

            if ($case->file !== '') {
                continue;
            }

            $case->file = $file;
        }
    }

    /**
     * Flattens all cases into their respective suites.
     *
     * @return TestSuite[] A collection of suites and their cases
     * @psalm-return list<TestSuite>
     */
    public function flattenCases(): array
    {
        $dict = [];
        foreach ($this->getCases() as $case) {
            if (! isset($dict[$case->file])) {
                $dict[$case->file] = TestSuite::empty();
            }

            $dict[$case->file]->name    = $case->class;
            $dict[$case->file]->file    = $case->file;
            $dict[$case->file]->cases[] = $case;
            ++$dict[$case->file]->tests;
            $dict[$case->file]->assertions += $case->assertions;
            $dict[$case->file]->failures   += count($case->failures);
            $dict[$case->file]->errors     += count($case->errors) + count($case->risky);
            $dict[$case->file]->warnings   += count($case->warnings);
            $dict[$case->file]->skipped    += count($case->skipped);
            $dict[$case->file]->time       += $case->time;
        }

        return array_values($dict);
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
