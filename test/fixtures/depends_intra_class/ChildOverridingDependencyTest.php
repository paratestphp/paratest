<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\depends_intra_class;

/**
 * PHPUnit collects inherited test methods before the ones declared in the class itself:
 * overriding the dependency moves it after the dependent test in declaration order.
 *
 * @internal
 */
final class ChildOverridingDependencyTest extends DependsTestCase
{
    public function testProducer(): string
    {
        self::assertTrue(true);

        return 'parent';
    }
}
