<?php

declare(strict_types=1);

require_once __DIR__ . '/../failing-tests/UnitTestWithMethodAnnotationsTest.php';

/**
 * @runParallel
 */
class UnitTestWithFatalFunctionErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        $fatal = static function (): void {
            inexistent();
        };

        $fatal();
    }

    /**
     * @test
     */
    public function isItFalse(): void
    {
        sleep(2);
        $this->assertFalse(false);
    }
}
