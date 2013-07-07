<?php namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputDefinition,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument;

class PHPUnitTest extends \TestBase
{
    public function testConfigureAddsOptionsAndArgumentsToCommand()
    {
        $testCommand = new TestCommand();
        $definition = new InputDefinition(array(
            new InputOption('phpunit', null, InputOption::VALUE_REQUIRED, 'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'),
            new InputOption('runner', null, InputOption::VALUE_REQUIRED, 'Runner or WrapperRunner. <comment>(default: Runner)</comment>'),
            new InputOption('bootstrap', null, InputOption::VALUE_REQUIRED, 'The bootstrap file to be used by PHPUnit.'),
            new InputOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'The PHPUnit configuration file to use.'),
            new InputOption('group', 'g', InputOption::VALUE_REQUIRED, 'Only runs tests from the specified group(s).'),
            new InputOption('log-junit', null, InputOption::VALUE_REQUIRED, 'Log test execution in JUnit XML format to file.'),
            new InputOption('colors', null, InputOption::VALUE_NONE, 'Displays a colored bar as a test result.'),
            new InputArgument('path', InputArgument::OPTIONAL, 'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'),
            new InputOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.')
        ));
        $tester = new PHPUnit();
        $tester->configure($testCommand);
        $this->assertEquals($definition, $testCommand->getDefinition());
    }
}

class TestCommand extends Command 
{
    public function __construct() {
        parent::__construct("testcommand");
    }
}
