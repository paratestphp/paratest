<?php

class HasOtherWarningsTest extends PHPUnit\Framework\TestCase
{
    /**
     * The test will fail on the line that tries to mock the non-existent method
     *
     * @return void
     */
    public function testMockingNonExistentMethod()
    {
        $mock = $this->getMockBuilder(\RuntimeException::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock
            ->method('nonExistentMethodMock')
            ->will($this->returnValue(true));

        $this->assertTrue(true);
    }
}
