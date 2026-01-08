<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\WrapperRunner\MissingResultsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(MissingResultsException::class)]
final class MissingResultsExceptionTest extends TestCase
{
    /**
     * @param 'coverage'|'test_result'         $fileType
     * @param non-empty-list<non-empty-string> $missingFiles
     * @param non-empty-string                 $expectedFileTypeLabel
     */
    #[DataProvider('provideFileTypesAndPaths')]
    public function testCreateExceptionWithMissingFiles(
        string $fileType,
        array $missingFiles,
        string $expectedFileTypeLabel
    ): void {
        $exception = MissingResultsException::create($missingFiles, $fileType);

        $message = $exception->getMessage();

        // Verify the exception message contains key information
        self::assertStringContainsString('One or more workers failed to generate', $message);
        self::assertStringContainsString($expectedFileTypeLabel, $message);
        self::assertStringContainsString('unexpected process termination', $message);
        self::assertStringContainsString('out of memory', $message);
        self::assertStringContainsString('Missing', $message);

        // Verify all missing files are listed
        foreach ($missingFiles as $file) {
            self::assertStringContainsString($file, $message);
        }
    }

    /** @return iterable<string, array{string, list<non-empty-string>, string}> */
    public static function provideFileTypesAndPaths(): iterable
    {
        yield 'single test result file' => [
            'test_result',
            ['/tmp/worker_01_test_result'],
            'test result',
        ];

        yield 'multiple test result files' => [
            'test_result',
            [
                '/tmp/worker_01_test_result',
                '/tmp/worker_02_test_result',
                '/tmp/worker_03_test_result',
            ],
            'test result',
        ];

        yield 'single coverage file' => [
            'coverage',
            ['/tmp/worker_01_coverage'],
            'coverage',
        ];

        yield 'multiple coverage files' => [
            'coverage',
            [
                '/tmp/worker_01_coverage',
                '/tmp/worker_02_coverage',
            ],
            'coverage',
        ];
    }

    public function testExceptionMessageForTestResultFiles(): void
    {
        $exception = MissingResultsException::create(['/tmp/test_result'], 'test_result');
        $message   = $exception->getMessage();

        self::assertStringContainsString('test result files', $message);
        self::assertStringContainsString('/tmp/test_result', $message);
    }

    public function testExceptionMessageForCoverageFiles(): void
    {
        $exception = MissingResultsException::create(['/tmp/coverage'], 'coverage');
        $message   = $exception->getMessage();

        self::assertStringContainsString('coverage files', $message);
        self::assertStringContainsString('/tmp/coverage', $message);
    }
}
