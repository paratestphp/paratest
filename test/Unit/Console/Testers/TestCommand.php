<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Testers;

use Symfony\Component\Console\Command\Command;

class TestCommand extends Command
{
    public function __construct()
    {
        parent::__construct('testcommand');
    }
}
