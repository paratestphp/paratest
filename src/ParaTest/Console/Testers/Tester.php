<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Tester
 *
 * A base for Testers. A Tester is a specialized console
 * command for controlling a given tool - i.e PHPUnit
 *
 * @package ParaTest\Console\Testers
 */
abstract class Tester
{
    /**
     * Configures the ParaTestCommand with Tester specific
     * definitions
     *
     * @param Command $command
     * @return mixed
     */
    abstract public function configure(Command $command);

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    abstract public function execute(InputInterface $input, OutputInterface $output);

    /**
     * Returns non-empty options
     *
     * @param InputInterface $input
     * @return array
     */
    protected function getOptions(InputInterface $input)
    {
        $options = $input->getOptions();
        foreach($options as $key => $value)
            if(empty($options[$key])) unset($options[$key]);
        return $options;
    }

    /**
     * Displays help for the ParaTestCommand
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function displayHelp(InputInterface $input, OutputInterface $output)
    {
        $help = $this->command->getApplication()->find('help');
        $input = new ArrayInput(array('command_name' => 'paratest'));
        $help->run($input, $output);
        exit(0);
    }
}