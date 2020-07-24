<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use Exception;
use PHPUnit;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Runner\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionObject;
use SebastianBergmann\Environment\Runtime;
use SplFileObject;

use function copy;
use function file_exists;
use function get_class;
use function is_dir;
use function is_object;
use function is_string;
use function preg_match;
use function rmdir;
use function str_replace;
use function uniqid;
use function unlink;

class TestBase extends PHPUnit\Framework\TestCase
{
    /**
     * Get PHPUnit version.
     */
    protected static function getPhpUnitVersion(): string
    {
        return Version::id();
    }

    protected function fixture($fixture)
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new Exception("Fixture $fixture not found");
        }

        return $fixture;
    }

    protected function findTests($dir)
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

    protected function getObjectValue($object, $property)
    {
        $prop = $this->getAccessibleProperty($object, $property);

        return $prop->getValue($object);
    }

    protected function setObjectValue($object, $property, $value)
    {
        $prop = $this->getAccessibleProperty($object, $property);

        return $prop->setValue($object, $value);
    }

    private function getAccessibleProperty($object, $property)
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
    public function call(object $object, string $methodName, ...$args)
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
    public function callStatic(string $class, string $methodName, ...$args)
    {
        return self::callMethod($class, $methodName, $args);
    }

    protected static function callMethod($objectOrClassName, $methodName, $args = null)
    {
        $isStatic = is_string($objectOrClassName);

        if (! $isStatic) {
            if (! is_object($objectOrClassName)) {
                throw new Exception('Method call on non existent object or class');
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
    protected static function skipIfCodeCoverageNotEnabled(): void
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
    protected function removeDirectory(string $dirname): void
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
    protected function copyCoverageFile(string $fixture, string $directory): string
    {
        $fixturePath = $this->fixture($fixture);
        $filename    = str_replace('.', '_', $directory . DS . uniqid('cov-', true));
        copy($fixturePath, $filename);

        return $filename;
    }
}
