<?php

declare(strict_types=1);

namespace ParaTest\Console\Commands;

use ParaTest\Console\Testers\Tester;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParaTestCommand extends Command
{
    /**
     * @var \ParaTest\Console\Testers\Tester
     */
    protected $tester;

    public function __construct(Tester $tester)
    {
        parent::__construct('paratest');
        $this->tester = $tester;
        $this->tester->configure($this);
    }

    /**
     * Ubiquitous configuration options for ParaTest.
     */
    protected function configure()
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
            ->addOption(
                'verbose',
                'v',
                InputOption::VALUE_REQUIRED,
                'If given, debug output is printed. Example: --verbose=1'
            )
            ->addOption('whitelist', null, InputOption::VALUE_REQUIRED, 'Directory to add to the coverage whitelist.');
    }

    /**
     * Executes the specified tester.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|mixed|null
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->tester->execute($input, $output);
    }
}
