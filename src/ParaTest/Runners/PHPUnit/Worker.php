<?php
namespace ParaTest\Runners\PHPUnit;

class Worker
{
    private $pipes;
    private static $descriptorspec = array(
       0 => array("pipe", "r"),
       1 => array("pipe", "w"),
       2 => array("pipe", "w")
    );
    private $isRunning = false;

    public function start()
    {
        $bin = 'bin/phpunit-wrapper';
        $pipes = array();
        $this->proc = proc_open($bin, self::$descriptorspec, $pipes); 
        $this->pipes = $pipes;
        $this->inExecution = 0;
        $this->isRunning = true;
        $this->chunks = '';
    } 

    public function stdout()
    {
        return $this->pipes[1];
    }

    public function execute($testCmd)
    {
        $this->checkStarted();
        fwrite($this->pipes[0], $testCmd . "\n");
        $this->inExecution++;
    }

    private function checkStarted()
    {
        if ($this->pipes === null) {
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
        $this->updateStateFromAvailableOutput();
        return $this->inExecution == 0;
    }

    public function waitForStop()
    {
        $status = proc_get_status($this->proc);
        while($status['running']) {
            // busy loop! wait for something to be written on pipes[1]
            $status = proc_get_status($this->proc);
            $this->exitCode = $status['exitcode'];
        }
    }

    public function isRunning()
    {
        $this->updateStateFromAvailableOutput();
        return $this->isRunning;
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
            }
            $lines = explode("\n", $this->chunks);
            $this->chunks = $lines[count($lines) - 1];
            unset($lines[count($lines) - 1]);
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
