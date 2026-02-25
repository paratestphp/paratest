<?php

declare(strict_types=1);

function custom_trigger(string $message, int $error_level): bool
{
    return @trigger_error($message, $error_level);
}
