<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use InvalidArgumentException;
use Jean85\PrettyVersions;
use ParaTest\ParaTestCommand;
use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function assert;
use function chdir;
use function getcwd;

/**
 * @internal
 *
 * @covers \ParaTest\ParaTestCommand
 */
final class ParaTestCommandTest extends TestCase
{
    private CommandTester $commandTester;
    /** @var non-empty-string */
    private string $tmpDir;
    /** @var non-empty-string */
    private string $getcwd;

    protected function setUp(): void
    {
        $getcwd = getcwd();
        assert($getcwd !== false);
        $this->getcwd = $getcwd;
        $this->tmpDir = (new TmpDirCreator())->create();
        chdir($this->tmpDir);
        $application = ParaTestCommand::applicationFactory($this->tmpDir);
        $application->add(new HelpCommand());

        $this->commandTester = new CommandTester($application->find(ParaTestCommand::COMMAND_NAME));
    }

    protected function tearDown(): void
    {
        chdir($this->getcwd);
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
            '--runner' => 'stdClass',
            'path' => $this->tmpDir,
        ]);
    }
}
