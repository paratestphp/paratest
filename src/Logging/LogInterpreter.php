<?php

declare(strict_types=1);

namespace ParaTest\Logging;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\TestSuite;

use function array_merge;
use function array_reduce;
use function assert;

/** @internal */
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
        $mainSuite = null;
        foreach ($this->readers as $reader) {
            $otherSuite = $reader->getSuite();
            if ($mainSuite === null) {
                $mainSuite = $otherSuite;
                continue;
            }

            if ($mainSuite->name !== $otherSuite->name) {
                if ($mainSuite->name !== '') {
                    $mainSuite2         = clone $mainSuite;
                    $mainSuite2->name   = '';
                    $mainSuite2->file   = '';
                    $mainSuite2->suites = [$mainSuite->name => $mainSuite];
                    $mainSuite2->cases  = [];
                    $mainSuite          = $mainSuite2;
                }

                if ($otherSuite->name !== '') {
                    $otherSuite2         = clone $otherSuite;
                    $otherSuite2->name   = '';
                    $otherSuite2->file   = '';
                    $otherSuite2->suites = [$otherSuite->name => $otherSuite];
                    $otherSuite2->cases  = [];
                    $otherSuite          = $otherSuite2;
                }
            }

            $this->mergeSuites($mainSuite, $otherSuite);
        }

        assert($mainSuite !== null);

        return $mainSuite;
    }

    private function mergeSuites(TestSuite $suite1, TestSuite $suite2): TestSuite
    {
        assert($suite1->name === $suite2->name);

        foreach ($suite2->suites as $suite2suiteName => $suite2suite) {
            if (! isset($suite1->suites[$suite2suiteName])) {
                $suite1->suites[$suite2suiteName] = $suite2suite;
                continue;
            }

            $suite1->suites[$suite2suiteName] = $this->mergeSuites(
                $suite1->suites[$suite2suiteName],
                $suite2suite,
            );
        }

        $suite1->tests      += $suite2->tests;
        $suite1->assertions += $suite2->assertions;
        $suite1->failures   += $suite2->failures;
        $suite1->errors     += $suite2->errors;
        $suite1->warnings   += $suite2->warnings;
        $suite1->risky      += $suite2->risky;
        $suite1->skipped    += $suite2->skipped;
        $suite1->time       += $suite2->time;
        $suite1->cases       = array_merge(
            $suite1->cases,
            $suite2->cases,
        );

        return $suite1;
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
