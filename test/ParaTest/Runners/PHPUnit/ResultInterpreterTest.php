<?php namespace ParaTest\Runners\PHPUnit;

class ResultInterpreterTest extends \TestBase
{
    protected $interpreter;

    public function setUp()
    {
        $this->interpreter = new ResultInterpreter();
    }

    public function testConstructor()
    {
        $this->assertEquals(array(), $this->getObjectValue($this->interpreter, 'readers'));
    }

    public function testAddReaderIncrementsReaders()
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $this->assertEquals(1, sizeof($this->getObjectValue($this->interpreter, 'readers')));
    }

    public function testAddReaderReturnsSelf()
    {
        $reader = $this->getMockReader();
        $self = $this->interpreter->addReader($reader);
        $this->assertSame($self, $this->interpreter);
    }

    protected function getMockReader()
    {
        return $this->getMockBuilder('ParaTest\\Logging\\JUnit\\Reader')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}