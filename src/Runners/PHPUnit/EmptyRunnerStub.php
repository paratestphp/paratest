<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

class EmptyRunnerStub extends BaseRunner
{
    public function run()
    {
        echo 'EXECUTED';
    }
}
