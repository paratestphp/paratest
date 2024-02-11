<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH420;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function defined;
use function ini_get;

/** @internal */
final class IssueTest extends TestCase
{
    #[DataProvider('provideCases')]
    public function testCases(bool $true): void
    {
        self::assertTrue($true);
    }

    /** @return true[][] */
    public static function provideCases(): array
    {
        $cases = [
            'ini' => ini_get('sendmail_from'),
            'const' => defined('ISSUE_420_const') ? ISSUE_420_const : null,
            'var' => $GLOBALS['ISSUE_420_var'] ?? null,
            'env' => $_ENV['ISSUE_420_env'] ?? null,
            'get' => $_GET['ISSUE_420_get'] ?? null,
            'post' => $_POST['ISSUE_420_post'] ?? null,
            'cookie' => $_COOKIE['ISSUE_420_cookie'] ?? null,
            'server' => $_SERVER['ISSUE_420_server'] ?? null,
            'files' => $_FILES['ISSUE_420_files'] ?? null,
            'const_FROM_BOOTSTRAP' => defined('ISSUE_420_FROM_BOOTSTRAP') ? ISSUE_420_FROM_BOOTSTRAP : null,
        ];

        // If the gathered variables are emtpy, the number of assertions will differ
        foreach ($cases as $index => $case) {
            self::assertIsString($case, $index);
            self::assertStringContainsString('ISSUE_420_', $case, $index);
        }

        return [[true]];
    }
}
