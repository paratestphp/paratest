<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use InvalidArgumentException;
use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use ParaTest\Runners\PHPUnit\BaseRunner;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Runner;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
     * @param array<string, string|int|true> $options
     * @param class-string<BaseRunner>|null  $runnerClass
     */
    public function execute(array $options = [], ?string $runnerClass = null): RunnerResult
    {
        if (isset($options['runner'])) {
            throw new InvalidArgumentException('Specify the runner as a parameter instead of an option');
        }

        if ($runnerClass === null) {
            $runnerClass = Runner::class;
        }

        $options['phpunit'] = PHPUNIT;
        $phpunitTester      = new PHPUnit();
        $paraTestCommand    = new ParaTestCommand($phpunitTester);
        $input              = new ArrayInput([], $paraTestCommand->getDefinition());
        foreach ($options as $key => $value) {
            $input->setOption($key, $value);
        }

        if ($this->path !== null) {
            $input->setArgument('path', $this->path);
        }

        $options = $phpunitTester->getRunnerOptions($input);

        $output = new BufferedOutput();

        $runner = new $runnerClass(new Options($options), $output);
        $runner->run();

        return new RunnerResult($runner, $output);
    }
}
