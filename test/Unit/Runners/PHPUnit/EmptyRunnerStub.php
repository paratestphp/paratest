<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class EmptyRunnerStub implements RunnerInterface
{
    public const OUTPUT = 'EmptyRunnerStub EXECUTED';
    /** @var Options */
    private $options;
    /** @var OutputInterface */
    private $output;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options = $options;
        $this->output  = $output;
    }

    public function run(): void
    {
        $this->output->write('Path: ' . $this->options->path() . "\n");
        $this->output->write('Configuration: ' . (($conf = $this->options->configuration()) !== null
            ? $conf->filename()
            : ''
        ) . "\n");
        $this->output->write(self::OUTPUT . "\n");
    }

    public function getExitCode(): int
    {
        return 0;
    }
}
