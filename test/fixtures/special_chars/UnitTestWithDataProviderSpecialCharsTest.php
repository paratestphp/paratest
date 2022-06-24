<?php

namespace ParaTest\Tests\fixtures\special_chars;

use PHPUnit\Framework\TestCase;

final class UnitTestWithDataProviderSpecialCharsTest extends TestCase
{
    /**
     * @dataProvider provideSpecialChars
     */
    public function testIsItFalse($specialChar)
    {
        self::assertFalse($specialChar, ord($specialChar));
    }

    public function provideSpecialChars()
    {
        return array_map(static function ($specialChar) {
            return [$specialChar];
        }, preg_split('//u', 'A\\|!"£$%&()=?àèìòùÀÈÌÒÙ<>-_@#[]ßбπ€✔你يدZ'));
    }
}