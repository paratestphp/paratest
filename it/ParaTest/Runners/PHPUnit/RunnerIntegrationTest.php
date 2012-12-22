<?php namespace ParaTest\Runners\PHPUnit;

class RunnerIntegrationTest extends \TestBase
{
    protected $runner;
    protected $options;

    public function setUp()
    {
        $this->options = array(
            'path' => FIXTURES . DS . 'tests',
            'phpunit' => PHPUNIT,
            'bootstrap' => BOOTSTRAP
        );
        $this->runner = new Runner($this->options);
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        $tempdir = sys_get_temp_dir();
        $countBefore = count(glob($tempdir . DS . 'PT_*'));
        //dont want the output mucking up the test results
        ob_start();
        $this->runner->run();
        ob_end_clean();
        $countAfter = count(glob($tempdir . DS . 'PT_*'));
        $this->assertEquals($countAfter, $countBefore, "Test Runner failed to clean up the 'PT_*' file in " . $tempdir);
    }

    public function testLogJUnitCreatesXmlFile()
    {
        $outputPath = FIXTURES . DS . 'logs' . DS . 'test-output.xml';
        $this->options['log-junit'] = $outputPath;
        $runner = new Runner($this->options);
        ob_start();
        $runner->run();
        ob_end_clean();
        $this->assertTrue(file_exists($outputPath));
        $this->assertXml($outputPath);
        if(file_exists($outputPath)) unlink($outputPath);
    }

    public function assertXml($path)
    {
        $doc = simplexml_load_file($path);
        $suites = $doc->xpath('//testsuite');
        $cases = $doc->xpath('//testcase');
        $failures = $doc->xpath('//failure');
        $errors = $doc->xpath('//error');
        $this->assertEquals(10, sizeof($suites));
        $this->assertEquals(31, sizeof($cases));
        $this->assertEquals(4, sizeof($failures));
        $this->assertEquals(1, sizeof($errors));
    }
}