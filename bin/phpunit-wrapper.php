<?php

declare(strict_types=1);

$opts = getopt('', [
    'stop-on-failure',
    'write-to:',
]);
$stopOnFailure = array_key_exists('stop-on-failure', $opts);

$composerAutoloadFiles = [
    dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'autoload.php',
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
];

foreach ($composerAutoloadFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
        define('PHPUNIT_COMPOSER_INSTALL', $file);

        break;
    }
}

$exitCode = \PHPUnit\TextUI\TestRunner::SUCCESS_EXIT;

assert(is_string($opts['write-to']));
$writeTo = fopen($opts['write-to'], 'wb');
assert(is_resource($writeTo));

$i = 0;
while (true) {
    $i++;
    if (feof(\STDIN)) {
        exit($exitCode);
    }

    $command = fgets(\STDIN);
    if ($command === false || $command === \ParaTest\Runners\PHPUnit\Worker\WrapperWorker::COMMAND_EXIT) {
        exit($exitCode);
    }

    $arguments = unserialize($command);
    $currentExitCode = (new PHPUnit\TextUI\Command)->run($arguments, false);

    fwrite($writeTo, \ParaTest\Runners\PHPUnit\Worker\BaseWorker::TEST_EXECUTED_MARKER);
    fflush($writeTo);

    $exitCode = max($exitCode, $currentExitCode);

    if ($stopOnFailure && $exitCode !== \PHPUnit\TextUI\TestRunner::SUCCESS_EXIT) {
        exit($exitCode);
    }
}
