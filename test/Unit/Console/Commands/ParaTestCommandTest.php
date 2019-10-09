<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Commands;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use ParaTest\Console\Testers\Tester;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParaTestCommandTest extends \ParaTest\Tests\TestBase
{
    protected $tester;
    protected $command;

    public function setUp(): void
    {
        $this->tester = new PHPUnit();
        $this->command = new ParaTestCommand($this->tester);
    }

    public function testConstructor()
    {
        $this->assertEquals('paratest', $this->command->getName());
        $this->assertSame($this->tester, $this->getObjectValue($this->command, 'tester'));
    }

    /**
     * Should be configured from the ParaTest command
     * as well as the Tester it is composed of.
     */
    public function testConfiguredDefinitionWithPHPUnitTester()
    {
        $options = [
            new InputOption(
                'processes',
                'p',
                InputOption::VALUE_REQUIRED,
                'The number of test processes to run.',
                'auto'
            ),
            new InputOption(
                'functional',
                'f',
                InputOption::VALUE_NONE,
                'Run test methods instead of classes in separate processes.'
            ),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption(
                'phpunit',
                null,
                InputOption::VALUE_REQUIRED,
                'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'
            ),
            new InputOption(
                'runner',
                null,
                InputOption::VALUE_REQUIRED,
                'Runner, WrapperRunner or SqliteRunner. <comment>(default: Runner)</comment>'
            ),
            new InputOption(
                'bootstrap',
                null,
                InputOption::VALUE_REQUIRED,
                'The bootstrap file to be used by PHPUnit.'
            ),
            new InputOption(
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'The PHPUnit configuration file to use.'
            ),
            new InputOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).'),
            new InputOption(
                'stop-on-failure',
                null,
                InputOption::VALUE_NONE,
                'Don\'t start any more processes after a failure.'
            ),
            new InputOption(
                'exclude-group',
                null,
                InputOption::VALUE_REQUIRED,
                'Don\'t run tests from the specified group(s).'
            ),
            new InputOption(
                'log-junit',
                null,
                InputOption::VALUE_REQUIRED,
                'Log test execution in JUnit XML format to file.'
            ),
            new InputOption('colors', null, InputOption::VALUE_NONE, 'Displays a colored bar as a test result.'),
            new InputArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'
            ),
            new InputOption(
                'no-test-tokens',
                null,
                InputOption::VALUE_NONE,
                'Disable TEST_TOKEN environment variables. <comment>(default: variable is set)</comment>'
            ),
            new InputOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.'),
            new InputOption(
                'coverage-clover',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Clover XML format.'
            ),
            new InputOption(
                'coverage-html',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in HTML format.'
            ),
            new InputOption(
                'coverage-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Serialize PHP_CodeCoverage object to file.'
            ),
            new InputOption(
                'coverage-text',
                null,
                InputOption::VALUE_NONE,
                'Generate code coverage report in text format.'
            ),
            new InputOption(
                'coverage-xml',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in PHPUnit XML format.'
            ),
            new InputOption(
                'coverage-test-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit the number of tests to record for each line of code. ' .
                    'Helps to reduce memory and size of coverage reports.'
            ),
            new InputOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Filter which testsuite to run'),
            new InputOption(
                'max-batch-size',
                'm',
                InputOption::VALUE_REQUIRED,
                'Max batch size (only for functional mode).',
                0
            ),
            new InputOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter (only for functional mode).'),
            new InputOption(
                'parallel-suite',
                null,
                InputOption::VALUE_NONE,
                'Run the suites of the config in parallel.'
            ),
            new InputOption(
                'passthru',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying test framework. ' .
                    'Example: --passthru="\'--prepend\' \'xdebug-filter.php\'"'
            ),
            new InputOption(
                'passthru-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying php process. ' .
                    'Example: --passthru-php="\'-d\' \'zend_extension=xdebug.so\'"'
            ),
            new InputOption(
                'verbose',
                'v',
                InputOption::VALUE_REQUIRED,
                'If given, debug output is printed. Example: --verbose=1'
            ),
            new InputOption(
                'whitelist',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to add to the coverage whitelist.'
            ),
        ];
        $expected = new InputDefinition($options);
        $definition = $this->command->getDefinition();
        $this->assertEquals($expected, $definition);
    }

    public function testExecuteInvokesTestersExecuteMethod()
    {
        $input = $this->getMockBuilder(InputInterface::class)->getMock();
        $output = $this->getMockBuilder(OutputInterface::class)->getMock();
        $tester = $this->getMockBuilder(Tester::class)->getMock();
        $tester
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->equalTo($input),
                $this->equalTo($output)
            )
        ;
        $command = new ParaTestCommand($tester);
        $command->execute($input, $output);
    }
}
