<?php namespace ParaTest\Logging;

class LogInterpreterTest extends \TestBase
{
    protected $interpreter;

    public function setUp()
    {
        $this->interpreter = new LogInterpreter();
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

    public function testGetReaders()
    {
        $reader = $this->getMockReader();
        $this->interpreter->addReader($reader);
        $readers = $this->interpreter->getReaders();
        $this->assertEquals(1, sizeof($readers));
        $this->assertSame($reader, $readers[0]);
    }

    protected function getMockReader()
    {
        return $this->getMockBuilder('ParaTest\\Logging\\JUnit\\Reader')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}