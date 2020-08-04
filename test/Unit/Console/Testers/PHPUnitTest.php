<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Testers;

use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Console\Testers\PHPUnit;
use ParaTest\Tests\TestBase;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

use function getcwd;
use function uniqid;

final class PHPUnitTest extends TestBase
{
    public function testConfigureAddsOptionsAndArgumentsToCommand(): void
    {
        $testCommand = new TestCommand();
        $definition  = new InputDefinition([
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
            new InputOption('path', null, InputOption::VALUE_REQUIRED, 'An alias for the path argument.'),
            new InputOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Filter which testsuite to run'),
        ]);
        $tester      = new PHPUnit();
        $tester->configure($testCommand);
        static::assertEquals($definition, $testCommand->getDefinition());
    }

    public function testRequireBootstrapIsChdirResistent(): void
    {
        $file   = __DIR__ . '/../../../fixtures/chdirBootstrap.php';
        $tester = new PHPUnit();
        $cwd    = getcwd();

        $tester->requireBootstrap($file);
        static::assertEquals($cwd, getcwd());
    }

    public function testWithBootstrapThatDoesNotExist(): void
    {
        $file   = __DIR__ . '/' . uniqid('bootstrap_');
        $tester = new PHPUnit();

        self::expectException(RuntimeException::class);
        self::expectDeprecationMessageMatches('/Bootstrap specified but could not be found/');

        $tester->requireBootstrap($file);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied(): void
    {
        $tester  = new PHPUnit();
        $command = new ParaTestCommand($tester);
        $input   = new ArgvInput([], $command->getDefinition());
        $input->setOption('configuration', 'nope.xml');
        $output = new BufferedOutput();

        $tester->execute($input, $output);

        static::assertStringContainsString('Could not read "nope.xml"', $output->fetch());
    }
}
