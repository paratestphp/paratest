<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\failing_listener;

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use RuntimeException;

/**
 * @internal
 */
final class MyListener implements TestListener
{
    use TestListenerDefaultImplementation;

    public function startTest(Test $test): void
    {
        throw new RuntimeException('lorem');
    }
}
