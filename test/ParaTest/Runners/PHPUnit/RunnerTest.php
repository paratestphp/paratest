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
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $options = $this->getObjectValue($runner, 'options');

        $this->assertEquals(4, $options->processes);
        $this->assertEquals(FIXTURES . DS . 'tests', $options->path);
        $this->assertEquals(array(), $this->getObjectValue($runner, 'pending'));
        $this->assertEquals(array(), $this->getObjectValue($runner, 'running'));
        $this->assertEquals(-1, $this->getObjectValue($runner, 'exitcode'));
        $this->assertTrue($options->functional);
        //filter out processes and path and phpunit
        $config = new Configuration(getcwd() . DS . 'phpunit.xml.dist');
        $this->assertEquals(array('bootstrap' => 'hello', 'configuration' => $config), $options->filtered);
        $this->assertInstanceOf('ParaTest\\Logging\\LogInterpreter', $this->getObjectValue($runner, 'interpreter'));
        $this->assertInstanceOf('ParaTest\\Runners\\PHPUnit\\ResultPrinter', $this->getObjectValue($runner, 'printer'));
    }

    public function testGetExitCode()
    {
        $this->assertEquals(-1, $this->runner->getExitCode());
    }

    public function testConstructorAssignsTokens()
    {
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $tokens = $this->getObjectValue($runner, 'tokens');
        $this->assertEquals(4, count($tokens));
    }

    public function testGetsNextAvailableTokenReturnsTokenIdentifier()
    {
        $tokens = array(0 => false, 1 => false, 2 => true, 3 => false);
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $token = $this->call($runner, 'getNextAvailableToken');
        $this->assertEquals(2, $token);
    }

    public function testGetNextAvailableTokenReturnsFalseWhenNoTokensAreAvailable()
    {
        $tokens = array(0 => false, 1 => false, 2 => false, 3 => false);
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $token = $this->call($runner, 'getNextAvailableToken');
        $this->assertTrue($token === false);
    }

    public function testReleaseTokenMakesTokenAvailable()
    {
        $tokens = array(0 => false, 1 => false, 2 => false, 3 => false);
        $opts = array('processes' => 4, 'path' => FIXTURES . DS . 'tests', 'bootstrap' => 'hello', 'functional' => true);
        $runner = new Runner($opts);
        $this->setObjectValue($runner, 'tokens', $tokens);

        $this->assertFalse($tokens[1]);
        $this->call($runner, 'releaseToken', 1);
        $tokens = $this->getObjectValue($runner, 'tokens');
        $this->assertTrue($tokens[1]);
    }
}