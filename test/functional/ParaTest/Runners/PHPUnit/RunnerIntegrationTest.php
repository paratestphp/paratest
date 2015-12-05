<?php namespace ParaTest\Runners\PHPUnit;

use ParaTest\Console\Commands\ParaTestCommand;

class RunnerIntegrationTest extends \TestBase
{
    /** @var Runner $runner */
    protected $runner;
    protected $options;

    public function setUp()
    {
        try {
            $coverage = new \PHP_CodeCoverage();
        } catch(\Exception $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $this->options = array(
            'path' => FIXTURES . DS . 'failing-tests',
            'phpunit' => PHPUNIT,
            'coverage-php' => sys_get_temp_dir() . DS . 'testcoverage.php',
            'bootstrap' => BOOTSTRAP
        );
        if (ParaTestCommand::isWhitelistSupported()) {
            $this->options['whitelist'] = FIXTURES . DS . 'failing-tests';
        }
        $this->runner = new Runner($this->options);
    }

    private function globTempDir($pattern)
    {
        return glob(sys_get_temp_dir() . DS . $pattern);
    }

    public function testRunningTestsShouldLeaveNoTempFiles()
    {
        $countBefore = count($this->globTempDir('PT_*'));
        $countCoverageBefore = count($this->globTempDir('CV_*'));

        ob_start();
        $this->runner->run();
        ob_end_clean();

        $countAfter = count($this->globTempDir('PT_*'));
        $countCoverageAfter = count($this->globTempDir('CV_*'));

        $this->assertEquals($countAfter, $countBefore,
            "Test Runner failed to clean up the 'PT_*' file in " . sys_get_temp_dir());
        $this->assertEquals($countCoverageAfter, $countCoverageBefore,
            "Test Runner failed to clean up the 'CV_*' file in " . sys_get_temp_dir());
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
        $this->assertJunitXmlIsCorrect($outputPath);
        if(file_exists($outputPath)) unlink($outputPath);
    }

    public function assertJunitXmlIsCorrect($path)
    {
        $doc = simplexml_load_file($path);
        $suites = $doc->xpath('//testsuite');
        $cases = $doc->xpath('//testcase');
        $failures = $doc->xpath('//failure');
        $errors = $doc->xpath('//error');

        // these numbers represent the tests in fixtures/failing-tests
        // so will need to be updated when tests are added or removed
        $this->assertCount(6, $suites);
        $this->assertCount(16, $cases);
        $this->assertCount(6, $failures);
        $this->assertCount(1, $errors);
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
