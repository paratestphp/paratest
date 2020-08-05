<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class Issue420BisTest extends TestCase
{
    /**
     * @dataProvider provideCases
     */
    public function testCases(string $constant): void
    {
        static::assertStringContainsString('ISSUE_420_BIS_', $constant);
    }

    public function provideCases(): array
    {
        $cases = [
            'const_FROM_BOOTSTRAP' => defined('ISSUE_420_BIS_FROM_BOOTSTRAP') ? ISSUE_420_BIS_FROM_BOOTSTRAP : null,
        ];
        
        // If the gathered variables are emtpy, the number of assertions will differ
        foreach ($cases as $index => $case) {
            static::assertIsString($case, $index);
            static::assertStringContainsString('ISSUE_420_BIS_', $case, $index);
        }

        return [[$cases['const_FROM_BOOTSTRAP']]];
    }
}
