<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'UnitTestWithMethodAnnotationsTest.php';

/**
 * @runParallel
 */
class UnitTestWithErrorTest extends UnitTestWithMethodAnnotationsTest
{
    /**
     * @group fixtures
     */
    public function testTruth(): void
    {
        throw new Exception('Error!!!');

        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function isItFalse(): void
    {
        $this->assertFalse(false);
    }
}
