<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use ParaTest\Console\Commands\ParaTestCommand;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class ParaTestInvoker
{
    /** @var string|null  */
    private $path;

    public function __construct(?string $path)
    {
        $this->path = $path;
    }

    /**
     * Runs the command, returns the proc after it's done.
     *
     * @param array<string, string|bool|int> $options
     */
    public function execute(array $options, ?string $cwd = null): RunnerResult
    {
        $application = ParaTestCommand::applicationFactory($cwd ?? PARATEST_ROOT);
        $application->add(new HelpCommand());

        $commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));

        if ($this->path !== null) {
            $options['path'] = $this->path;
        }

        $commandTester->execute($options);

        return new RunnerResult(
            $commandTester->getStatusCode(),
            $commandTester->getDisplay()
        );
    }
}
