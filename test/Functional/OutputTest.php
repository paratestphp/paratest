<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use function getcwd;

/**
 * @coversNothing
 */
final class OutputTest extends FunctionalTestBase
{
    /** @var ParaTestInvoker */
    protected $paratest;

    public function setUp(): void
    {
        parent::setUp();
        $this->paratest = new ParaTestInvoker($this->fixture('failing-tests/UnitTestWithClassAnnotationTest.php'));
    }

    public function testDefaultMessagesDisplayed(): void
    {
        $output = $this->paratest->execute(['--processes' => 5])->getOutput();
        static::assertStringContainsString('Running phpunit in 5 processes with ' . PHPUNIT, $output);
        static::assertStringContainsString('Configuration read from ' . getcwd() . DS . 'phpunit.xml.dist', $output);
        static::assertMatchesRegularExpression('/[.F]{4}/', $output);
    }

    public function testMessagePrintedWhenFunctionalModeIsOn(): void
    {
        $output = $this->paratest
            ->execute(['--functional' => true, '--processes' => 5])
            ->getOutput();
        static::assertStringContainsString('Running phpunit in 5 processes with ' . PHPUNIT, $output);
        static::assertStringContainsString('Functional mode is ON.', $output);
        static::assertMatchesRegularExpression('/[.F]{4}/', $output);
    }

    public function testProcCountIsReportedWithProcOption(): void
    {
        $output = $this->paratest->execute(['--processes' => 1])
            ->getOutput();
        static::assertStringContainsString('Running phpunit in 1 process with ' . PHPUNIT, $output);
        static::assertMatchesRegularExpression('/[.F]{4}/', $output);
    }
}
