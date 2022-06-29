<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\special_chars;

use PHPUnit\Framework\TestCase;

use function array_map;
use function ord;
use function preg_split;

final class UnitTestWithDataProviderSpecialCharsTest extends TestCase
{
    /**
     * @dataProvider provideSpecialChars
     */
    public function testIsItFalse(string $specialChar): void
    {
        self::assertFalse($specialChar, (string) ord($specialChar));
    }

    /**
     * @return non-empty-list<non-empty-list<string>>
     */
    public function provideSpecialChars(): array
    {
        return array_map(static function ($specialChar) {
            return [$specialChar];
        }, preg_split('//u', 'A\\|!"£$%&()=?àèìòùÀÈÌÒÙ<>-_@#[]ßбπ€✔你يدZ'));
    }
}
