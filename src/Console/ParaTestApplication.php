<?php

declare(strict_types=1);

namespace ParaTest\Console;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParaTestApplication extends Application
{
    private const NAME = 'ParaTest';

    private const VERSION = '1.0.1';

    public function __construct()
    {
        parent::__construct(static::NAME, VersionProvider::getVersion(static::VERSION));
    }

    /**
     * Instantiates the specific Tester and runs it via the ParaTestCommand.
     *
     * @todo for now paratest will only run the phpunit command
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->add(new ParaTestCommand(new PHPUnit()));

        return parent::doRun($input, $output);
    }

    /**
     * The default InputDefinition for the application. Leave it to specific
     * Tester objects for specifying further definitions.
     *
     * @return InputDefinition
     */
    public function getDefinition(): InputDefinition
    {
        return new InputDefinition([
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message.'),
        ]);
    }

    /**
     * @param InputInterface $input
     *
     * @return string
     */
    public function getCommandName(InputInterface $input): string
    {
        return 'paratest';
    }
}
