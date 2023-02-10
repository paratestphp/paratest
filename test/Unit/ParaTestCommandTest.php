<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit;

use InvalidArgumentException;
use Jean85\PrettyVersions;
use ParaTest\ParaTestCommand;
use ParaTest\Tests\TmpDirCreator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function assert;
use function chdir;
use function getcwd;
use function uniqid;

/** @internal */
#[CoversClass(ParaTestCommand::class)]
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

        self::assertArrayHasKey(ParaTestCommand::COMMAND_NAME, $commands);
        self::assertInstanceOf(ParaTestCommand::class, $commands[ParaTestCommand::COMMAND_NAME]);
        self::assertSame(
            'ParaTest <info>' . PrettyVersions::getVersion('brianium/paratest')->getPrettyVersion() . '</info>',
            $application->getLongVersion(),
        );
    }

    public function testDisplayHelpWithoutConfigNorPath(): void
    {
        $this->commandTester->execute([]);

        self::assertStringContainsString('Usage:', $this->commandTester->getDisplay());
    }

    public function testCustomRunnerMustBeAValidClass(): void
    {
        $className = uniqid('invalid_class_name_');
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage($className);

        $this->commandTester->execute([
            '--runner' => $className,
            'path' => $this->tmpDir,
        ]);
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

    public function testAllowCustomRunners(): void
    {
        $returnCode = $this->commandTester->execute([
            '--runner' => CustomRunner::class,
            'path' => $this->tmpDir,
        ]);

        self::assertSame(CustomRunner::EXIT_CODE, $returnCode);
    }
}
