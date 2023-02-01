<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use InvalidArgumentException;
use SimpleXMLElement;


/**
 * @internal
 * @immutable
 */
final class TestSuite
{
    /** @var array<string, TestSuite> */
    public readonly array $suites;
    /** @var list<TestCase> */
    public readonly array $cases;

    /**
     * @param array<string, TestSuite> $suites
     * @param list<TestCase>           $cases
     */
    public function __construct(
        public readonly string $name,
        public readonly int $tests,
        public readonly int $assertions,
        public readonly int $failures,
        public readonly int $errors,
        public readonly int $warnings,
        public readonly int $risky,
        public readonly int $skipped,
        public readonly float $time,
        public readonly string $file,
        array $suites,
        array $cases
    ) {
        $this->suites = $suites;
        $this->cases  = $cases;
    }

    public static function fromFile(\SplFileInfo $logFile): self
    {
        if (! $logFile->isFile()) {
            throw new InvalidArgumentException("Log file {$logFile->getPathname()} does not exist");
        }

        if (0 === (int) $logFile->getSize()) {
            throw new InvalidArgumentException(
                "Log file {$logFile->getPathname()} is empty. This means a PHPUnit process has crashed.",
            );
        }

        $logFileContents = file_get_contents($logFile->getPathname());
        assert($logFileContents !== false);

        return self::parseTestSuite(
            new SimpleXMLElement($logFileContents),
            true
        );
    }

    private static function parseTestSuite(SimpleXMLElement $node, bool $isRootSuite): self
    {
        if ($isRootSuite) {
            foreach ($node->testsuite as $singleTestSuiteXml) {
                return self::parseTestSuite($singleTestSuiteXml, false);
            }
        }

        $suites = [];
        foreach ($node->testsuite as $singleTestSuiteXml) {
            $testSuite                = self::parseTestSuite($singleTestSuiteXml, false);
            $suites[$testSuite->name] = $testSuite;
        }

        $cases = [];
        foreach ($node->testcase as $singleTestCase) {
            $cases[] = TestCase::caseFromNode($singleTestCase);
        }

        $risky  = array_sum(array_map(static function (TestCase $testCase): int {
            return (int) (
                $testCase instanceof TestCaseWithMessage
                && $testCase->xmlTagName === MessageType::risky
            );
        }, $cases));
        $risky += array_sum(array_map(static function (TestSuite $testSuite): int {
            return $testSuite->risky;
        }, $suites));

        return new self(
            (string) $node['name'],
            (int) $node['tests'],
            (int) $node['assertions'],
            (int) $node['failures'],
            (int) $node['errors'] - $risky,
            (int) $node['warnings'],
            $risky,
            (int) $node['skipped'],
            (float) $node['time'],
            (string) $node['file'],
            $suites,
            $cases,
        );
    }
}
