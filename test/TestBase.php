<?php

use SebastianBergmann\CodeCoverage\CodeCoverage;

class TestBase extends PHPUnit_Framework_TestCase
{
    /**
     * Get PHPUnit version

     * @return string
     */
    protected static function getPhpUnitVersion()
    {
        if (method_exists('\\PHPUnit_Runner_Version', 'id')) {
            return \PHPUnit_Runner_Version::id();
        }
        return \PHPUnit_Runner_Version::VERSION;
    }

    protected function fixture($fixture)
    {
        $fixture = FIXTURES . DS . $fixture;
        if(!file_exists($fixture))
            throw new Exception("Fixture $fixture not found");

        return $fixture;
    }

    protected function findTests($dir)
    {
        $files = array();
        foreach(new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveIteratorIterator::SELF_FIRST)) as $file){
            if(preg_match('/Test\.php$/', $file)) $files []= $file;
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
        $refl = new \ReflectionObject($object);
        $prop = $refl->getProperty($property);
        $prop->setAccessible(true);
        return $prop;
    }

    /**
     * Calls an object method even if it is protected or private
     * @param Object $object the object to call a method on
     * @param string $methodName the method name to be called
     * @param mixed $args 0 or more arguments passed in the function
     * @return mixed returns what the object's method call will return
     */
    public function call($object, $methodName, $args = null)
    {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        return self::_callMethod($object, $methodName, $args);
    }

    /**
     * Calls a class method even if it is protected or private
     * @param string $class the class to call a method on
     * @param string $methodName the method name to be called
     * @param mixed $args 0 or more arguments passed in the function
     * @return mixed returns what the object's method call will return
     */
    public function callStatic($class, $methodName, $args = null)
    {
        $args = func_get_args();
        array_shift($args);
        array_shift($args);
        return self::_callMethod($class, $methodName, $args);
    }

    protected static function _callMethod($objectOrClassName, $methodName, $args = null)
    {
        $isStatic = is_string($objectOrClassName);

        if (!$isStatic) {
            if (!is_object($objectOrClassName)) {
                throw new Exception('Method call on non existent object or class');
            }
        }

        $class = $isStatic ? $objectOrClassName : get_class($objectOrClassName);
        $object = $isStatic ? null : $objectOrClassName;

        $reflectionClass = new ReflectionClass($class);
        $method = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * @throws \PHPUnit_Framework_SkippedTestError When code coverage library is not found
     */
    protected static function skipIfCodeCoverageNotEnabled()
    {
        try {
            if (class_exists('\\PHP_CodeCoverage')) {
                new \PHP_CodeCoverage();
            } else if (class_exists('\\SebastianBergmann\\CodeCoverage\\CodeCoverage')) {
                new CodeCoverage();
            }
        } catch(\Exception $e) {
            static::markTestSkipped($e->getMessage());
        }
    }

    /**
     * Remove dir and its files
     * @param string $dirname
     */
    protected function removeDirectory($dirname)
    {
        if (!file_exists($dirname) || !is_dir($dirname)) {
            return;
        }

        $directory = new \RecursiveDirectoryIterator(
            $dirname,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        /** @var \SplFileObject[] $iterator */
        $iterator = new \RecursiveIteratorIterator(
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
     * Copy fixture file to tmp folder, cause coverage file will be deleted by merger
     *
     * @param string $fixture Fixture coverage file name
     * @param string $directory
     *
     * @return string Copied coverage file
     */
    protected function copyCoverageFile($fixture, $directory = '/tmp')
    {
        $fixturePath = $this->fixture($fixture);
        $filename = str_replace('.', '_', uniqid($directory . '/cov-', true));
        copy($fixturePath, $filename);
        return $filename;
    }
}
