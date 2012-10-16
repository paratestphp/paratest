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
        $this->assertEquals(0, $this->getObjectValue($this->printer, 'time'));
    }

    public function testAddSuiteShouldAddSuite()
    {
        $suite = new Suite('/path/to/ResultSuite.php', array());

        $this->printer->addSuite($suite);

        $this->assertEquals(array($suite), $this->getObjectValue($this->printer, 'suites'));
    }

    public function testAddSuiteReturnsSelf()
    {
        $suite = new Suite('/path/to/ResultSuite.php', array());

        $self = $this->printer->addSuite($suite);

        $this->assertSame($this->printer, $self);
    }

    public function testStartTimerPopulatesTime()
    {
        $this->printer->startTimer();

        $this->assertTrue($this->getObjectValue($this->printer, 'time') > 0);
    }

    public function testgetTimeReturnsTotalTimeAndResetsTime()
    {
        $this->printer->startTimer();
        usleep(10000);
        $total = $this->printer->getTime();
        $this->assertTrue($total > 0);
        $this->assertEquals(0, $this->getObjectValue($this->printer, 'time'));
    }
}
