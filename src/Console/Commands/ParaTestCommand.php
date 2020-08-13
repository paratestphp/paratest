<?php

declare(strict_types=1);

namespace ParaTest\Console\Commands;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function file_exists;
use function is_string;
use function is_subclass_of;
use function sprintf;

final class ParaTestCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'ParaTest';

    public static function applicationFactory(): Application
    {
        $application = new Application('ParaTest');
        $command     = new ParaTestCommand();

        $application->add($command);
        $application->setDefaultCommand($command->getName(), true);

        return $application;
    }

    /**
     * Ubiquitous configuration options for ParaTest.
     */
    protected function configure(): void
    {
        $this
            ->addOption('processes', 'p', InputOption::VALUE_REQUIRED, 'The number of test processes to run.', 'auto')
            ->addOption(
                'functional',
                'f',
                InputOption::VALUE_NONE,
                'Run test methods instead of classes in separate processes.'
            )
            ->addOption(
                'no-test-tokens',
                null,
                InputOption::VALUE_NONE,
                'Disable TEST_TOKEN environment variables. <comment>(default: variable is set)</comment>'
            )
            ->addOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.')
            ->addOption(
                'coverage-clover',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Clover XML format.'
            )
            ->addOption(
                'coverage-crap4j',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Crap4J XML format.'
            )
            ->addOption(
                'coverage-html',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in HTML format.'
            )
            ->addOption('coverage-php', null, InputOption::VALUE_REQUIRED, 'Serialize PHP_CodeCoverage object to file.')
            ->addOption('coverage-text', null, InputOption::VALUE_NONE, 'Generate code coverage report in text format.')
            ->addOption(
                'coverage-xml',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in PHPUnit XML format.'
            )
            ->addOption(
                'coverage-test-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit the number of tests to record for each line of code. Helps to reduce memory and size of ' .
                    'coverage reports.'
            )
            ->addOption(
                'max-batch-size',
                'm',
                InputOption::VALUE_REQUIRED,
                'Max batch size (only for functional mode).',
                0
            )
            ->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter (only for functional mode).')
            ->addOption('parallel-suite', null, InputOption::VALUE_NONE, 'Run the suites of the config in parallel.')
            ->addOption(
                'passthru',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying test framework. Example: ' .
                    '--passthru="\'--prepend\' \'xdebug-filter.php\'"'
            )
            ->addOption(
                'passthru-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying php process. Example: --passthru-php="\'-d\' ' .
                    '\'zend_extension=xdebug.so\'"'
            )
            ->addOption('whitelist', null, InputOption::VALUE_REQUIRED, 'Directory to add to the coverage whitelist.')
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
    }

    /**
     * Executes the specified tester.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasConfig($input) && ! $this->hasPath($input)) {
            return $this->displayHelp($input, $output);
        }

        $options     = Options::fromConsoleInput($input);
        $runnerClass = $this->getRunnerClass($input);

        $runner = new $runnerClass($options, $output);
        $runner->run();

        return $runner->getExitCode();
    }

    /**
     * Returns whether or not a test path has been supplied
     * via option or regular input.
     */
    private function hasPath(InputInterface $input): bool
    {
        $argument = $input->getArgument('path');
        $option   = $input->getOption('path');

        return ($argument !== null && $argument !== '')
            || ($option !== null && $option !== '');
    }

    /**
     * Is there a PHPUnit xml configuration present.
     */
    private function hasConfig(InputInterface $input): bool
    {
        return $this->getConfig($input) !== null;
    }

    private function getConfig(InputInterface $input): ?string
    {
        if (is_string($path = $input->getOption('configuration')) && file_exists($path)) {
            $configFilename = $path;
        } elseif (file_exists($path = 'phpunit.xml')) {
            $configFilename = $path;
        } elseif (file_exists($path = 'phpunit.xml.dist')) {
            $configFilename = $path;
        } else {
            return null;
        }

        return $configFilename;
    }

    /**
     * Displays help for the ParaTestCommand.
     */
    private function displayHelp(InputInterface $input, OutputInterface $output): int
    {
        $help  = $this->getApplication()->find('help');
        $input = new ArrayInput(['command_name' => $this->getName()]);

        return $help->run($input, $output);
    }

    /**
     * @return class-string<RunnerInterface>
     */
    private function getRunnerClass(InputInterface $input): string
    {
        $runnerClass = Runner::class;
        $runner      = $input->getOption('runner');
        if ($runner !== null) {
            $runnerClass = $runner;
            $runnerClass = class_exists($runnerClass)
                ? $runnerClass
                : '\\ParaTest\\Runners\\PHPUnit\\' . $runnerClass;
        }

        if (! class_exists($runnerClass) || ! is_subclass_of($runnerClass, RunnerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Selected runner class "%s" does not exist or does not implement %s',
                $runnerClass,
                RunnerInterface::class
            ));
        }

        return $runnerClass;
    }
}
