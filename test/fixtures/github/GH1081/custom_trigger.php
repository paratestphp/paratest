<?php

declare(strict_types=1);

// phpcs:ignore Squiz.Functions.GlobalFunction.Found
function custom_trigger(string $message, int $error_level): bool
{
    return @trigger_error($message, $error_level);
}
