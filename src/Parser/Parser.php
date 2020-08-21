<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\StandardTestSuiteLoader;
use ReflectionClass;
use ReflectionMethod;

use function assert;
use function is_file;
use function preg_match;
use function realpath;
use function str_replace;

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

    public function __construct(string $srcPath)
    {
        if (! is_file($srcPath)) {
            throw new InvalidArgumentException('file not found: ' . $srcPath);
        }

        $srcPath = realpath($srcPath);
        assert($srcPath !== false);

        if (! isset(self::$alreadyLoadedSources[$srcPath])) {
            try {
                self::$alreadyLoadedSources[$srcPath] = (new StandardTestSuiteLoader())->load($srcPath);
            } catch (Exception $exception) {
                throw new NoClassInFileException('', 0, $exception);
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
        return new ParsedClass(
            (string) $this->refl->getDocComment(),
            $this->getCleanReflectionName(),
            $this->refl->getNamespaceName(),
            $this->getMethods()
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
