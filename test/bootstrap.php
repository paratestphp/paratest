<?php

declare(strict_types=1);

const FIXTURES            = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';
const PROCESSES_FOR_TESTS = 2; // We need exactly 2 to generate appropriate race conditions for complete coverage

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
