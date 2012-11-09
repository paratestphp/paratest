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
        $expected = new InputDefinition(array(
            new InputOption('processes', 'p', InputOption::VALUE_OPTIONAL, 'The number of test processes to run.', 5),
            new InputOption('functional', 'f', InputOption::VALUE_NONE, 'Run methods instead of suites in separate processes.'),
            new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('phpunit', null, InputOption::VALUE_OPTIONAL, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'),
            new InputOption('bootstrap', null, InputOption::VALUE_OPTIONAL, 'The bootstrap file to be used by PHPUnit.'),
            new InputOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Only runs tests from the specified group(s).'),
            new InputArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default: current directory)</comment>')
        ));
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