<?php

declare(strict_types=1);

namespace ParaTest\Console\Commands;

use InvalidArgumentException;
use Jean85\PrettyVersions;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use ParaTest\Runners\PHPUnit\WrapperRunner;
use PHPUnit\Runner\Version;
use SebastianBergmann\Environment\Console;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function class_exists;
use function is_string;
use function is_subclass_of;
use function sprintf;

/** @internal */
final class ParaTestCommand extends Command
{
    public const COMMAND_NAME = 'paratest';

    private const KNOWN_RUNNERS = [
        'WrapperRunner' => WrapperRunner::class,
    ];

    /**
     * @param non-empty-string $cwd
     */
    public function __construct(
        private readonly string $cwd,
        ?string $name = null
    )
    {
        parent::__construct($name);
    }

    /**
     * @param non-empty-string $cwd
     */
    public static function applicationFactory(string $cwd): Application
    {
        $application = new Application();
        $command     = new self($cwd, self::COMMAND_NAME);

        $application->setName('ParaTest');
        $application->setVersion(PrettyVersions::getVersion('brianium/paratest')->getPrettyVersion());
        $application->add($command);
        $application->setDefaultCommand((string) $command->getName(), true);

        return $application;
    }

    /**
     * Ubiquitous configuration options for ParaTest.
     */
    protected function configure(): void
    {
        Options::setInputDefinition($this->getDefinition());
    }

    /**
     * {@inheritDoc}
     */
    public function mergeApplicationDefinition($mergeArgs = true): void
    {
    }

    /**
     * Executes the specified tester.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        assert($application !== null);

        $output->write(sprintf(
            "%s upon %s\n",
            $application->getLongVersion(),
            Version::getVersionString(),
        ));
        $output->write("\n");

        $options = Options::fromConsoleInput(
            $input,
            $this->cwd,
        );

        $runnerClass = $this->getRunnerClass($input);

        $runner = new $runnerClass($options, $output);
        assert($runner instanceof RunnerInterface);

        $runner->run();

        return $runner->getExitCode();
    }

    /** @return class-string<RunnerInterface> */
    private function getRunnerClass(InputInterface $input): string
    {
        $runnerClass = $input->getOption('runner');
        assert(is_string($runnerClass));
        $runnerClass = self::KNOWN_RUNNERS[$runnerClass] ?? $runnerClass;

        if (! class_exists($runnerClass) || ! is_subclass_of($runnerClass, RunnerInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'Selected runner class "%s" does not exist or does not implement %s',
                $runnerClass,
                RunnerInterface::class,
            ));
        }

        return $runnerClass;
    }
}
