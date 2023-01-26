<?php

declare(strict_types=1);

use ParaTest\Runners\PHPUnit\Worker\ApplicationForWrapperWorker;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;

(static function (): void {
    $getopt = getopt('', [
        'status-file:',
        'progress-file:',
        'phpunit-argv:',
    ]);

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

    assert(isset($getopt['status-file']) && is_string($getopt['status-file']));
    $statusFile = fopen($getopt['status-file'], 'wb');
    assert(is_resource($statusFile));

    assert(isset($getopt['progress-file']) && is_string($getopt['progress-file']));

    assert(isset($getopt['phpunit-argv']) && is_string($getopt['phpunit-argv']));
    $phpunitArgv = unserialize($getopt['phpunit-argv'], ['allowed_classes' => false]);
    assert(is_array($phpunitArgv));

    $application = new ApplicationForWrapperWorker(
        $phpunitArgv,
        $getopt['progress-file'],
    );

    while (true) {
        if (feof(STDIN)) {
            $application->end();
            exit;
        }

        $testPath = fgets(STDIN);
        if ($testPath === false || $testPath === WrapperWorker::COMMAND_EXIT) {
            $application->end();
            exit;
        }

        // It must be a 1 byte string to ensure filesize() is equal to the number of tests executed
        $exitCode = $application->runTest(trim($testPath));

        fwrite($statusFile, (string) $exitCode);
        fflush($statusFile);
    }
})();
