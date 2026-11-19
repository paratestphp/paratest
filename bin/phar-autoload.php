<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use PHPUnit\Runner\Version;
use PHPUnit\Util\ExcludeList;

(static function (?string $autoload, ?string $phpunitPhar): void {
    $vendor = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR;
    if ($autoload !== null && is_file($autoload)) {
        require_once $autoload;
        define('PARATEST_PHAR_AUTOLOAD', $autoload);
        $composerDir = $vendor . 'composer' . DIRECTORY_SEPARATOR;
        $classes = require $composerDir . 'autoload_classmap.php';
        $paratest = array_filter($classes, static fn (string $class): bool => str_starts_with($class, 'ParaTest\\'), ARRAY_FILTER_USE_KEY);

        $application = new ClassLoader();
        $application->addClassMap($paratest);
        $application->register(true);

        $fallback = new ClassLoader();
        $fallback->addClassMap(array_diff_key($classes, $paratest));
        $fallback->register(false);

        foreach (require $composerDir . 'autoload_files.php' as $id => $file) {
            if (empty($GLOBALS['__composer_autoload_files'][$id])) {
                $GLOBALS['__composer_autoload_files'][$id] = true;
                require $file;
            }
        }
    } else {
        require_once $vendor . 'autoload.php';
    }

    if (class_exists(Version::class) && defined('PARATEST_PHAR_AUTOLOAD')) {
        define('PHPUNIT_COMPOSER_INSTALL', PARATEST_PHAR_AUTOLOAD);
    } elseif ($phpunitPhar !== null) {
        $phpunitPhar = realpath($phpunitPhar);
        if ($phpunitPhar === false || !is_file($phpunitPhar)) {
            fwrite(STDERR, 'The PHPUnit PHAR supplied by PhpStorm was not found.' . PHP_EOL);
            exit(1);
        }

        require $phpunitPhar;
    }

    if (class_exists(ExcludeList::class)) {
        // PHPUnit's addDirectory() uses realpath(), which cannot resolve a PHAR directory.
        $directories = new ReflectionProperty(ExcludeList::class, 'directories');
        $directories->setValue(null, [...$directories->getValue(), Phar::running() . '/']);
    }
})($autoload ?? null, $phpunitPhar ?? null);
