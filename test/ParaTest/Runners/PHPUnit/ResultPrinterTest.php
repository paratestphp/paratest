<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Logging\LogInterpreter;
use ParaTest\Parser\ParsedFunction;

class ResultPrinterTest extends \TestBase
{
    protected $printer;
    protected $interpreter;

    public function setUp()
    {
        $this->interpreter = new LogInterpreter();
        $this->printer = new ResultPrinter($this->interpreter);
        chdir(__DIR__);
        if(file_exists('myconfig.xml'))
            unlink('myconfig.xml');
    }

    public function testConstructor()
    {
        $this->assertEquals(array(), $this->getObjectValue($this->printer, 'suites'));
        $this->assertInstanceOf(
            'ParaTest\\Logging\\LogInterpreter',
            $this->getObjectValue($this->printer, 'results')
        );
    }

    public function testAddTestShouldaddTest()
    {
        $suite = new Suite('/path/to/ResultSuite.php', array());

        $this->printer->addTest($suite);

        $this->assertEquals(array($suite), $this->getObjectValue($this->printer, 'suites'));
    }

    public function testAddTestReturnsSelf()
    {
        $suite = new Suite('/path/to/ResultSuite.php', array());

        $self = $this->printer->addTest($suite);

        $this->assertSame($this->printer, $self);
    }

    public function testStartPrintsOptionInfo()
    {
        $options = new Options();
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 5 processes with %s\n\n", $options->phpunit);
        $this->assertEquals($expected, $contents);
    }

    public function testStartSetsWidthAndMaxColumn()
    {
        $funcs = array();
        for($i = 0; $i < 120; $i++)
            $funcs[] = new ParsedFunction('doc', 'public', 'function' . $i);
        $suite = new Suite('/path', $funcs);
        $this->printer->addTest($suite);
        $this->getStartOutput(new Options());
        $numTestsWidth = $this->getObjectValue($this->printer, 'numTestsWidth');
        $maxColumn = $this->getObjectValue($this->printer, 'maxColumn');
        $this->assertEquals(3, $numTestsWidth);
        $this->assertEquals(63, $maxColumn);
    }

    public function testStartPrintsOptionInfoAndConfigurationDetailsIfConfigFilePresent()
    {
        file_put_contents('myconfig.xml', '<root />');
        $options = new Options(array('configuration' => 'myconfig.xml'));
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 5 processes with %s\n\nConfiguration read from %s\n\n",
                            $options->phpunit,
                            __DIR__ . DS . 'myconfig.xml');
        $this->assertEquals($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithFunctionalMode()
    {
        $options = new Options(array('functional' => true));
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 5 processes with %s. Functional mode is on\n\n", $options->phpunit);
        $this->assertEquals($expected, $contents);
    }

    public function testStartPrintsOptionInfoWithSingularForOneProcess()
    {
        $options = new Options(array('processes' => 1));
        $contents = $this->getStartOutput($options);
        $expected = sprintf("\nRunning phpunit in 1 process with %s\n\n", $options->phpunit);
        $this->assertEquals($expected, $contents);
    }

    public function testAddSuiteAddsFunctionCountToTotalTestCases()
    {
        $suite = new Suite('/path', array(
            new ParsedFunction('doc', 'public', 'funcOne'),
            new ParsedFunction('doc', 'public', 'funcTwo')
        ));
        $this->printer->addTest($suite);
        $this->assertEquals(2, $this->printer->getTotalCases());
    }

    public function testAddTestMethodIncrementsCountByOne()
    {
        $method = new TestMethod('/path', 'testThisMethod');
        $this->printer->addTest($method);
        $this->assertEquals(1, $this->printer->getTotalCases());
    }

    protected function getStartOutput(Options $options)
    {
        ob_start();
        $this->printer->start($options);
        $contents = ob_get_clean();
        return $contents;
    }
}
