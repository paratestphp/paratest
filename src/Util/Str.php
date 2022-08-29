<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function assert;
use function explode;
use function trim;

/** @internal */
final class Str
{
    /**
     * Split $string on $delimiter and trim the individual parts.
     *
     * @return string[]
     * @psalm-return list<string>
     */
    public static function explodeWithCleanup(string $delimiter, string $string): array
    {
        assert($delimiter !== '');
        $stringValues = explode($delimiter, $string);
        $parsedValues = [];
        foreach ($stringValues as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $parsedValues[] = $value;
        }

        return $parsedValues;
    }
}
