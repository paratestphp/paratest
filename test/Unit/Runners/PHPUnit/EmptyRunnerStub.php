<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\BaseRunner;

final class EmptyRunnerStub extends BaseRunner
{
    public const OUTPUT = 'EmptyRunnerStub EXECUTED';

    public function run(): void
    {
        $this->printer->start();
        $this->output->write(self::OUTPUT);
    }

    protected function beforeLoadChecks(): void
    {
    }
}
