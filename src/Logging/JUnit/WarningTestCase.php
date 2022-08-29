<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/** @internal */
final class WarningTestCase extends TestCaseWithMessage
{
    public function getXmlTagName(): string
    {
        return 'warning';
    }
}
