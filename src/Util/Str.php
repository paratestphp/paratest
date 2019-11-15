<?php

declare(strict_types=1);

namespace ParaTest\Util;

class Str
{
    /**
     * Split $string on $delimiter and trim the individual parts.
     *
     * @param string $delimiter
     * @param string $string
     *
     * @return string[]
     */
    public static function explodeWithCleanup(string $delimiter, string $string): array
    {
        $stringValues = \explode($delimiter, $string);
        $parsedValues = [];
        foreach ($stringValues as $k => $v) {
            $v = \trim($v);
            if (empty($v)) {
                continue;
            }
            $parsedValues[] = $v;
        }

        return $parsedValues;
    }
}
