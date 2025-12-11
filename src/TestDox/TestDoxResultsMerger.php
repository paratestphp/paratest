<?php

declare(strict_types=1);

namespace ParaTest\TestDox;

use PHPUnit\Logging\TestDox\TestResult as TestDoxTestMethod;
use PHPUnit\Logging\TestDox\TestResultCollection;
use ReflectionException;
use ReflectionMethod;
use SplFileInfo;

use function array_merge;
use function assert;
use function file_get_contents;
use function is_subclass_of;
use function ksort;
use function uksort;
use function unserialize;
use function usort;

/** @internal */
final readonly class TestDoxResultsMerger
{
    /**
     * @param list<SplFileInfo> $testdoxFiles
     *
     * @return array<string, TestResultCollection>
     */
    public function getResultsFromTestdoxFiles(array $testdoxFiles): array
    {
        /** @var array<string, TestResultCollection> $testMethodsGroupedByClass */
        $testMethodsGroupedByClass = [];
        foreach ($testdoxFiles as $testdoxFile) {
            if (! $testdoxFile->isFile()) {
                continue;
            }

            $testdoxFileContents = file_get_contents($testdoxFile->getPathname());
            assert($testdoxFileContents !== false);

            /** @var array<string, TestResultCollection> $testMethodsGroupedByClassInTestdoxFile */
            $testMethodsGroupedByClassInTestdoxFile = unserialize($testdoxFileContents);
            foreach ($testMethodsGroupedByClassInTestdoxFile as $className => $testResultCollection) {
                if (! isset($testMethodsGroupedByClass[$className])) {
                    $testMethodsGroupedByClass[$className] = $testResultCollection;
                } else {
                    $combinedTestResultCollection          = TestResultCollection::fromArray([
                        ...$testMethodsGroupedByClass[$className]->asArray(),
                        ...$testResultCollection->asArray(),
                    ]);
                    $testMethodsGroupedByClass[$className] = $combinedTestResultCollection;
                }
            }
        }

        return $this->orderTestdoxResults($testMethodsGroupedByClass);
    }

    /**
     * @param array<string, TestResultCollection> $testdoxResults
     *
     * @return array<string, TestResultCollection>
     */
    private function orderTestdoxResults(array $testdoxResults): array
    {
        // @see \PHPUnit\Logging\TestDox\TestResultCollector::testMethodsGroupedByClass
        $orderedTestdoxResults = [];

        foreach ($testdoxResults as $prettifiedClassName => $tests) {
            $testsByDeclaringClass = [];

            foreach ($tests as $test) {
                try {
                    $declaringClassName = (new ReflectionMethod($test->test()->className(), $test->test()->methodName()))->getDeclaringClass()->getName();
                } catch (ReflectionException) {
                    $declaringClassName = $test->test()->className();
                }

                if (! isset($testsByDeclaringClass[$declaringClassName])) {
                    $testsByDeclaringClass[$declaringClassName] = [];
                }

                $testsByDeclaringClass[$declaringClassName][] = $test;
            }

            foreach ($testsByDeclaringClass as $declaringClassName) {
                usort(
                    $declaringClassName,
                    static function (TestDoxTestMethod $a, TestDoxTestMethod $b): int {
                        return $a->test()->line() <=> $b->test()->line();
                    },
                );
            }

            uksort(
                $testsByDeclaringClass,
                /**
                 * @param class-string $a
                 * @param class-string $b
                 */
                static function (string $a, string $b): int {
                    if (is_subclass_of($b, $a)) {
                        return -1;
                    }

                    if (is_subclass_of($a, $b)) {
                        return 1;
                    }

                    return 0;
                },
            );

            $tests = [];

            foreach ($testsByDeclaringClass as $_tests) {
                $tests = array_merge($tests, $_tests);
            }

            $orderedTestdoxResults[$prettifiedClassName] = TestResultCollection::fromArray($tests);
        }

        ksort($orderedTestdoxResults);

        return $orderedTestdoxResults;
    }
}
