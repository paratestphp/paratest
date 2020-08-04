<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

final class EmptyRunnerStub extends BaseRunner
{
    public function run(): void
    {
        $this->output->write('EXECUTED');
    }

    protected function beforeLoadChecks(): void
    {
    }
}
