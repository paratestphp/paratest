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
            'coverage-php' => sys_get_temp_dir() . DS . 'testcoverage.php',
            'bootstrap' => BOOTSTRAP
        );
        $this->runner = new Runner($this->options);
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        $countBefore = count(glob(sys_get_temp_dir() . DS . 'PT_*'));
        $countCoverageBefore = count(glob(sys_get_temp_dir() . DS . 'CV_*'));
        //dont want the output mucking up the test results
        ob_start();
        $this->runner->run();
        ob_end_clean();
        $countAfter = count(glob(sys_get_temp_dir() . DS . 'PT_*'));
        $countCoverageAfter = count(glob(sys_get_temp_dir() . DS . 'CV_*'));

        $this->assertEquals($countAfter, $countBefore, "Test Runner failed to clean up the 'PT_*' file in " . sys_get_temp_dir());
        $this->assertEquals($countCoverageAfter, $countCoverageBefore, "Test Runner failed to clean up the 'CV_*' file in " . sys_get_temp_dir());
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
        $this->assertEquals(12, sizeof($suites));
        $this->assertEquals(34, sizeof($cases));
        $this->assertEquals(4, sizeof($failures));
        $this->assertEquals(1, sizeof($errors));
    }

    protected function tearDown()
    {
        $testcoverageFile = sys_get_temp_dir() . DS . 'testcoverage.php';
        if (file_exists($testcoverageFile)) {
            unlink($testcoverageFile);
        }

        parent::tearDown();
    }
}
