<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Console\Commands;

use InvalidArgumentException;
use Jean85\PrettyVersions;
use ParaTest\Console\Commands\ParaTestCommand;
use ParaTest\Tests\TestBase;
use ParaTest\Tests\Unit\Runners\PHPUnit\EmptyRunnerStub;
use PHPUnit\TextUI\XmlConfiguration\Exception;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

/**
 * @internal
 *
 * @covers \ParaTest\Console\Commands\ParaTestCommand
 */
final class ParaTestCommandTest extends TestBase
{
    private CommandTester $commandTester;

    public function setUpTest(): void
    {
        $application = ParaTestCommand::applicationFactory($this->tmpDir);
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));
    }

    public function testApplicationFactory(): void
    {
        $application = ParaTestCommand::applicationFactory($this->tmpDir);
        $commands    = $application->all();

        static::assertArrayHasKey(ParaTestCommand::COMMAND_NAME, $commands);
        static::assertInstanceOf(ParaTestCommand::class, $commands[ParaTestCommand::COMMAND_NAME]);
        static::assertSame(
            'ParaTest <info>' . PrettyVersions::getVersion('brianium/paratest')->getPrettyVersion() . '</info>',
            $application->getLongVersion(),
        );
    }

    public function testMessagePrintedWhenInvalidConfigFileSupplied(): void
    {
        static::expectException(Exception::class);
        static::expectExceptionMessage(sprintf('Could not read "%s%snope.xml"', $this->tmpDir, DS));

        $this->commandTester->execute(['--configuration' => 'nope.xml']);
    }

    public function testDisplayHelpWithoutConfigNorPath(): void
    {
        $this->commandTester->execute([]);

        static::assertStringContainsString('Usage:', $this->commandTester->getDisplay());
    }

    public function testCustomRunnerMustBeAValidRunner(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessageMatches('/stdClass/');

        $this->commandTester->execute([
            '--configuration' => $this->fixture('phpunit-file.xml'),
            '--runner' => 'stdClass',
        ]);
    }
}
