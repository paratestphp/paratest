<?php namespace ParaTest\Runners\PHPUnit;

class ResultPrinterTest extends \TestBase
{
    protected $printer;

    public function setUp()
    {
        $this->printer = new ResultPrinter();
    }

    public function testConstructor()
    {
        $this->assertEquals(array(), $this->getObjectValue($this->printer, 'suites'));
        $this->assertInstanceOf(
            'ParaTest\\Runners\\PHPUnit\\ResultInterpreter',
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

    protected function getStartOutput(Options $options)
    {
        ob_start();
        $this->printer->start($options);
        $contents = ob_get_clean();
        return $contents;
    }
}
