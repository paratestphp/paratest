<?php namespace ParaTest\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;

class ParaTestApplication extends Application
{
    const NAME = 'ParaTest';
    const VERSION = '0.9.6';

    public function __construct()
    {
        parent::__construct(static::NAME, static::VERSION);
    }

    /**
     * Instantiates the specific Tester and runs it via the ParaTestCommand
     *
     * @todo for now paratest will only run the phpunit command
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->add(new ParaTestCommand(new PHPUnit()));
        return parent::doRun($input, $output);
    }

    /**
     * The default InputDefinition for the application. Leave it to specific
     * Tester objects for specifying further definitions
     *
     * @return InputDefinition
     */
    public function getDefinition()
    {
        return new InputDefinition(array(
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message.')
        ));
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    public function getCommandName(InputInterface $input)
    {
        return 'paratest';
    }
}
