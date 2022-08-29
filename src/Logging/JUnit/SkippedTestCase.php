<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

/** @internal */
final class SkippedTestCase extends TestCaseWithMessage
{
    public function getXmlTagName(): string
    {
        return 'skipped';
    }
}
