<?php

declare(strict_types=1);
class EmptyRunnerStub extends \ParaTest\Runners\PHPUnit\BaseRunner
{
    public function run()
    {
        echo 'EXECUTED';
    }
}
