<?php

declare(strict_types=1);

use ParaTest\ParaTestCommand;

if (($_SERVER['argv'][1] ?? null) === '--phpunit-wrapper') {
    require __DIR__ . DIRECTORY_SEPARATOR . 'phpunit-wrapper.php';
    exit;
}

$cwd = getcwd();
assert(is_string($cwd) && '' !== $cwd);

$autoload = $cwd . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'phar-autoload.php';

ParaTestCommand::applicationFactory($cwd, '@git_version@')->run();
