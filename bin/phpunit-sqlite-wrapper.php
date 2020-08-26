<?php

declare(strict_types=1);

$opts = getopt('', [
    'database:',
    'stop-on-failure'
]);
$database = $opts['database'];
assert(is_string($database));
$stopOnFailure = array_key_exists('stop-on-failure', $opts);

if (! is_file($database)) {
    fwrite(STDERR, 'First parameter for sqlite database file required.');
    exit(255);
}
$db = new PDO('sqlite:' . $database);

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

$selectQuery = '
    SELECT id, command
    FROM tests
    WHERE reserved_by_process_id IS NULL
    LIMIT 1
';
$reserveTest = '
    UPDATE tests
    SET reserved_by_process_id = :procId
    WHERE id = :id AND reserved_by_process_id IS NULL
';

$exitCode = \PHPUnit\TextUI\TestRunner::SUCCESS_EXIT;
while (($test = $db->query($selectQuery)->fetch()) !== false) {
    $statement = $db->prepare($reserveTest);
    $statement->execute([
        ':procId' => getmypid(),
        ':id' => $test['id'],
    ]);

    if ($statement->rowCount() !== 1) {
        // Seems like this test has already been reserved. Continue to the next one.
        continue;
    }

    try {
        $arguments = unserialize($test['command']);

        $currentExitCode = (new PHPUnit\TextUI\Command)->run($arguments, false);
    } finally {
        $db->prepare('UPDATE tests SET completed = 1 WHERE id = :id')
            ->execute([':id' => $test['id']]);
    }

    $exitCode = max($exitCode, $currentExitCode);

    if ($stopOnFailure && $exitCode !== \PHPUnit\TextUI\TestRunner::SUCCESS_EXIT) {
        $db->exec('DELETE FROM tests WHERE completed = 0 AND reserved_by_process_id IS NULL');
        exit($exitCode);
    }
}

exit($exitCode);
