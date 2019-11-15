<?php

declare(strict_types=1);

namespace ParaTest\Parser;

class Parser
{
    /**
     * The path to the source file to parse.
     *
     * @var string
     */
    private $path;

    /**
     * @var \ReflectionClass
     */
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

    public function __construct(string $srcPath)
    {
        if (!\file_exists($srcPath)) {
            throw new \InvalidArgumentException('file not found: ' . $srcPath);
        }

        $this->path = $srcPath;
        $declaredClasses = \get_declared_classes();
        require_once $this->path;
        $class = $this->getClassName($this->path, $declaredClasses);
        if (!$class) {
            throw new NoClassInFileException();
        }
        try {
            $this->refl = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException(
                'Unable to instantiate ReflectionClass. ' . $class . ' not found in: ' . $srcPath
            );
        }
    }

    /**
     * Returns the fully constructed class
     * with methods or null if the class is abstract.
     *
     * @return ParsedClass|null
     */
    public function getClass()
    {
        return $this->refl->isAbstract()
            ? null
            : new ParsedClass(
                (string) $this->refl->getDocComment(),
                $this->getCleanReflectionName(),
                $this->refl->getNamespaceName(),
                $this->getMethods()
            );
    }

    /**
     * Return reflection name with null bytes stripped.
     *
     * @return string
     */
    private function getCleanReflectionName(): string
    {
        return \str_replace("\x00", '', $this->refl->getName());
    }

    /**
     * Return all test methods present in the file.
     *
     * @return array
     */
    private function getMethods(): array
    {
        $tests = [];
        $methods = $this->refl->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $hasTestName = \preg_match(self::$testName, $method->getName());
            $docComment = $method->getDocComment();
            $hasTestAnnotation = false !== $docComment && \preg_match(self::$testAnnotation, $docComment);
            $isTestMethod = $hasTestName || $hasTestAnnotation;
            if ($isTestMethod) {
                $tests[] = new ParsedFunction((string) $method->getDocComment(), 'public', $method->getName());
            }
        }

        return $tests;
    }

    /**
     * Return the class name of the class contained
     * in the file.
     *
     * @param mixed $filename
     * @param mixed $previousDeclaredClasses
     *
     * @return string
     */
    private function getClassName(string $filename, array $previousDeclaredClasses)
    {
        $filename = \realpath($filename);
        $classes = \get_declared_classes();
        $newClasses = \array_values(\array_diff($classes, $previousDeclaredClasses));

        $className = $this->searchForUnitTestClass($newClasses, $filename);
        if (isset($className)) {
            return $className;
        }

        $className = $this->searchForUnitTestClass($classes, $filename);
        if (isset($className)) {
            return $className;
        }
    }

    /**
     * Search for the name of the unit test.
     *
     * @param string[] $classes
     * @param string   $filename
     *
     * @return string|null
     */
    private function searchForUnitTestClass(array $classes, string $filename)
    {
        // TODO: After merging this PR or other PR for phpunit 6 support, keep only the applicable subclass name
        $matchingClassName = null;
        foreach ($classes as $className) {
            $class = new \ReflectionClass($className);
            if ($class->getFileName() === $filename) {
                if ($class->isSubclassOf('PHPUnit\Framework\TestCase')) {
                    if ($this->classNameMatchesFileName($filename, $className)) {
                        return $className;
                    } elseif ($matchingClassName === null) {
                        $matchingClassName = $className;
                    }
                }
            }
        }

        return $matchingClassName;
    }

    /**
     * @param $filename
     * @param $className
     *
     * @return bool
     */
    private function classNameMatchesFileName(string $filename, string $className): bool
    {
        return \strpos($filename, $className) !== false
            || \strpos($filename, $this->invertSlashes($className)) !== false;
    }

    private function invertSlashes(string $className): string
    {
        return \str_replace('\\', '/', $className);
    }
}
