<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

final class EmptyRunnerStub extends BaseRunner
{
    public function run(): void
    {
        echo 'EXECUTED';
    }

    protected function beforeLoadChecks(): void
    {
    }
}
