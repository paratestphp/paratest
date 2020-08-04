<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

final class EmptyRunnerStub extends BaseRunner
{
    public const OUTPUT = 'EmptyRunnerStub EXECUTED';

    public function run(): void
    {
        $this->printer->start($this->options);
        $this->output->write(self::OUTPUT);
    }

    protected function beforeLoadChecks(): void
    {
    }
}
