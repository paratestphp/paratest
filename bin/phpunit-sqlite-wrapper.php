<?php

declare(strict_types=1);

if (! isset($argv[1])) {
    fwrite(STDERR, 'First parameter for sqlite database file required.');
    exit(1);
}

$db = new PDO('sqlite:' . $argv[1]);

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
    ORDER BY file_name
    LIMIT 1
';
$reserveTest = '
    UPDATE tests
    SET reserved_by_process_id = :procId
    WHERE id = :id AND reserved_by_process_id IS NULL
';

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
        $_SERVER['argv'] = unserialize($test['command']);

        PHPUnit\TextUI\Command::main(false);
    } finally {
        $db->prepare('UPDATE tests SET completed = 1 WHERE id = :id')
            ->execute([':id' => $test['id']]);
    }
}
