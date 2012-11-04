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

    public function testGetCaseStatusWherePassing()
    {
        $case = array('pass' => true, 'errors' => 0, 'failures' => 0);
        $this->assertEquals('.', $this->interpreter->getCaseStatus($case));
    }

    public function testGetCaseStatusWhereErrorsPresent()
    {
        $case = array('pass' => false, 'errors' => 2, 'failures' => 1);
        $this->assertEquals('E', $this->interpreter->getCaseStatus($case));
    }

    public function testGetCaseStatusWhereFailuresPresentAndNoErrors()
    {
        $case = array('pass' => false, 'errors' => 0, 'failures' => 2);
        $this->assertEquals('F', $this->interpreter->getCaseStatus($case));
    }

    protected function getMockReader()
    {
        return $this->getMockBuilder('ParaTest\\LogReaders\\JUnitXmlLogReader')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
}