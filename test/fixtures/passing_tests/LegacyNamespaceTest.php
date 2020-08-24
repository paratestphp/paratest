<?php

declare(strict_types=1);

namespace {

/**
 * This exampletest ensures that legacy namespaces (non PSR-0) can be used.
 *
 * @internal
 */
    class LegacyNamespaceTest extends PHPUnit\Framework\TestCase
    {
        public function testAlwaysTrue(): void
        {
            $this->assertTrue(true);
        }
    }

}
