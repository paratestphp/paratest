<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

abstract class Tester
{
    abstract public function configure(Command $command);

    abstract public function execute(InputInterface $input, OutputInterface $output);

    protected function getOptions(InputInterface $input)
    {
        $options = $input->getOptions();
        foreach($options as $key => $value)
            if(empty($options[$key])) unset($options[$key]);
        return $options;
    }
}