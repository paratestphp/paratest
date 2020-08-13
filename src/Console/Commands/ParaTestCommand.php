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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function file_exists;
use function is_string;
use function is_subclass_of;
use function sprintf;

final class ParaTestCommand extends Command
{
    public const COMMAND_NAME = 'paratest';

    /** @var string */
    private $cwd;

    public function __construct(string $cwd, ?string $name = null)
    {
        $this->cwd = $cwd;
        parent::__construct($name);
    }

    public static function applicationFactory(string $cwd): Application
    {
        $application = new Application();
        $command     = new self($cwd, self::COMMAND_NAME);

        $application->add($command);
        $application->setDefaultCommand($command->getName(), true);

        return $application;
    }

    /**
     * Ubiquitous configuration options for ParaTest.
     */
    protected function configure(): void
    {
        Options::setInputDefinition($this->getDefinition(), $this->cwd);
    }

    public function mergeApplicationDefinition(bool $mergeArgs = true): void
    {
    }

    /**
     * Executes the specified tester.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->hasConfig($input) && ! $this->hasPath($input)) {
            return $this->displayHelp($input, $output);
        }

        $options     = Options::fromConsoleInput($input, $this->cwd);
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
