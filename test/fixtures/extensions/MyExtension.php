<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\extensions;

use PHPUnit\Event\TestRunner\EventFacadeSealed;
use PHPUnit\Event\TestRunner\EventFacadeSealedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class MyExtension implements Extension, EventFacadeSealedSubscriber
{
    public static string $value = 'fail';

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber($this);
    }

    public function notify(EventFacadeSealed $event): void
    {
        self::$value = 'success';
    }
}
