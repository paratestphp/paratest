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
    /** @var int */
    private $runs;

    public function __construct(Options $options, OutputInterface $output)
    {
        $this->options = $options;
        $this->output  = $output;
        $this->runs    = 0;
    }

    public function run(): void
    {
        $this->runs++;
        $this->output->writeln('Path: ' . $this->options->path());
        $this->output->writeln('Configuration: ' . (($conf = $this->options->configuration()) !== null
            ? $conf->filename()
            : ''
        ));

        // Single-run is self-completed
        if ($this->options->repeat() !== 1) {
            return;
        }

        $this->complete();
    }

    public function complete(): void
    {
        $this->output->writeln(self::OUTPUT . ' ' . $this->runs . ' TIMES');
    }

    public function getExitCode(): int
    {
        return 0;
    }
}
