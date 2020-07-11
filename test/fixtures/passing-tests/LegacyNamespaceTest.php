<?php

namespace {

/**
 * This exampletest ensures that legacy namespaces (non PSR-0) can be used.
 */
class LegacyNamespaceTest extends PHPUnit\Framework\TestCase
{
    public function testAlwaysTrue()
    {
        $this->assertTrue(true);
    }
}

}
