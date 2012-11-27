<?php namespace ParaTest\Runners\PHPUnit;

class RunnerTest extends \TestBase
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
        //dont want the output mucking up the test results
        ob_start();
        $this->runner->run();
        ob_end_clean();
        $tempdir = sys_get_temp_dir();
        $output = glob($tempdir . DS . 'PT_*');
        $this->assertTrue(sizeof($output) == 0);
    }

    public function testLogJUnitCreatsXmlFile()
    {
        $outputPath = FIXTURES . DS . 'logs' . DS . 'test-output.xml';
        $this->options['log-junit'] = $outputPath;
        $runner = new Runner($this->options);
        ob_start();
        $runner->run();
        ob_end_clean();
        $this->assertTrue(file_exists($outputPath));
        if(file_exists($outputPath)) unlink($outputPath);
    }
}