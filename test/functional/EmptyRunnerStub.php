<?php

declare(strict_types = 1);

namespace ParaTest\Tests\Functional;

use ParaTest\Runners\PHPUnit\BaseRunner;

class EmptyRunnerStub extends BaseRunner
{
    public function run()
    {
        echo 'EXECUTED';
    }
}
