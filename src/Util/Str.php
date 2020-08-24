<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function assert;
use function explode;
use function trim;

/**
 * @internal
 */
final class Str
{
    /**
     * Split $string on $delimiter and trim the individual parts.
     *
     * @return string[]
     */
    public static function explodeWithCleanup(string $delimiter, string $string): array
    {
        $stringValues = explode($delimiter, $string);
        assert($stringValues !== false);
        $parsedValues = [];
        foreach ($stringValues as $k => $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }

            $parsedValues[] = $v;
        }

        return $parsedValues;
    }
}
