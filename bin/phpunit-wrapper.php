<?php

declare(strict_types=1);

use ParaTest\WrapperRunner\ApplicationForWrapperWorker;
use ParaTest\WrapperRunner\WrapperWorker;

(static function (): void {
    $getopt = getopt('', [
        'status-file:',
        'progress-file:',
        'unexpected-output-file:',
        'testresult-file:',
        'teamcity-file:',
        'testdox-file:',
        'testdox-color',
        'testdox-columns:',
        'testdox-summary',
        'phpunit-argv:',
    ]);

    $composerAutoloadFiles = [
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    ];

    foreach ($composerAutoloadFiles as $file) {
        if (file_exists($file)) {
            define('PHPUNIT_COMPOSER_INSTALL', $file);
            require_once $file;

            break;
        }
    }

    assert(isset($getopt['status-file']) && is_string($getopt['status-file']));
    $statusFile = fopen($getopt['status-file'], 'wb');
    assert(is_resource($statusFile));

    assert(isset($getopt['progress-file']) && is_string($getopt['progress-file']));
    assert(isset($getopt['unexpected-output-file']) && is_string($getopt['unexpected-output-file']));
    assert(isset($getopt['testresult-file']) && is_string($getopt['testresult-file']));
    assert(!isset($getopt['teamcity-file']) || is_string($getopt['teamcity-file']));
    assert(!isset($getopt['testdox-file']) || is_string($getopt['testdox-file']));
    assert(!isset($getopt['testdox-columns']) || $getopt['testdox-columns'] === (string) (int) $getopt['testdox-columns']);

    assert(isset($getopt['phpunit-argv']) && is_string($getopt['phpunit-argv']));
    $phpunitArgv = unserialize($getopt['phpunit-argv'], ['allowed_classes' => false]);
    assert(is_array($phpunitArgv));

    $application = new ApplicationForWrapperWorker(
        $phpunitArgv,
        $getopt['progress-file'],
        $getopt['unexpected-output-file'],
        $getopt['testresult-file'],
        $getopt['teamcity-file'] ?? null,
        $getopt['testdox-file'] ?? null,
        isset($getopt['testdox-color']),
        isset($getopt['testdox-columns']) ? (int) $getopt['testdox-columns'] : null,
        isset($getopt['testdox-summary']),
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
        $exitCode = $application->runTest(trim($testPath, "\n"));

        fwrite($statusFile, (string) $exitCode);
        fflush($statusFile);
    }
})();
