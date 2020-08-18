<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use RuntimeException;

abstract class BaseWrapperRunner extends BaseRunner
{
    private const PHPUNIT_FAILURES = 1;

    private const PHPUNIT_ERRORS = 2;

    /** @var resource[] */
    protected $streams = [];

    /** @var resource[] */
    protected $modified = [];

    final protected function beforeLoadChecks(): void
    {
        if ($this->options->functional()) {
            throw new RuntimeException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                    'to the current PHPUnit commands causing classloading issues.'
            );
        }
    }

    final protected function complete(): void
    {
        $this->setExitCode();
        $this->printer->printResults();
        $this->log();
        $this->logCoverage();
        $readers = $this->interpreter->getReaders();
        foreach ($readers as $reader) {
            $reader->removeLog();
        }
    }

    private function setExitCode(): void
    {
        if ($this->interpreter->getTotalErrors() > 0) {
            $this->exitcode = self::PHPUNIT_ERRORS;
        } elseif ($this->interpreter->getTotalFailures() > 0) {
            $this->exitcode = self::PHPUNIT_FAILURES;
        } else {
            $this->exitcode = 0;
        }
    }

    /*
    private function testIsStillRunning($test)
    {
        if(!$test->isDoneRunning()) return true;
        $this->setExitCode($test);
        $test->stop();
        if (static::PHPUNIT_FATAL_ERROR === $test->getExitCode())
            throw new \Exception($test->getStderr(), $test->getExitCode());
        return false;
    }
     */
}
