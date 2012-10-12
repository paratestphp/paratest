<?php namespace ParaTest\LogReaders;

class JUnitXmlLogReaderTest_SingleResultWithFailureTest extends JUnitXmlLogReaderTest_BaseJUnitXmlLogReaderTestBase
{
    protected $fixture = 'single-wfailure.xml';
    protected $expectedTotalTests = 3;
    protected $expectedTotalAssertions = 3;
    protected $expectedTotalFailures = 1;
    protected $expectedTime = '0.005895';
    protected $expectedErrors = 0;
}