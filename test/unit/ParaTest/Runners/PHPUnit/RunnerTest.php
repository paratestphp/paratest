<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
{
    protected $runner;
    protected $files;
    protected $testDir;

    public function setUp()
    {
        $this->runner = new Runner();
    }

    public function testConstructor()
    {
        $opts = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner($opts);
        $options = $this->getObjectValue($runner, 'options');

        $this->assertEquals(4, $options->processes);
        $this->assertEquals(FIXTURES . DS . 'tests', $options->path);
        $this->assertEquals([], $this->getObjectValue($runner, 'pending'));
        $this->assertEquals([], $this->getObjectValue($runner, 'running'));
        $this->assertEquals(-1, $this->getObjectValue($runner, 'exitcode'));
        $this->assertTrue($options->functional);
        //filter out processes and path and phpunit
        $config = new Configuration(getcwd() . DS . 'phpunit.xml.dist');
        $this->assertEquals(['bootstrap' => 'hello', 'configuration' => $config], $options->filtered);
        $this->assertInstanceOf('ParaTest\\Logging\\LogInterpreter', $this->getObjectValue($runner, 'interpreter'));
        $this->assertInstanceOf('ParaTest\\Runners\\PHPUnit\\ResultPrinter', $this->getObjectValue($runner, 'printer'));
    }

    public function testGetExitCode()
    {
        $this->assertEquals(-1, $this->runner->getExitCode());
    }

    public function testConstructorAssignsTokens()
    {
        $opts = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner($opts);
        $tokens = $this->getObjectValue($runner, 'tokens');
        $this->assertEquals(4, count($tokens));
    }

    public function testGetsNextAvailableTokenReturnsTokenIdentifier()
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => true],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false]
        ];
        $opts = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $tokenData = $this->call($runner, 'getNextAvailableToken');
        $this->assertEquals(2, $tokenData['token']);
    }

    public function testGetNextAvailableTokenReturnsFalseWhenNoTokensAreAvailable()
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => false],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false]
        ];
        $opts = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $tokenData = $this->call($runner, 'getNextAvailableToken');
        $this->assertTrue($tokenData === false);
    }

    public function testReleaseTokenMakesTokenAvailable()
    {
        $tokens = [
            0 => ['token' => 0, 'unique' => uniqid(), 'available' => false],
            1 => ['token' => 1, 'unique' => uniqid(), 'available' => false],
            2 => ['token' => 2, 'unique' => uniqid(), 'available' => false],
            3 => ['token' => 3, 'unique' => uniqid(), 'available' => false]
        ];
        $opts = ['processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true];
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $this->assertFalse($tokens[1]['available']);
        $this->call($runner, 'releaseToken', 1);
        $tokens = $this->getObjectValue($runner, 'tokens');
        $this->assertTrue($tokens[1]['available']);
    }
}