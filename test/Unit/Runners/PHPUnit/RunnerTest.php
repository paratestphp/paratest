<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\ResultPrinter;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Tests\TestBase;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use Symfony\Component\Console\Output\BufferedOutput;

use function getcwd;
use function uniqid;

final class RunnerTest extends TestBase
{
    /** @var Runner  */
    private $runner;
    /** @var BufferedOutput  */
    private $output;

    public function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->runner = new Runner(new Options([]), $this->output);
    }

    public function testConstructor(): void
    {
        $opts    = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner  = new Runner(new Options($opts), $this->output);
        $options = $this->getObjectValue($runner, 'options');

        static::assertEquals(4, $options->processes);
        static::assertEquals(FIXTURES . DS . 'tests', $options->path);
        static::assertEquals([], $this->getObjectValue($runner, 'pending'));
        static::assertEquals([], $this->getObjectValue($runner, 'running'));
        static::assertEquals(-1, $this->getObjectValue($runner, 'exitcode'));
        static::assertTrue($options->functional);
        //filter out processes and path and phpunit
        $config = (new Loader())->load(getcwd() . DS . 'phpunit.xml.dist');
        static::assertEquals(['bootstrap' => 'hello', 'configuration' => $config], $options->filtered);
        static::assertInstanceOf(LogInterpreter::class, $this->getObjectValue($runner, 'interpreter'));
        static::assertInstanceOf(ResultPrinter::class, $this->getObjectValue($runner, 'printer'));
    }

    public function testGetExitCode(): void
    {
        static::assertEquals(-1, $this->runner->getExitCode());
    }

    public function testConstructorAssignsTokens(): void
    {
        $opts   = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner(new Options($opts), $this->output);
        $tokens = $this->getObjectValue($runner, 'tokens');
        static::assertCount(4, $tokens);
    }

    public function testGetsNextAvailableTokenReturnsTokenIdentifier(): void
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => true],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false],
        ];
        $opts   = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner(new Options($opts), $this->output);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $tokenData = $this->call($runner, 'getNextAvailableToken');
        static::assertEquals(2, $tokenData['token']);
    }

    public function testGetNextAvailableTokenReturnsFalseWhenNoTokensAreAvailable(): void
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => false],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false],
        ];
        $opts   = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner(new Options($opts), $this->output);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $tokenData = $this->call($runner, 'getNextAvailableToken');
        static::assertFalse($tokenData);
    }

    public function testReleaseTokenMakesTokenAvailable(): void
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => false],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false],
        ];
        $opts   = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner(new Options($opts), $this->output);
        $this->setObjectValue($runner, 'tokens', $tokens);

        static::assertFalse($tokens[1]['available']);
        $this->call($runner, 'releaseToken', 1);
        $tokens = $this->getObjectValue($runner, 'tokens');
        static::assertTrue($tokens[1]['available']);
    }
}
