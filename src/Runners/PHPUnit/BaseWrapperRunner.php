<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;

use function max;

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

    final protected function setExitCode(int $exitCode): void
    {
        $this->exitcode = max($this->exitcode, $exitCode);
    }
}
