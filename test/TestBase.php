<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Options;
use PHPUnit;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Runner\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use RuntimeException;
use SebastianBergmann\Environment\Runtime;
use SplFileObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;

use function copy;
use function file_exists;
use function get_class;
use function getcwd;
use function is_dir;
use function is_object;
use function is_string;
use function preg_match;
use function rmdir;
use function str_replace;
use function uniqid;
use function unlink;

abstract class TestBase extends PHPUnit\Framework\TestCase
{
    /**
     * @param array<string, string|bool|int> $argv
     */
    final protected function createOptionsFromArgv(array $argv, ?string $cwd = null): Options
    {
        $inputDefinition = new InputDefinition();
        Options::setInputDefinition($inputDefinition);

        $input = new ArrayInput($argv, $inputDefinition);

        return Options::fromConsoleInput($input, $cwd ?? getcwd());
    }

    /**
     * Get PHPUnit version.
     */
    final protected static function getPhpUnitVersion(): string
    {
        return Version::id();
    }

    final protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new InvalidArgumentException("Fixture $fixture not found");
        }

        return $fixture;
    }

    /**
     * @return string[]
     */
    final protected function findTests(string $dir): array
    {
        $it    = new RecursiveDirectoryIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        $it    = new RecursiveIteratorIterator($it);
        $files = [];
        foreach ($it as $file) {
            if (! preg_match('/Test\.php$/', $file->getPathname())) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return mixed
     */
    final protected function getObjectValue(object $object, string $property)
    {
        $prop = $this->getAccessibleProperty($object, $property);

        return $prop->getValue($object);
    }

    /**
     * @param mixed $value
     */
    final protected function setObjectValue(object $object, string $property, $value): void
    {
        $prop = $this->getAccessibleProperty($object, $property);

        $prop->setValue($object, $value);
    }

    private function getAccessibleProperty(object $object, string $property): ReflectionProperty
    {
        $refl = new ReflectionObject($object);
        $prop = $refl->getProperty($property);
        $prop->setAccessible(true);

        return $prop;
    }

    /**
     * Calls an object method even if it is protected or private.
     *
     * @param object $object     the object to call a method on
     * @param string $methodName the method name to be called
     * @param mixed  $args       0 or more arguments passed in the function
     *
     * @return mixed returns what the object's method call will return
     */
    final public function call(object $object, string $methodName, ...$args)
    {
        return self::callMethod($object, $methodName, $args);
    }

    /**
     * Calls a class method even if it is protected or private.
     *
     * @param string $class      the class to call a method on
     * @param string $methodName the method name to be called
     * @param mixed  $args       0 or more arguments passed in the function
     *
     * @return mixed returns what the object's method call will return
     */
    final public function callStatic(string $class, string $methodName, ...$args)
    {
        return self::callMethod($class, $methodName, $args);
    }

    /**
     * @param string|object $objectOrClassName
     * @param mixed[]|null  $args
     *
     * @return mixed
     */
    final protected static function callMethod($objectOrClassName, string $methodName, ?array $args = null)
    {
        $isStatic = is_string($objectOrClassName);

        if (! $isStatic) {
            if (! is_object($objectOrClassName)) {
                throw new RuntimeException('Method call on non existent object or class');
            }
        }

        $class  = $isStatic ? $objectOrClassName : get_class($objectOrClassName);
        $object = $isStatic ? null : $objectOrClassName;

        $reflectionClass = new ReflectionClass($class);
        $method          = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * @throws SkippedTestError When code coverage library is not found.
     */
    final protected static function skipIfCodeCoverageNotEnabled(): void
    {
        static $runtime;
        if ($runtime === null) {
            $runtime = new Runtime();
        }

        if ($runtime->canCollectCodeCoverage()) {
            return;
        }

        static::markTestSkipped('No code coverage driver available');
    }

    /**
     * Remove dir and its files.
     */
    final protected function removeDirectory(string $dirname): void
    {
        if (! file_exists($dirname) || ! is_dir($dirname)) {
            return;
        }

        $directory = new RecursiveDirectoryIterator(
            $dirname,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        /** @var SplFileObject[] $iterator */
        $iterator = new RecursiveIteratorIterator(
            $directory,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dirname);
    }

    /**
     * Copy fixture file to tmp folder, cause coverage file will be deleted by merger.
     *
     * @param string $fixture Fixture coverage file name
     *
     * @return string Copied coverage file
     */
    final protected function copyCoverageFile(string $fixture, string $directory): string
    {
        $fixturePath = $this->fixture($fixture);
        $filename    = str_replace('.', '_', $directory . DS . uniqid('cov-', true));
        copy($fixturePath, $filename);

        return $filename;
    }
}
