<?php namespace ParaTest\Console;

use Symfony\Component\Console\Application,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputDefinition,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface,
    ParaTest\Console\Commands\ParaTestCommand,
    ParaTest\Console\Testers\PHPUnit;

class ParaTestApplication extends Application
{
    const NAME = 'ParaTest';
    const VERSION = '0.4.4';

    public function __construct()
    {
        parent::__construct(static::NAME, static::VERSION);
    }

    /**
     * @todo for now paratest will only run the phpunit command
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->add(new ParaTestCommand(new PHPUnit()));
        return parent::doRun($input, $output);
    }

    public function getDefinition()
    {
        return new InputDefinition(array(
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message.')
        ));
    }

    public function getCommandName(InputInterface $input)
    {
        return 'paratest';
    }
}
