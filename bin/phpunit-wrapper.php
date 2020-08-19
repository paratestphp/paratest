<?php

declare(strict_types=1);

echo "Worker starting\n";

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

$lastExitCode    = 0;
$rand            = mt_rand(0, 999999);
$uniqueTestToken = getenv('UNIQUE_TEST_TOKEN') ?: 'no_unique_test_token';
$testToken       = getenv('TEST_TOKEN') ?: 'no_test_token';
$filename        = "paratest_t-{$testToken}_ut-{$uniqueTestToken}_r-{$rand}.log";
$path            = sys_get_temp_dir() . '/' . $filename;
$loggingEnabled  = getenv('PT_LOGGING_ENABLE');
$logInfo         = static function (string $info) use ($path, $loggingEnabled): void {
    if ($loggingEnabled === false) {
        return;
    }

    file_put_contents($path, $info, FILE_APPEND | LOCK_EX);
};

$i = 0;
while (true) {
    $i++;
    if (feof(\STDIN)) {
        exit($lastExitCode);
    }

    $command = fgets(\STDIN);
    if ($command === false) {
        exit($lastExitCode);
    }

    if ($command === \ParaTest\Runners\PHPUnit\Worker\WrapperWorker::COMMAND_EXIT) {
        echo "EXITED\n";
        exit($lastExitCode);
    }

    $arguments = unserialize($command);
    $command   = implode(' ', $arguments);
    echo "Executing: {$command}\n";

    $info     = [];
    $info[]   = 'Time: ' . (new DateTimeImmutable())->format(DateTime::RFC3339);
    $info[]   = "Iteration: {$i}";
    $info[]   = "Command: {$command}";
    $info[]   = PHP_EOL;
    $infoText = implode(PHP_EOL, $info) . PHP_EOL;
    $logInfo($infoText);

    $_SERVER['argv'] = $arguments;

    ob_start();
    $lastExitCode = PHPUnit\TextUI\Command::main(false);
    $infoText     = ob_get_clean();
    assert($infoText !== false);

    $logInfo($infoText);

    echo \ParaTest\Runners\PHPUnit\Worker\WrapperWorker::COMMAND_FINISHED;
}
