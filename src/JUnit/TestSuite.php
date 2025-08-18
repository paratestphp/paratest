<?php

declare(strict_types=1);

namespace ParaTest\JUnit;

use SimpleXMLElement;
use SplFileInfo;

use function array_merge;
use function assert;
use function count;
use function file_get_contents;
use function ksort;

/**
 * @internal
 *
 * @immutable
 */
final readonly class TestSuite
{
    /**
     * @param array<string, TestSuite> $suites
     * @param list<TestCase>           $cases
     */
    public function __construct(
        public string $name,
        public int $tests,
        public int $assertions,
        public int $failures,
        public int $errors,
        public int $skipped,
        public float $time,
        public string $file,
        public array $suites,
        public array $cases
    ) {
    }

    public static function fromFile(SplFileInfo $logFile): self
    {
        assert($logFile->isFile() && 0 < (int) $logFile->getSize());

        $logFileContents = file_get_contents($logFile->getPathname());
        assert($logFileContents !== false);

        return self::parseTestSuite(
            new SimpleXMLElement($logFileContents),
            true,
        );
    }

    private static function parseTestSuite(SimpleXMLElement $node, bool $isRootSuite): self
    {
        if ($isRootSuite) {
            $tests      = 0;
            $assertions = 0;
            $failures   = 0;
            $errors     = 0;
            $skipped    = 0;
            $time       = 0;
        } else {
            $tests      = (int) $node['tests'];
            $assertions = (int) $node['assertions'];
            $failures   = (int) $node['failures'];
            $errors     = (int) $node['errors'];
            $skipped    = (int) $node['skipped'];
            $time       = (float) $node['time'];
        }

        $count  = count($node->testsuite);
        $suites = [];
        foreach ($node->testsuite as $singleTestSuiteXml) {
            $testSuite = self::parseTestSuite($singleTestSuiteXml, false);
            if ($isRootSuite && $count === 1) {
                return $testSuite;
            }

            if (isset($suites[$testSuite->name])) {
                $suites[$testSuite->name] = $suites[$testSuite->name]->mergeWith($testSuite);
            } else {
                $suites[$testSuite->name] = $testSuite;
            }

            if (! $isRootSuite) {
                continue;
            }

            $tests      += $testSuite->tests;
            $assertions += $testSuite->assertions;
            $failures   += $testSuite->failures;
            $errors     += $testSuite->errors;
            $skipped    += $testSuite->skipped;
            $time       += $testSuite->time;
        }

        $cases = [];
        foreach ($node->testcase as $singleTestCase) {
            $cases[] = TestCase::caseFromNode($singleTestCase);
        }

        return new self(
            (string) $node['name'],
            $tests,
            $assertions,
            $failures,
            $errors,
            $skipped,
            $time,
            (string) $node['file'],
            $suites,
            $cases,
        );
    }

    public function mergeWith(self $other): self
    {
        assert($this->name === $other->name);

        $suites = $this->suites;
        foreach ($other->suites as $otherSuiteName => $otherSuite) {
            if (! isset($this->suites[$otherSuiteName])) {
                $suites[$otherSuiteName] = $otherSuite;
                continue;
            }

            $suites[$otherSuiteName]->mergeWith($otherSuite);
        }

        ksort($suites);

        return new TestSuite(
            $this->name,
            $this->tests + $other->tests,
            $this->assertions + $other->assertions,
            $this->failures + $other->failures,
            $this->errors + $other->errors,
            $this->skipped + $other->skipped,
            $this->time + $other->time,
            $this->file,
            $suites,
            array_merge($this->cases, $other->cases),
        );
    }
}
