<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    ParaTest\Runners\PHPUnit\Configuration,
    ParaTest\Runners\PHPUnit\Runner;

class PHPUnit extends Tester
{
    protected $command;

    public function configure(Command $command)
    {
        $command
            ->addOption('phpunit', null, InputOption::VALUE_REQUIRED, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>')
            ->addOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'The bootstrap file to be used by PHPUnit.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'The PHPUnit configuration file to use.')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).')
            ->addOption('log-junit', null, InputOption::VALUE_REQUIRED, 'Log test execution in JUnit XML format to file.')
            ->addArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default: current directory)</comment>')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.');
        $this->command = $command;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if(!$this->hasConfig($input) && !$this->hasPath($input))
            $this->displayHelp($input, $output);
        $runner = new Runner($this->getRunnerOptions($input));
        $runner->run();
        return $runner->getExitCode();
    }

    protected function hasPath(InputInterface $input)
    {
        $argument = $input->getArgument('path');
        $option = $input->getOption('path');
        return $argument || $option;
    }

    protected function hasConfig(InputInterface $input)
    {
        return (false !== $this->getConfig($input));
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return \ParaTest\Runners\PHPUnit\Configuration|boolean
     */
    protected function getConfig(InputInterface $input)
    {
        $cwd = getcwd() . DIRECTORY_SEPARATOR;

        if($input->getOption('configuration'))
            $configFilename = $input->getOption('configuration');
        elseif(file_exists($cwd . 'phpunit.xml.dist'))
            $configFilename = $cwd . 'phpunit.xml.dist';
        elseif(file_exists($cwd . 'phpunit.xml'))
            $configFilename = $cwd . 'phpunit.xml';
        else
            return false;
        
        return new Configuration($configFilename);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return array
     * @throws \RuntimeException
     */
    protected function getRunnerOptions(InputInterface $input)
    {
        $path = $input->getArgument('path');
        $options = $this->getOptions($input);
        if($this->hasConfig($input) && !isset($options['bootstrap']))
        {
            if($phpUnitBootstrap = $this->getConfig($input)->getBootstrap())
            {
                $options['bootstrap'] = $phpUnitBootstrap;
            }
        }
        if(isset($options['bootstrap']))
        {
            if(file_exists($options['bootstrap']))
            {
                require_once $options['bootstrap'];
            }
            else
            {
                throw new \RuntimeException(
                    sprintf('Bootstrap specified but could not be found (%s)',
                        $options['bootstrap']));
            }
        }
        
        $options = ($path) ? array_merge(array('path' => $path), $options) : $options;
        return $options;
    }
}