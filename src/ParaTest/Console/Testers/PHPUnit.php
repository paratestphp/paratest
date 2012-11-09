<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    ParaTest\Runners\PHPUnit\Runner;

class PHPUnit extends Tester
{
    public function configure(Command $command)
    {
        $command
            ->addOption('phpunit', null, InputOption::VALUE_OPTIONAL, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>')
            ->addOption('bootstrap', null, InputOption::VALUE_OPTIONAL, 'The bootstrap file to be used by PHPUnit.')
            ->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Only runs tests from the specified group(s).')
            ->addArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default:current directory)</comment>');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $options = $this->getOptions($input);
        if(isset($options['bootstrap']) && file_exists($options['bootstrap']))
            require_once $options['bootstrap'];
        $options = ($path) ? array_merge(array('path' => $path), $options) : $options;
        $runner = new Runner($options);
        $runner->run();
    }
}