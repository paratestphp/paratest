<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Commands;

use InvalidArgumentException;
use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Runners\PHPUnit\EmptyRunnerStub;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\XmlConfiguration\Exception;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;

use function chdir;
use function getcwd;

final class ParaTestCommandTest extends TestBase
{
    /** @var CommandTester */
    private $commandTester;
    /** @var string */
    private $cwd;

    public function setUp(): void
    {
        $application = ParaTestCommand::applicationFactory();
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));

        $cwd = getcwd();
        static::assertIsString($cwd);
        $this->cwd = $cwd;
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
    }

    public function testApplicationFactory(): void
    {
        $application = ParaTestCommand::applicationFactory();
        $commands    = $application->all();

        static::assertArrayHasKey(ParaTestCommand::COMMAND_NAME, $commands);
        static::assertInstanceOf(ParaTestCommand::class, $commands[ParaTestCommand::COMMAND_NAME]);
    }

    /**
     * Should be configured from the ParaTest command
     * as well as the Tester it is composed of.
     */
    public function testConfiguredDefinitionWithPHPUnitTester(): void
    {
        $options  = [
            // Arguments
            new InputArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'
            ),

            // Default Symfony options
            new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message.'),

            // Custom options
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
                'coverage-crap4j',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Crap4J XML format.'
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
                'whitelist',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to add to the coverage whitelist.'
            ),
        ];
        $expected = new InputDefinition($options);

        $application = ParaTestCommand::applicationFactory();
        $command     = $application->get(ParaTestCommand::COMMAND_NAME);
        $command->mergeApplicationDefinition();
        $definition = $command->getDefinition();

        static::assertEquals($expected, $definition);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied(): void
    {
        static::expectException(Exception::class);
        static::expectDeprecationMessage('Could not read "nope.xml"');

        $this->commandTester->execute(['--configuration' => 'nope.xml']);
    }

    public function testDisplayHelpWithoutConfigNorPath(): void
    {
        chdir(__DIR__);

        $this->commandTester->execute([]);

        static::assertStringContainsString('Usage:', $this->commandTester->getDisplay());
    }

    public function testCustomRunnerMustBeAValidRunner(): void
    {
        static::expectException(InvalidArgumentException::class);

        $this->commandTester->execute(['--runner' => 'stdClass']);
    }

    /**
     * @dataProvider provideConfigurationDirectories
     */
    public function testGetPhpunitConfigFromDefaults(string $directory): void
    {
        chdir($directory);

        $this->commandTester->execute([
            '--runner' => EmptyRunnerStub::class,
        ]);

        static::assertStringContainsString($directory, $this->commandTester->getDisplay());
    }

    /**
     * @return array<string, string[]>
     */
    public function provideConfigurationDirectories(): array
    {
        return [
            'config-from-phpunit.xml' => [FIXTURES . DS . 'config-from-phpunit.xml'],
            'config-from-phpunit.xml.dist' => [FIXTURES . DS . 'config-from-phpunit.xml.dist'],
        ];
    }
}
