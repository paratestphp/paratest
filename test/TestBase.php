<?php
class TestBase extends PHPUnit_Framework_TestCase
{
    protected function pathToFixture($fixture)
    {
        $fixture = FIXTURES . DS . $fixture;
        if(!file_exists($fixture))
            throw new Exception("Fixture not found");
        
        return $fixture;
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
     * @param Class $class the class to call a method on
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
}
