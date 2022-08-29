<?php

declare(strict_types=1);

namespace ParaTest\Parser;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Exception;
use PHPUnit\Runner\StandardTestSuiteLoader;
use PHPUnit\Util\Test as TestUtil;
use ReflectionClass;
use ReflectionMethod;

use function array_diff;
use function assert;
use function get_declared_classes;
use function is_file;
use function realpath;

/** @internal */
final class Parser
{
    /** @var ReflectionClass<TestCase> */
    private $refl;

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
                    [self::$alreadyLoadedSources[$srcPath]->getName()],
                );
            } catch (Exception $exception) {
                self::$externalClassesFound += array_diff(get_declared_classes(), $declaredClasses);

                $reflFound = null;
                foreach (self::$externalClassesFound as $newClass) {
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
            $this->refl->getName(),
            $this->getMethods(),
            $parentsCount,
        );
    }

    /**
     * Return all test methods present in the file.
     *
     * @return ReflectionMethod[]
     * @psalm-return list<ReflectionMethod>
     */
    private function getMethods(): array
    {
        $methods = [];
        // @see \PHPUnit\Framework\TestSuite::__construct
        foreach ($this->refl->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === Assert::class) {
                continue;
            }

            if ($method->getDeclaringClass()->getName() === TestCase::class) {
                continue;
            }

            if (! TestUtil::isTestMethod($method)) {
                continue;
            }

            $methods[] = $method;
        }

        return $methods;
    }
}
