<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/** @internal */
final class ErrorTestCase extends TestCaseWithMessage
{
    public function getXmlTagName(): string
    {
        return 'error';
    }
}
