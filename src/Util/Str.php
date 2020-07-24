<?php

declare(strict_types=1);

namespace ParaTest\Util;

use function explode;
use function trim;

class Str
{
    /**
     * Split $string on $delimiter and trim the individual parts.
     *
     * @return string[]
     */
    public static function explodeWithCleanup(string $delimiter, string $string): array
    {
        $stringValues = explode($delimiter, $string);
        $parsedValues = [];
        foreach ($stringValues as $k => $v) {
            $v = trim($v);
            if (empty($v)) {
                continue;
            }

            $parsedValues[] = $v;
        }

        return $parsedValues;
    }
}
