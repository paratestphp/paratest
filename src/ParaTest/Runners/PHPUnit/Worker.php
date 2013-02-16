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

    public function start()
    {
        $bin = 'bin/phpunit-wrapper';
        $pipes = array();
        $this->proc = proc_open($bin, self::$descriptorspec, $pipes); 
        $this->pipes = $pipes;
        $this->inExecution = 0;
    } 

    public function execute($testCmd)
    {
        fwrite($this->pipes[0], $testCmd . "\n");
        $this->inExecution++;
    }

    public function stop()
    {
        fwrite($this->pipes[0], "EXIT\n");
        fclose($this->pipes[0]);
    }

    public function waitForFinishedJob()
    {
        if ($this->inExecution == 0) {
            return;
        }
        $tellsUsItHasFinished = false;
        stream_set_blocking($this->pipes[1], 1);
        while ($line = fgets($this->pipes[1])) {
            var_dump($line);
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
        stream_set_blocking($this->pipes[1], 0);
        while ($line = fgets($this->pipes[1])) {
            var_dump($line);
            if (strstr($line, "FINISHED\n")) {
                $this->inExecution--;
            }
        }
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
}
