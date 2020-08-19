<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use PHPUnit\TextUI\TestRunner;

abstract class BaseWrapperRunner extends BaseRunner
{
    final protected function beforeLoadChecks(): void
    {
        if ($this->options->functional()) {
            throw new InvalidArgumentException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                    'to the current PHPUnit commands causing classloading issues.'
            );
        }
    }

    final protected function setExitCode(): void
    {
        $this->exitcode = TestRunner::SUCCESS_EXIT;
        if ($this->interpreter->getTotalErrors() > 0) {
            $this->exitcode = TestRunner::EXCEPTION_EXIT;
        } elseif ($this->interpreter->getTotalFailures() > 0) {
            $this->exitcode = TestRunner::FAILURE_EXIT;
        }
    }
}
