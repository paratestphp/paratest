<?php

declare(strict_types=1);

namespace ParaTest\Console\Testers;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\BaseRunner;
use ParaTest\Runners\PHPUnit\Configuration;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Util\Str;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function array_merge;
use function chdir;
use function class_exists;
use function file_exists;
use function getcwd;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;

use const DIRECTORY_SEPARATOR;

/**
 * Creates the interface for PHPUnit testing
 */
class PHPUnit extends Tester
{
    /**
     * @see \PHPUnit\Util\Configuration
     * @see https://github.com/sebastianbergmann/phpunit/commit/80754cf323fe96003a2567f5e57404fddecff3bf
     */
    private const TEST_SUITE_FILTER_SEPARATOR = ',';

    /** @var Command */
    protected $command;

    /**
     * Configures the ParaTestCommand with PHPUnit specific
     * definitions.
     */
    public function configure(Command $command): void
    {
        $command
            ->addOption(
                'phpunit',
                null,
                InputOption::VALUE_REQUIRED,
                'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'
            )
            ->addOption(
                'runner',
                null,
                InputOption::VALUE_REQUIRED,
                'Runner, WrapperRunner or SqliteRunner. <comment>(default: Runner)</comment>'
            )
            ->addOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'The bootstrap file to be used by PHPUnit.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'The PHPUnit configuration file to use.')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).')
            ->addOption(
                'exclude-group',
                null,
                InputOption::VALUE_REQUIRED,
                'Don\'t run tests from the specified group(s).'
            )
            ->addOption(
                'stop-on-failure',
                null,
                InputOption::VALUE_NONE,
                'Don\'t start any more processes after a failure.'
            )
            ->addOption(
                'log-junit',
                null,
                InputOption::VALUE_REQUIRED,
                'Log test execution in JUnit XML format to file.'
            )
            ->addOption('colors', null, InputOption::VALUE_NONE, 'Displays a colored bar as a test result.')
            ->addOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Filter which testsuite to run')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'
            )
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.');
        $this->command = $command;
    }

    /**
     * Executes the PHPUnit Runner. Will Display help if no config and no path
     * supplied.
     *
     * @return int|mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (! $this->hasConfig($input) && ! $this->hasPath($input)) {
            $this->displayHelp($input, $output);
        }

        $runner = $this->initializeRunner($input);

        $runner->run();

        return $runner->getExitCode();
    }

    /**
     * Returns whether or not a test path has been supplied
     * via option or regular input.
     */
    protected function hasPath(InputInterface $input): bool
    {
        $argument = $input->getArgument('path');
        $option   = $input->getOption('path');

        return $argument || $option;
    }

    /**
     * Is there a PHPUnit xml configuration present.
     */
    protected function hasConfig(InputInterface $input): bool
    {
        return $this->getConfig($input) !== false;
    }

    /**
     * @return Configuration|bool
     */
    protected function getConfig(InputInterface $input)
    {
        $cwd = getcwd() . DIRECTORY_SEPARATOR;

        if ($input->getOption('configuration')) {
            $configFilename = $input->getOption('configuration');
        } elseif (file_exists($cwd . 'phpunit.xml.dist')) {
            $configFilename = $cwd . 'phpunit.xml.dist';
        } elseif (file_exists($cwd . 'phpunit.xml')) {
            $configFilename = $cwd . 'phpunit.xml';
        } else {
            return false;
        }

        return new Configuration($configFilename);
    }

    /**
     * Displays help for the ParaTestCommand.
     */
    protected function displayHelp(InputInterface $input, OutputInterface $output): void
    {
        $help  = $this->command->getApplication()->find('help');
        $input = new ArrayInput(['command_name' => 'paratest']);
        $help->run($input, $output);
        exit(0);
    }

    /**
     * @return array
     *
     * @throws RuntimeException
     */
    public function getRunnerOptions(InputInterface $input): array
    {
        $path      = $input->getArgument('path');
        $options   = $this->getOptions($input);
        $bootstrap = $this->getBootstrapFile($input, $options);
        $this->requireBootstrap($bootstrap);

        if ($this->hasCoverage($options)) {
            $options['coverage-php'] = tempnam(sys_get_temp_dir(), 'paratest_');
        }

        if ($path) {
            $options = array_merge(['path' => $path], $options);
        }

        if (array_key_exists('testsuite', $options)) {
            $options['testsuite'] = Str::explodeWithCleanup(
                self::TEST_SUITE_FILTER_SEPARATOR,
                $options['testsuite']
            );
        }

        return $options;
    }

    /**
     * Require the bootstrap. If the file is specified, but does not exist
     * then an exception will be raised.
     *
     * @throws RuntimeException
     */
    public function requireBootstrap(string $file): void
    {
        if (! $file) {
            return;
        }

        if (! file_exists($file)) {
            $message = sprintf('Bootstrap specified but could not be found (%s)', $file);

            throw new RuntimeException($message);
        }

        $this->scopedRequire($file);
    }

    /**
     * This function limits the scope of a required file
     * so that variables defined in it do not break
     * this object's configuration.
     */
    protected function scopedRequire(string $file): void
    {
        $cwd = getcwd();
        require_once $file;
        chdir($cwd);
    }

    /**
     * Return whether or not code coverage information should be collected.
     *
     * @param array $options
     */
    protected function hasCoverage(array $options): bool
    {
        $isFileFormat = isset($options['coverage-html'])
            || isset($options['coverage-clover'])
            || isset($options['coverage-crap4j'])
            || isset($options['coverage-xml']);
        $isTextFormat = isset($options['coverage-text']);
        $isPHP        = isset($options['coverage-php']);

        return $isTextFormat || $isFileFormat && ! $isPHP;
    }

    /**
     * Fetch the path to the bootstrap file.
     *
     * @param array $options
     */
    protected function getBootstrapFile(InputInterface $input, array $options): string
    {
        if (isset($options['bootstrap'])) {
            return $options['bootstrap'];
        }

        if (! $this->hasConfig($input)) {
            return '';
        }

        $config    = $this->getConfig($input);
        $bootstrap = $config->getBootstrap();

        return $bootstrap ? $config->getConfigDir() . $bootstrap : '';
    }

    private function initializeRunner(InputInterface $input): BaseRunner
    {
        if ($input->getOption('runner')) {
            $runnerClass = $input->getOption('runner') ?: '';
            $runnerClass = class_exists($runnerClass)
                ? $runnerClass
                : '\\ParaTest\\Runners\\PHPUnit\\' . $runnerClass;
        } else {
            $runnerClass = Runner::class;
        }

        if (! class_exists($runnerClass)) {
            throw new InvalidArgumentException('Selected runner does not exist.');
        }

        return new $runnerClass($this->getRunnerOptions($input));
    }
}
