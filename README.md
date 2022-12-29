ParaTest
========

[![Latest Stable Version](https://img.shields.io/packagist/v/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)
[![Downloads](https://img.shields.io/packagist/dt/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)
[![Integrate](https://github.com/paratestphp/paratest/workflows/Integrate/badge.svg?branch=6.x)](https://github.com/paratestphp/paratest/actions)
[![Code Coverage](https://codecov.io/gh/paratestphp/paratest/coverage.svg?branch=6.x)](https://codecov.io/gh/paratestphp/paratest?branch=6.x)
[![Type Coverage](https://shepherd.dev/github/paratestphp/paratest/coverage.svg)](https://shepherd.dev/github/paratestphp/paratest)
[![Infection MSI](https://badge.stryker-mutator.io/github.com/paratestphp/paratest/6.x)](https://dashboard.stryker-mutator.io/reports/github.com/paratestphp/paratest/6.x)

The objective of ParaTest is to support parallel testing in PHPUnit. Provided you have well-written PHPUnit tests, you can drop `paratest` in your project and
start using it with no additional bootstrap or configurations!

Benefits:

* Zero configuration. After the installation, run with `vendor/bin/paratest`. That's it!
* Code Coverage report combining. Run your tests in N parallel processes and all the code coverage output will be combined into one report.
* Flexible. Isolate test files in separate processes or take advantage of `WrapperRunner` for even faster runs.

# Installation

To install with composer run the following command:

    composer require --dev brianium/paratest
    
# Versions

Only the latest version of PHPUnit is supported, and thus only the latest version of ParaTest is actively maintained.

This is because of the following reasons:

1. To reduce bugs, code duplication and incompatibilities with PHPUnit, from version 5 ParaTest heavily relies on PHPUnit `@internal` classes
1. The fast pace both PHP and PHPUnit have taken recently adds too much maintenance burden, which we can only afford for the latest versions to stay up-to-date

# Usage

After installation, the binary can be found at `vendor/bin/paratest`. Run it
with `--help` option to see a complete list of the available options.

## Optimizing Speed

To get the most out of ParaTest, you have to adjust the parameters carefully.

1. **Use the WrapperRunner if possible**

    The default Runner for PHPUnit spawns a new process for each testcase (or method in functional mode). This provides
    the highest compatibility but comes with the cost of many spawned processes and a bootstrapping for each process.
    Especially when you have a slow bootstrapping in your tests (like a database setup) you should try the `WrapperRunner`
    with `--runner WrapperRunner`. It spawns one "worker"-process for each parallel process (`-p`), executes the
    bootstrapping once and reuses these processes for each test executed. That way the overhead of process spawning and
    bootstrapping is reduced to the minimum.

    Using the `--max-batch-size` option with the WrapperRunner will reset each worker after `--max-batch-size` testcases, which might help solve memory leaks problems.

2. **Adjust the number of processes with `-p`**

    To allow full usage of your cpu cores, you should have at least one process per core. More processes allow better
    resource usage but keep in mind that each process has its own costs for spawning. The default is auto, which means
    the number of logical CPU cores is set as the number of processes. You might try something like logical `CPU cores * 2`
    (e.g. if you have 8 logical cores, you might try `16`), but keep in mind that each process generates a little bit
    of overhead as well.

3. **Choose between per-testcase- and per-testmethod-parallelization with `-f`**

    Given you have few testcases (classes) with many long running methods, you should use the `-f` option to enable the
    `functional mode` and allow different methods of the same class to be executed in parallel. Keep in mind that the
    default is per-testcase-parallelization to address inter-testmethod dependencies. Note that in most projects, using
    `-f` is **slower** since each test **method** will need to be bootstrapped separately.

4. **Tune batch max size `--max-batch-size`**

    Batch size will affect the max amount of atomic tests which will be used for a single test method.
    Please note that it only works with either the `functional mode` OR `--runner WrapperRunner` (mutually exclusive). The following describes the `functional mode` system.
    One atomic test will be either one test method from test class if no data provider available for
    method or will be only one item from dataset for method.
    Increase this value to reduce per-process overhead and in most cases it will also reduce parallel efficiency.
    Decrease this value to increase per-process overhead and in most cases it will also increase parallel efficiency.
    If the amount of all tests is less than the max batch size then everything will be processed in one
    process thread so ParaTest is completely useless in that case.
    The best way to find the most effective batch size is to test with different batch size values
    and select best.
    Max batch size = 0 means that grouping in batches will not be used and one batch will equal
    all method tests (one or all from data provider).
    Max batch size = 1 means that each batch will contain only one test from the data provider or one
    method if the data provider is not used.
    Bigger max batch size can significantly increase phpunit command line length so the process can fail.
    Decrease max batch size to reduce command line length.
    Windows has a limit around 32k, Linux - 2048k, Mac OS X - 256k.

## Test token

The `TEST_TOKEN` environment variable is guaranteed to have a value that is different
from every other currently running test. This is useful to e.g. use a different database
for each test:

```php
if (getenv('TEST_TOKEN') !== false) {  // Using ParaTest
    $dbname = 'testdb_' . getenv('TEST_TOKEN');
} else {
    $dbname = 'testdb';
}
```

A `UNIQUE_TEST_TOKEN` environment variable is also available and guaranteed to have a value that is unique both
per run and per process.

## Code coverage

Beginning from PHPUnit 9.3.4, it is strongly advised to set a coverage cache directory,
see [PHPUnit Changelog @ 9.3.4](https://github.com/sebastianbergmann/phpunit/blob/9.3.4/ChangeLog-9.3.md#934---2020-08-10).

The cache is always warmed up by ParaTest before executing the test suite.

### PCOV

If you have installed `pcov` but need to enable it only while running tests, you have to pass thru the needed PHP binary
option:

```
php -d pcov.enabled=1 vendor/bin/paratest --passthru-php="'-d' 'pcov.enabled=1'"
```

### xDebug

If you have `xDebug` installed, activating it by the environment variable is enough to have it running even in the subprocesses:

```
XDEBUG_MODE=coverage vendor/bin/paratest
```

### PHPDBG

`PHPDBG` is automatically detected and used in the subprocesses if it's the running binary of the main process:

```
phpdbg vendor/bin/paratest
```

## Initial setup for all tests

Because ParaTest runs multiple processes in parallel, each with their own instance of the PHP interpreter,
techniques used to perform an initialization step exactly once for each test work different from PHPUnit.
The following pattern will not work as expected - run the initialization exactly once - and instead run the
initialization once per process:

```php
private static bool $initialized = false;

public function setUp(): void
{
    if (! self::$initialized) {
         self::initialize();
         self::$initialized = true;
    }
}
```

This is because static variables persist during the execution of a single process.
In parallel testing each process has a separate instance of `$initialized`.
You can use the following pattern to ensure your initialization runs exactly once for the entire test invocation:

```php
static bool $initialized = false;

public function setUp(): void
{
    if (! self::$initialized) {
        // We utilize the filesystem as shared mutable state to coordinate between processes
        touch('/tmp/test-initialization-lock-file');
        $lockFile = fopen('/tmp/test-initialization-lock-file', 'r');

        // Attempt to get an exclusive lock - first process wins
        if (flock($lockFile, LOCK_EX | LOCK_NB)) {
            // Since we are the single process that has an exclusive lock, we run the initialization
            self::initialize();
        } else {
            // If no exclusive lock is available, block until the first process is done with initialization
            flock($lockFile, LOCK_SH);
        }

        self::$initialized = true;
    }
}
```

## Troubleshooting

If you run into problems with `paratest`, try to get more information about the issue by enabling debug output via
`--verbose --debug`.

When a sub-process fails, the originating command is given in the output and can then be copy-pasted in the terminal
to be run and debugged. All internal commands run with `--printer [...]\NullPhpunitPrinter` which silence the original
PHPUnit output: during a debugging run remove that option to restore the output and see what PHPUnit is doing.

## Windows

Windows users be sure to use the appropriate batch files.

An example being:

`vendor\bin\paratest.bat ...`

ParaTest assumes [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for loading tests.

For convenience, ParaTest for Windows uses 79 columns mode to prevent blank lines in the standard
80x25 windows console.

## Caveats

1. Constants, static methods, static variables and everything exposed by test classes consumed by other test classes
(including Reflection) are not supported. This is due to a limitation of the current implementation of `WrapperRunner`
and how PHPUnit searches for classes. The fix is to put shared code into classes which are not tests _themselves_.

## Integration with PHPStorm

ParaTest provides a dedicated binary to work with PHPStorm; follow these steps to have ParaTest working within it:

1. Be sure you have PHPUnit already configured in PHPStorm: https://www.jetbrains.com/help/phpstorm/using-phpunit-framework.html#php_test_frameworks_phpunit_integrate
2. Go to `Run` -> `Edit configurations...`
3. Select `Add new Configuration`, select the `PHPUnit` type and name it `ParaTest`
4. In the `Command Line` -> `Interpreter options` add `./vendor/bin/paratest_for_phpstorm`
5. Any additional ParaTest options you want to pass to ParaTest should go within the `Test runner` -> `Test runner options` section

You should now have a `ParaTest` run within your configurations list.
It should natively work with the `Rerun failed tests` and `Toggle auto-test` buttons of the `Run` overlay.

### Run with Coverage

Coverage with one of the [available coverage engines](#code-coverage) must already be [configured in PHPStorm](https://www.jetbrains.com/help/phpstorm/code-coverage.html) 
and working when running tests sequentially in order for the helper binary to correctly handle code coverage

# For Contributors: testing ParaTest itself

Before creating a Pull Request be sure to run all the necessary checks with `make` command.
