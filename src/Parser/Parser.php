<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\StandardTestSuiteLoader;
use ReflectionClass;
use ReflectionMethod;

use function array_diff;
use function assert;
use function get_declared_classes;
use function is_file;
use function preg_match;
use function realpath;
use function str_replace;

/**
 * @internal
 */
final class Parser
{
    /** @var ReflectionClass<TestCase> */
    private $refl;

    /**
     * Matches a test method beginning with the conventional "test"
     * word.
     *
     * @var string
     */
    private static $testName = '/^test/';

    /**
     * A pattern for matching test methods that use the @test annotation.
     *
     * @var string
     */
    private static $testAnnotation = '/@test\b/';

    /** @var ReflectionClass<TestCase>[] */
    private static $alreadyLoadedSources = [];

    /** @var class-string[]  */
    private static $externalClassesFound = [];

    public function __construct(string $srcPath)
    {
        if (! is_file($srcPath)) {
            throw new InvalidArgumentException('file not found: ' . $srcPath);
        }

        $srcPath = realpath($srcPath);
        assert($srcPath !== false);

        if (! isset(self::$alreadyLoadedSources[$srcPath])) {
            $declaredClasses = get_declared_classes();
            try {
                self::$alreadyLoadedSources[$srcPath] = (new StandardTestSuiteLoader())->load($srcPath);

                self::$externalClassesFound += array_diff(
                    get_declared_classes(),
                    $declaredClasses,
                    [self::$alreadyLoadedSources[$srcPath]->getName()]
                );
            } catch (Exception $exception) {
                self::$externalClassesFound += array_diff(get_declared_classes(), $declaredClasses);

                $reflFound = null;
                foreach (self::$externalClassesFound as $newClass) {
                    // DocType is untruth but needed to make SA happy
                    // Real checks are done below
                    /** @var ReflectionClass<TestCase> $refClass */
                    $refClass = new ReflectionClass($newClass);
                    if ($refClass->getFileName() !== $srcPath) {
                        continue;
                    }

                    $reflFound = $refClass;
                    break;
                }

                if ($reflFound === null || ! $reflFound->isSubclassOf(TestCase::class) || $reflFound->isAbstract()) {
                    throw new NoClassInFileException('', 0, $exception);
                }

                self::$alreadyLoadedSources[$srcPath] = $reflFound;
            }
        }

        $this->refl = self::$alreadyLoadedSources[$srcPath];
    }

    /**
     * Returns the fully constructed class
     * with methods or null if the class is abstract.
     */
    public function getClass(): ParsedClass
    {
        $parentsCount = 0;
        $class        = $this->refl;
        while (($parent = $class->getParentClass()) !== false) {
            ++$parentsCount;
            $class = $parent;
        }

        return new ParsedClass(
            (string) $this->refl->getDocComment(),
            $this->getCleanReflectionName(),
            $this->refl->getNamespaceName(),
            $this->getMethods(),
            $parentsCount
        );
    }

    /**
     * Return reflection name with null bytes stripped.
     */
    private function getCleanReflectionName(): string
    {
        return str_replace("\x00", '', $this->refl->getName());
    }

    /**
     * Return all test methods present in the file.
     *
     * @return ParsedFunction[]
     */
    private function getMethods(): array
    {
        $tests   = [];
        $methods = $this->refl->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $hasTestName       = preg_match(self::$testName, $method->getName()) > 0;
            $docComment        = $method->getDocComment();
            $hasTestAnnotation = $docComment !== false && preg_match(self::$testAnnotation, $docComment) > 0;
            $isTestMethod      = $hasTestName || $hasTestAnnotation;
            if (! $isTestMethod) {
                continue;
            }

            $tests[] = new ParsedFunction((string) $method->getDocComment(), $method->getName());
        }

        return $tests;
    }
}
