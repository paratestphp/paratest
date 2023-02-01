<?php

namespace ParaTest\Logging\JUnit;

enum MessageType
{
    case error;
    case failure;
    case risky;
    case skipped;
    case warning;
    
    public function toString(): string
    {
        return match($this) {
            self::error, self::risky => 'error',
            self::failure => 'failure',
            self::skipped => 'skipped',
            self::warning => 'warning',
        };
    }
}