<?php
namespace ParaTest\Runners\PHPUnit;

use Exception;

class Worker
{
    private static $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );
    private $proc;
    private $pipes;
    private $inExecution = 0;
    private $isRunning = false;
    private $exitCode = null;
    private $commands = array();
    private $chunks = '';
    private $alreadyReadOutput = '';
    /**
     * @var ExecutableTest
     */
    private $currentlyExecuting;

    public function start($wrapperBinary, $token = 1, $uniqueToken = null)
    {
        $bin = 'PARATEST=1 ';
        if (is_numeric($token)) {
            $bin .= "TEST_TOKEN=$token ";
        }
        if ($uniqueToken) {
            $bin .= "UNIQUE_TEST_TOKEN=$uniqueToken ";
        }
        $bin .= "exec \"$wrapperBinary\"";
        $pipes = array();
        $this->proc = proc_open($bin, self::$descriptorspec, $pipes);
        $this->pipes = $pipes;
        $this->isRunning = true;
    }

    public function stdout()
    {
        return $this->pipes[1];
    }

    public function execute($testCmd)
    {
        $this->checkStarted();
        $this->commands[] = $testCmd;
        fwrite($this->pipes[0], $testCmd . "\n");
        $this->inExecution++;
    }

    public function assign(ExecutableTest $test, $phpunit, $phpunitOptions)
    {
        if ($this->currentlyExecuting !== null) {
            throw new Exception("Worker already has a test assigned - did you forget to call reset()?");
        }
        $this->currentlyExecuting = $test;
        $this->execute($test->command($phpunit, $phpunitOptions));
    }

    public function printFeedback($printer)
    {
        if ($this->currentlyExecuting !== null) {
            $printer->printFeedback($this->currentlyExecuting);
        }
    }

    public function reset()
    {
        $this->currentlyExecuting = null;
    }

    public function isStarted()
    {
        return $this->proc !== null && $this->pipes !== null;
    }

    private function checkStarted()
    {
        if (!$this->isStarted()) {
            throw new \RuntimeException("You have to start the Worker first!");
        }
    }

    public function stop()
    {
        fwrite($this->pipes[0], "EXIT\n");
        fclose($this->pipes[0]);
    }

    /**
     * This is an utility function for tests.
     * Refactor or write it only in the test case.
     */
    public function waitForFinishedJob()
    {
        if ($this->inExecution == 0) {
            return;
        }
        $tellsUsItHasFinished = false;
        stream_set_blocking($this->pipes[1], 1);
        while ($line = fgets($this->pipes[1])) {
            if (strstr($line, "FINISHED\n")) {
                $tellsUsItHasFinished = true;
                $this->inExecution--;
                break;
            }
        }
        if (!$tellsUsItHasFinished) {
            throw new \RuntimeException("The Worker terminated without finishing the job.");
        }
    }

    public function isFree()
    {
        $this->checkNotCrashed();
        $this->updateStateFromAvailableOutput();
        return $this->inExecution == 0;
    }

    /**
     * @deprecated
     * This function consumes a lot of CPU while waiting for
     * the worker to finish. Use it only in testing paratest
     * itself.
     */
    public function waitForStop()
    {
        $status = proc_get_status($this->proc);
        while ($status['running']) {
            $status = proc_get_status($this->proc);
            $this->setExitCode($status);
        }
    }

    public function getCoverageFileName()
    {
        if ($this->currentlyExecuting !== null) {
            return $this->currentlyExecuting->getCoverageFileName();
        } else {
            return null;
        }
    }

    private function setExitCode($status)
    {
        if (!$status['running']) {
            if ($this->exitCode === null) {
                $this->exitCode = $status['exitcode'];
            }
        }
    }

    public function isRunning()
    {
        $this->checkNotCrashed();
        $this->updateStateFromAvailableOutput();
        return $this->isRunning;
    }

    public function isCrashed()
    {
        if (!$this->isStarted()) {
            return false;
        }
        $status = proc_get_status($this->proc);

        $this->updateStateFromAvailableOutput();
        if (!$this->isRunning) {
            return false;
        }

        $this->setExitCode($status);
        if ($this->exitCode === null) {
            return false;
        }
        return $this->exitCode != 0;
    }

    private function checkNotCrashed()
    {
        if ($this->isCrashed()) {
            throw new \RuntimeException(
                "This worker has crashed. Last executed command: " . end($this->commands) . PHP_EOL
                . "Output:" . PHP_EOL
                . "----------------------" . PHP_EOL
                . $this->alreadyReadOutput . PHP_EOL
                . "----------------------" . PHP_EOL
                . $this->readAllStderr()
            );
        }
    }

    private function readAllStderr()
    {
        return stream_get_contents($this->pipes[2]);
    }

    /**
     * Have to read even incomplete lines to play nice with stream_select()
     * Otherwise it would continue to non-block because there are bytes to be read,
     * but fgets() won't pick them up.
     */
    private function updateStateFromAvailableOutput()
    {
        if (isset($this->pipes[1])) {
            stream_set_blocking($this->pipes[1], 0);
            while ($chunk = fread($this->pipes[1], 4096)) {
                $this->chunks .= $chunk;
                $this->alreadyReadOutput .= $chunk;
            }
            $lines = explode("\n", $this->chunks);
            // last element is not a complete line,
            // becomes part of a line completed later
            $this->chunks = $lines[count($lines) - 1];
            unset($lines[count($lines) - 1]);
            // delivering complete lines to this Worker
            foreach ($lines as $line) {
                $line .= "\n";
                if (strstr($line, "FINISHED\n")) {
                    $this->inExecution--;
                }
                if (strstr($line, "EXITED\n")) {
                    $this->isRunning = false;
                }
            }
            stream_set_blocking($this->pipes[1], 1);
        }
    }
}
