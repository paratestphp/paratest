<?php namespace ParaTest\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ParaTest\Console\Testers\Tester;

class ParaTestCommand extends Command
{
    /**
     * @var \ParaTest\Console\Testers\Tester
     */
    protected $tester;

    public function __construct(Tester $tester)
    {
        parent::__construct('paratest');
        $this->tester = $tester;
        $this->tester->configure($this);
    }

    /**
     * Ubiquitous configuration options for ParaTest
     */
    protected function configure()
    {
        $this
            ->addOption('processes', 'p', InputOption::VALUE_REQUIRED, 'The number of test processes to run.', 5)
            ->addOption('functional', 'f', InputOption::VALUE_NONE, 'Run methods instead of suites in separate processes.')
            ->addOption('no-test-tokens', null, InputOption::VALUE_NONE, 'Disable TEST_TOKEN environment variables. <comment>(default: variable is set)</comment>')
            ->addOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.');
    }

    /**
     * Executes the specified tester
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|mixed|null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->tester->execute($input, $output);
    }
}
