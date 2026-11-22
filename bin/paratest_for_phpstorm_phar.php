<?php

declare(strict_types=1);

use ParaTest\Util\PhpstormHelper;
use PHPUnit\TextUI\Application;

if (($_SERVER['argv'][1] ?? null) === '--phpunit-wrapper') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php';
    exit;
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Util' . DIRECTORY_SEPARATOR . 'PhpstormHelper.php';

$entrypoint = PhpstormHelper::handleArgvFromPhpstorm($_SERVER['argv'], __DIR__ . DIRECTORY_SEPARATOR . 'paratest-phar.php', $phpunitBinary);
$phpunitPhar = str_ends_with(strtolower($phpunitBinary), '.phar') ? $phpunitBinary : null;
if ($phpunitPhar !== null && $entrypoint === $phpunitPhar) {
    require $phpunitPhar;
    exit((new Application())->run(array_values($_SERVER['argv'])));
}

require $entrypoint;
