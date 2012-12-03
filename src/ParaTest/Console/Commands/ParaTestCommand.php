<?php namespace ParaTest\Console\Commands;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    ParaTest\Console\Testers\Tester;

class ParaTestCommand extends Command
{
    protected $tester;

    public function __construct(Tester $tester)
    {
        parent::__construct('paratest');
        $this->tester = $tester;
        $this->tester->configure($this);
    }

    protected function configure()
    {
        $this
            ->addOption('processes', 'p', InputOption::VALUE_REQUIRED, 'The number of test processes to run.', 5)
            ->addOption('functional', 'f', InputOption::VALUE_NONE, 'Run methods instead of suites in separate processes.')
            ->addOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->tester->execute($input, $output);
    }
}