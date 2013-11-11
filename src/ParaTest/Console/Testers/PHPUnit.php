<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ParaTest\Runners\PHPUnit\Configuration;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Runners\PHPUnit\WrapperRunner;

/**
 * Class PHPUnit
 *
 * Creates the interface for PHPUnit testing
 *
 * @package ParaTest\Console\Testers
 */
class PHPUnit extends Tester
{
    /**
     * @var \ParaTest\Console\Commands\ParaTestCommand
     */
    protected $command;

    /**
     * Configures the ParaTestCommand with PHPUnit specific
     * definitions
     *
     * @param Command $command
     * @return mixed
     */
    public function configure(Command $command)
    {
        $command
            ->addOption('phpunit', null, InputOption::VALUE_REQUIRED, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>')
            ->addOption('runner', null, InputOption::VALUE_REQUIRED, 'Runner or WrapperRunner. <comment>(default: Runner)</comment>')
            ->addOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'The bootstrap file to be used by PHPUnit.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'The PHPUnit configuration file to use.')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).')
            ->addOption('log-junit', null, InputOption::VALUE_REQUIRED, 'Log test execution in JUnit XML format to file.')
            ->addOption('colors', null, InputOption::VALUE_NONE, 'Displays a colored bar as a test result.')
            ->addArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default: current directory)</comment>')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.');
        $this->command = $command;
    }

    /**
     * Executes the PHPUnit Runner. Will Display help if no config and no path
     * supplied
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if(!$this->hasConfig($input) && !$this->hasPath($input))
            $this->displayHelp($input, $output);
        if ($input->getOption('runner') === 'WrapperRunner') {
            $runner = new WrapperRunner($this->getRunnerOptions($input));
        } else {
            $runner = new Runner($this->getRunnerOptions($input));
        }
        $runner->run();
        return $runner->getExitCode();
    }

    /**
     * Returns whether or not a test path has been supplied
     * via option or regular input
     *
     * @param InputInterface $input
     * @return bool
     */
    protected function hasPath(InputInterface $input)
    {
        $argument = $input->getArgument('path');
        $option = $input->getOption('path');
        return $argument || $option;
    }

    /**
     * Is there a PHPUnit xml configuration present
     *
     * @param InputInterface $input
     * @return bool
     */
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
    public function getRunnerOptions(InputInterface $input)
    {
        $path = $input->getArgument('path');
        $options = $this->getOptions($input);

        if($this->hasConfig($input) && !isset($options['bootstrap'])) {
            $config = $this->getConfig($input);
            if($config->getBootstrap())
                $options['bootstrap'] = $config->getConfigDir() . $config->getBootstrap();
        }
        if(isset($options['bootstrap'])) {
            if(file_exists($options['bootstrap']))
                $this->requireBootstrap($options['bootstrap']);
            else
                throw new \RuntimeException(
                    sprintf('Bootstrap specified but could not be found (%s)',
                    $options['bootstrap']));
        }

        if ($this->hasCoverage($options) && !isset($options['coverage-php'])) {
            $options['coverage-php'] = sys_get_temp_dir() . '/will_be_overwritten.php';
        }

        $options = ($path) ? array_merge(array('path' => $path), $options) : $options;
        return $options;
    }

    /**
     * This function limits the scope affected by the bootstrap,
     * so that $options variable defined in it doesn't break
     * this object's configuration.
     */
    private function requireBootstrap($file)
    {
        require_once $file;
    }

    /**
     * @param $options
     * @return bool
     */
    protected function hasCoverage($options)
    {
        return isset($options['coverage-html']) || isset($options['coverage-clover']);
    }
}
