<?php namespace ParaTest\Console\Commands;

use ParaTest\Console\Testers\PHPUnit,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputDefinition;

class ParaTestCommandTest extends \TestBase
{
    protected $tester;
    protected $command;

    public function setUp()
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
     * as well as the Tester it is composed of
     */
    public function testConfiguredDefinitionWithPHPUnitTester()
    {
        $options = array(
            new InputOption('processes', 'p', InputOption::VALUE_REQUIRED, 'The number of test processes to run.', 5),
            new InputOption('functional', 'f', InputOption::VALUE_NONE, 'Run methods instead of suites in separate processes.'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('phpunit', null, InputOption::VALUE_REQUIRED, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'),
            new InputOption('runner', null, InputOption::VALUE_REQUIRED, 'Runner or WrapperRunner. <comment>(default: Runner)</comment>'),
            new InputOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'The bootstrap file to be used by PHPUnit.'),
            new InputOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'The PHPUnit configuration file to use.'),
            new InputOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).'),
            new InputOption('stop-on-failure', null, InputOption::VALUE_NONE, 'Don\'t start any more processes after a failure.'),
            new InputOption('exclude-group', null, InputOption::VALUE_REQUIRED, 'Don\'t run tests from the specified group(s).'),
            new InputOption('log-junit', null, InputOption::VALUE_REQUIRED, 'Log test execution in JUnit XML format to file.'),
            new InputOption('colors', null, InputOption::VALUE_NONE, 'Displays a colored bar as a test result.'),
            new InputArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'),
            new InputOption('no-test-tokens', null, InputOption::VALUE_NONE, 'Disable TEST_TOKEN environment variables. <comment>(default: variable is set)</comment>'),
            new InputOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.'),
            new InputOption('coverage-clover', null, InputOption::VALUE_REQUIRED, 'Generate code coverage report in Clover XML format.'),
            new InputOption('coverage-html', null, InputOption::VALUE_REQUIRED, 'Generate code coverage report in HTML format.'),
            new InputOption('coverage-php', null, InputOption::VALUE_REQUIRED, 'Serialize PHP_CodeCoverage object to file.'),
            new InputOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Filter which testsuite to run'),
            new InputOption('max-batch-size', 'm', InputOption::VALUE_REQUIRED, 'Max batch size (only for functional mode).', 0),
            new InputOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter (only for functional mode).'),
        );
        if (ParaTestCommand::isWhitelistSupported()) {
            $options[] = new InputOption('whitelist', null, InputOption::VALUE_REQUIRED, 'Directory to add to the coverage whitelist.');
        }
        $expected = new InputDefinition($options);
        $definition = $this->command->getDefinition();
        $this->assertEquals($expected, $definition);
    }

    public function testExecuteInvokesTestersExecuteMethod()
    {
        $input = $this->getMock('Symfony\\Component\\Console\\Input\\InputInterface');
        $output = $this->getMock('Symfony\\Component\\Console\\Output\\OutputInterface');
        $tester = $this->getMock('ParaTest\\Console\\Testers\\Tester');
        $tester
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->equalTo($input),
                $this->equalTo($output)
            );
        $command = new ParaTestCommand($tester);
        $command->execute($input, $output);
    }
}
