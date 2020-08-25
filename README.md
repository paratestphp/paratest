ParaTest
========

[![Latest Stable Version](https://img.shields.io/packagist/v/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)
[![Downloads](https://img.shields.io/packagist/dt/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)
[![Integrate](https://github.com/paratestphp/paratest/workflows/Integrate/badge.svg?branch=master)](https://github.com/paratestphp/paratest/actions)
[![Code Coverage](https://codecov.io/gh/paratestphp/paratest/coverage.svg?branch=master)](https://codecov.io/gh/paratestphp/paratest?branch=master)
[![Type Coverage](https://shepherd.dev/github/paratestphp/paratest/coverage.svg)](https://shepherd.dev/github/paratestphp/paratest)

The objective of ParaTest is to support parallel testing in PHPUnit. Provided you have well-written PHPUnit tests, you can drop `paratest` in your project and
start using it with no additional bootstrap or configurations!

# Benefits

Why use `paratest` over the alternative parallel test runners out there?

* Code Coverage report combining. *Run your tests in N parallel processes and all the code coverage output will be combined into one report.*
* Zero configuration. *After composer install, run with `vendor/bin/paratest`. That's it!*
* Flexible. *Isolate test files in separate processes or take advantage of WrapperRunner for even faster runs.*

# Installation

To install with composer run the following command:

    composer require --dev brianium/paratest
    
# Versions

Only the latest version of PHPUnit is supported, and thus only the latest version of ParaTest is actively maintained.

This is because of the following reasons:

1. To reduce bugs, code duplication and incompatibilities with PHPUnit, from version 5 ParaTest heavily relies on PHPUnit `@internal` classes
1. The fast pace both PHP and PHPUnit have taken recently adds too much maintenance burden, which we can only afford for the latest versions to stay up-to-date

# Usage

After installation, the binary can be found at `vendor/bin/paratest`. Usage is as follows:

```
Usage:
  paratest [options] [--] [<path>]

Arguments:
  path                                           The path to a directory or file containing tests.

Options:
      --bootstrap=BOOTSTRAP                      The bootstrap file to be used by PHPUnit.
      --colors                                   Displays a colored bar as a test result.
  -c, --configuration=CONFIGURATION              The PHPUnit configuration file to use.
      --coverage-clover=COVERAGE-CLOVER          Generate code coverage report in Clover XML format.
      --coverage-crap4j=COVERAGE-CRAP4J          Generate code coverage report in Crap4J XML format.
      --coverage-html=COVERAGE-HTML              Generate code coverage report in HTML format.
      --coverage-php=COVERAGE-PHP                Serialize PHP_CodeCoverage object to file.
      --coverage-test-limit=COVERAGE-TEST-LIMIT  Limit the number of tests to record for each line of code. Helps to reduce memory and size of coverage reports.
      --coverage-text                            Generate code coverage report in text format.
      --coverage-xml=COVERAGE-XML                Generate code coverage report in PHPUnit XML format.
      --exclude-group=EXCLUDE-GROUP              Don't run tests from the specified group(s).
      --filter=FILTER                            Filter (only for functional mode).
  -f, --functional                               Run test methods instead of classes in separate processes.
  -g, --group=GROUP                              Only runs tests from the specified group(s).
  -h, --help                                     Display this help message.
      --log-junit=LOG-JUNIT                      Log test execution in JUnit XML format to file.
  -m, --max-batch-size=MAX-BATCH-SIZE            Max batch size (only for functional mode). [default: 0]
      --no-test-tokens                           Disable TEST_TOKEN environment variables. (default: variable is set)
      --parallel-suite                           Run the suites of the config in parallel.
      --passthru=PASSTHRU                        Pass the given arguments verbatim to the underlying test framework. Example: --passthru="'--prepend' 'xdebug-filter.php'"
      --passthru-php=PASSTHRU-PHP                Pass the given arguments verbatim to the underlying php process. Example: --passthru-php="'-d' 'zend_extension=xdebug.so'"
      --path=PATH                                An alias for the path argument.
      --phpunit=PHPUNIT                          The PHPUnit binary to execute. [default: "./vendor/phpunit/phpunit/phpunit"]
  -p, --processes=PROCESSES                      The number of test processes to run. [default: "auto"]
      --runner=RUNNER                            Runner, WrapperRunner or SqliteRunner. [default: "Runner"]
      --stop-on-failure                          Don't start any more processes after a failure.
      --testsuite=TESTSUITE                      Filter which testsuite to run
      --tmp-dir=TMP-DIR                          Temporary directory for internal ParaTest files [default: sys_get_temp_dir()]
  -v, --verbose=VERBOSE                          If given, debug output is printed. Example: --verbose=1 [default: 0]
      --whitelist=WHITELIST                      Directory to add to the coverage whitelist.
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

Beginning from PHPUnit 9.3.4, it is strongly advised to set a coverage cache directory,
see [PHPUnit Changlog @ 9.3.4](https://github.com/sebastianbergmann/phpunit/blob/master/ChangeLog-9.3.md#934---2020-08-10).

The cache is always warmed by ParaTest before executing the test suite.

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
<phpunit>
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

**Note that The `display_errors` php.ini directive must be set to `stderr` to run the test suite.**

Before creating a Pull Request be sure to run all the necessary checks with `make` command.

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).
