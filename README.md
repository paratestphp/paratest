ParaTest
========

[![Build Status](https://travis-ci.org/paratestphp/paratest.svg?branch=master)](https://travis-ci.org/paratestphp/paratest)
[![Packagist](https://img.shields.io/packagist/dt/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)

The objective of ParaTest is to support parallel testing in PHPUnit. Provided you have well-written PHPUnit tests, you can drop `paratest` in your project and
start using it with no additional bootstrap or configurations!

# Benefits

Why use `paratest` over the alternative parallel test runners out there?

* Code Coverage report combining. *Run your tests in N parallel processes and all the code coverage output will be combined into one report.*
* Zero configuration. *After composer install, run with `vendor/bin/paratest -p4 path/to/tests`. That's it!*
* Flexible. *Isolate test files in separate processes or take advantage of WrapperRunner for even faster runs.*

# Installation

To install with composer run the following command:

    composer require --dev brianium/paratest
    
# Versions

| PHPUnit Version  | Corresponding Paratest Version |
| ------------- | ------------- |
| <= 6.* | 1.* |
| 7.* | 2.* |
| 8.* | 3.* |
| 9.* | 4.* |

# Usage

After installation, the binary can be found at `vendor/bin/paratest`. Usage is as follows:

```
Usage:
 paratest [-p|--processes PROCESSES] [-f|--functional] [--no-test-tokens] [-h|--help] [--coverage-clover COVERAGE-CLOVER] [--coverage-html COVERAGE-HTML] [--coverage-php COVERAGE-PHP] [--coverage-text] [--coverage-xml COVERAGE-XML] [-m|--max-batch-size MAX-BATCH-SIZE] [--filter FILTER] [--parallel-suite] [--passthru PASSTHRU] [--passthru-php PASSTHRU-PHP] [-v|--verbose VERBOSE] [--whitelist WHITELIST] [--phpunit PHPUNIT] [--runner RUNNER] [--bootstrap BOOTSTRAP] [-c|--configuration CONFIGURATION] [-g|--group GROUP] [--exclude-group EXCLUDE-GROUP] [--stop-on-failure] [--log-junit LOG-JUNIT] [--colors] [--testsuite [TESTSUITE]] [--path PATH] [--] [<path>]

Arguments:
 path                        The path to a directory or file containing tests. (default: current directory)
      
Options:      
 --processes (-p)            The number of test processes to run. (Default: auto)
                             Possible values:
                             - Integer (>= 1): Number of processes to run.
                             - auto (default): Number of processes is automatically set to the number of logical CPU cores.
                             - half: Number of processes is automatically set to half the number of logical CPU cores.
 --functional (-f)           Run test methods instead of classes in separate processes.
 --no-test-tokens            Disable TEST_TOKEN environment variables. (Default: Variable is set)
 --help (-h)                 Display this help message.
 --coverage-clover           Generate code coverage report in Clover XML format.
 --coverage-html             Generate code coverage report in HTML format.
 --coverage-php              Serialize PHP_CodeCoverage object to file.
 --coverage-text             Generate code coverage report in text format.
 --coverage-xml              Generate code coverage report in PHPUnit XML format.
 --max-batch-size (-m)       Max batch size (only for functional mode). (Default: 0)
 --filter                    Filter (only for functional mode).
 --phpunit                   The PHPUnit binary to execute. (Default: vendor/bin/phpunit)
 --runner                    Runner, WrapperRunner or SqliteRunner. (Default: Runner)
 --bootstrap                 The bootstrap file to be used by PHPUnit.
 --configuration (-c)        The PHPUnit configuration file to use.
 --group (-g)                Only runs tests from the specified group(s).
 --exclude-group             Don't run tests from the specified group(s).
 --stop-on-failure           Don't start any more processes after a failure.
 --log-junit                 Log test execution in JUnit XML format to file.
 --colors                    Displays a colored bar as a test result.
 --testsuite                 Filter which testsuite to run. Run multiple suits by separating them with ",". Example:  --testsuite suite1,suite2
 --path                      An alias for the path argument.
 --parallel-suite            Run testsuites in parallel as opposed to running test classes / test functions in parallel.
 --passthru=PASSTHRU         Pass the given arguments verbatim to the underlying test framework. Example: --passthru="'--prepend' 'xdebug-filter.php'"
 --passthru-php=PASSTHRU-PHP Pass the given arguments verbatim to the underlying php process. Example: --passthru-php="'-d' 'zend_extension=xdebug.so'"
  -v, --verbose=VERBOSE      If given, debug output is printed. Example: --verbose=1
 
```

### Optimizing Speed

To get the most out of paratest, you have to adjust the parameters carefully.

 1. **Adjust the number of processes with `-p`**

    To allow full usage of your cpu cores, you should have at least one process per core. More processes allow better resource usage but keep in mind that each process has its own costs for spawning. The default is auto, which means the number of logical CPU cores is set as number of processes. You might try something like logical `CPU cores * 2` (e.g. if you have 8 logical cores, you might try `16`), but keep in mind that each process generates a little bit of overhead as well.

 2. **Choose between per-testcase- and per-testmethod-parallelization with `-f`**

    Given you have few testcases (classes) with many long running methods, you should use the `-f` option to enable the `functional mode` and allow different methods of the same class to be executed in parallel. Keep in mind that the default is per-testcase-parallelization to address inter-testmethod dependencies. Note that in most projects, using `-f` is **slower** since each test **method** will need to be bootstrapped separately.

 3. **Use the WrapperRunner or SqliteRunner if possible**

    The default Runner for PHPUnit spawns a new process for each testcase (or method in functional mode). This provides the highest compatibility but comes with the cost of many spawned processes and a bootstrapping for each process. Especially when you have a slow bootstrapping in your tests (like a database setup) you should try the WrapperRunner with `--runner WrapperRunner` or the SqliteRunner with `--runner SqliteRunner`. It spawns one "worker"-process for each parallel process (`-p`), executes the bootstrapping once and reuses these processes for each test executed. That way the overhead of process spawning and bootstrapping is reduced to the minimum.

 4. **Tune batch max size `--max-batch-size`**

    Batch size will affect on max amount of atomic tests which will be used for single test method.
    One atomic test will be either one test method from test class if no data provider available for
    method or will be only one item from dataset for method.
    Increase this value to reduce per-process overhead and in most cases it will also reduce parallel efficiency.
    Decrease this value to increase per-process overhead and in most cases it will also increase parallel efficiency.
    If amount of all tests less then max batch size then everything will be processed in one
    process thread so paratest is completely useless in that case.
    The best way to find the most effective batch size is to test with different batch size values
    and select best.
    Max batch size = 0 means that grouping in batches will not be used and one batch will equal to
    all method tests (one or all from data provider).
    Max batch size = 1 means that each batch will contain only one test from data provider or one
    method if data provider is not used.
    Bigger max batch size can significantly increase phpunit command line length so process can failed.
    Decrease max batch size to reduce command line length.
    Windows has limit around 32k, Linux - 2048k, Mac OS X - 256k.

### Examples
Examples assume your tests are located under `./test/unit`.

```
# Run all unit tests in 8 parallel processes
vendor/bin/paratest -p8 test/unit
```

```
# Run all unit tests in 4 parallel processes with WrapperRunner and output html code coverage report to /tmp/coverage
# (Code coverage requires Xdebug to be installed)
vendor/bin/paratest -p4 --runner=WrapperRunner --coverage-html=/tmp/coverage test/unit
```

### Troubleshooting
If you run into problems with `paratest`, try to get more information about the issue by enabling debug output via `--verbose=1`.

In case you are using the `WrapperRunner` for execution, consider enabling logging for troubleshooting via `export PT_LOGGING_ENABLE="true"`.
The corresponding logfiles are placed in your `sys_get_temp_dir()`.

See [Logging docs](docs/logging.md) for further information.

### Generating code coverage
Examples assume your tests are located under `./test/unit`.
````
vendor/bin/paratest -p 1 --coverage-text test/unit

Running phpunit in 1 process with /codebase/paratest/vendor/phpunit/phpunit/phpunit

Configuration read from /codebase/paratest/phpunit.xml.dist

...............................................................  63 / 155 ( 40%)
............................................................... 126 / 157 ( 80%)
.....................................

Time: 27.2 seconds, Memory: 8.00MB

OK (163 tests, 328 assertions)


Code Coverage Report:
  2019-01-25 09:41:26

 Summary:
  Classes: 22.86% (8/35)
  Methods: 49.47% (139/281)
  Lines:   59.38% (896/1509)
````

**Caution**: Generating coverage is an art in itself. Please refer to our extensive guide on setting up everything correctly for 
[code coverage generation with `paratest`](docs/code-coverage.md).

### Windows

Windows users be sure to use the appropriate batch files.

An example being:

`vendor\bin\paratest.bat --phpunit vendor\bin\phpunit.bat ...`

ParaTest assumes [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for loading tests.

For convenience paratest windows version use 79 columns mode to prevent blank lines in standard
80x25 windows console.

# PHPUnit Xml Config Support

When running PHPUnit tests, ParaTest will automatically pass the phpunit.xml or phpunit.xml.dist to the phpunit runner
via the --configuration switch. ParaTest also allows the configuration path to be specified manually.

ParaTest will rely on the `testsuites` node of phpunit's xml configuration to handle loading of suites.

The following phpunit config file is used for ParaTest's test cases.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit backupGlobals="false"
         backupStaticAttributes="false"
         bootstrap="../bootstrap.php"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         processIsolation="false"
         stopOnFailure="false"
         syntaxCheck="false"
        >
    <testsuites>
        <testsuite name="ParaTest Fixtures">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

# Test token

The `TEST_TOKEN` environment variable is guaranteed to have a value that is different
from every other currently running test. This is useful to e.g. use a different database
for each test:

```php
if (getenv('TEST_TOKEN') !== false) {  // Using paratest
    $dbname = 'testdb_' . getenv('TEST_TOKEN');
} else {
    $dbname = 'testdb';
}
```

# For Contributors: Testing paratest itself

ParaTest's test suite depends on PHPUnit being installed via composer. Make sure you run `composer install` after cloning.

**Note that The `display_errors` php.ini directive must be set to `stderr` to run the test suite.**

You can use composer scripts for convenient access to style checks and tests. Run `compose run-script -l` to list the
available commands:

````
composer run-script -l
scripts:
  style            Run style checks (only dry run - no fixing!)
  style-fix        Run style checks and fix violations
  test             Run all tests
  test-unit        Run only unit tests
  test-functional  Run only functional tests
  test-paratest    Run all tests with paratest itself
````

To run unit tests:
`composer test-unit` OR `vendor/bin/phpunit test/unit`

To run functional tests:
`composer test-functional` OR `vendor/bin/phpunit test/functional`

You can run all tests at once by running phpunit from the project directory:
`composer test` OR `vendor/bin/phpunit`

ParaTest can run its own test suite by running it from the `bin` directory:
`composer test` OR `bin/paratest`

Before creating a Pull Request be sure to run the style checks and commit the eventual changes:
`composer style-fix` OR `vendor/bin/php-cs-fixer fix`

Use `composer style` to only show violations without fixing.

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).
