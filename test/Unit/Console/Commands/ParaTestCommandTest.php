<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Commands;

use InvalidArgumentException;
use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Runners\PHPUnit\EmptyRunnerStub;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\XmlConfiguration\Exception;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class ParaTestCommandTest extends TestBase
{
    /** @var CommandTester */
    private $commandTester;

    public function setUp(): void
    {
        $application = ParaTestCommand::applicationFactory(PARATEST_ROOT);
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));
    }

    public function testApplicationFactory(): void
    {
        $application = ParaTestCommand::applicationFactory(PARATEST_ROOT);
        $commands    = $application->all();

        static::assertArrayHasKey(ParaTestCommand::COMMAND_NAME, $commands);
        static::assertInstanceOf(ParaTestCommand::class, $commands[ParaTestCommand::COMMAND_NAME]);
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied(): void
    {
        static::expectException(Exception::class);
        static::expectExceptionMessage(sprintf('Could not read "%s%snope.xml"', PARATEST_ROOT, DS));

        $this->commandTester->execute(['--configuration' => 'nope.xml']);
    }

    public function testDisplayHelpWithoutConfigNorPath(): void
    {
        $application = ParaTestCommand::applicationFactory(__DIR__);
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));
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
        $application = ParaTestCommand::applicationFactory($directory);
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));
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
