<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\same_issue_in_multiple_processes;

use function trigger_error;

use const E_USER_DEPRECATED;
use const E_USER_NOTICE;
use const E_USER_WARNING;

/** @internal */
final class ClassWithIssues
{
    public function foo(): bool
    {
        trigger_error('deprecation', E_USER_DEPRECATED);
        trigger_error('notice', E_USER_NOTICE);
        trigger_error('warning', E_USER_WARNING);

        return true;
    }
}
