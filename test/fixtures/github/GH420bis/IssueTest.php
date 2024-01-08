<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH420bis;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function defined;

/** @internal */
final class IssueTest extends TestCase
{
    #[DataProvider('provideCases')]
    public function testCases(string $constant): void
    {
        self::assertStringContainsString('ISSUE_420_BIS_', $constant);
    }

    /** @return string[][] */
    public static function provideCases(): array
    {
        $cases = [
            'const_FROM_BOOTSTRAP' => defined('ISSUE_420_BIS_FROM_BOOTSTRAP') ? ISSUE_420_BIS_FROM_BOOTSTRAP : null,
        ];

        // If the gathered variables are emtpy, the number of assertions will differ
        foreach ($cases as $index => $case) {
            self::assertIsString($case, $index);
            self::assertStringContainsString('ISSUE_420_BIS_', $case, $index);
        }

        return [[$cases['const_FROM_BOOTSTRAP']]];
    }
}
