<?php

declare(strict_types = 1);

namespace ParaTest\Tests\Functional;

class EmptyRunnerStub extends \ParaTest\Runners\PHPUnit\BaseRunner
{
    public function run()
    {
        echo 'EXECUTED';
    }
}
