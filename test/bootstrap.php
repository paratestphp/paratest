<?php

declare(strict_types=1);

set_error_handler(static function (int $errno, string $errstr = '', string $errfile = '', int $errline = 0): bool {
    if ((error_reporting() & $errno) === 0) {
        return true;
    }

    throw new ErrorException($errstr, $errno, $errno, $errfile, $errline);
});

require __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';
require PARATEST_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
