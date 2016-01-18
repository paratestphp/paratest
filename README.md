ParaTest
========

[![Build Status](https://travis-ci.org/brianium/paratest.svg?branch=master)](https://travis-ci.org/brianium/paratest)
[![HHVM Status](http://hhvm.h4cc.de/badge/brianium/paratest.svg)](http://hhvm.h4cc.de/package/brianium/paratest)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/brianium/paratest/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/brianium/paratest/?branch=master)
[![Packagist](https://img.shields.io/packagist/dt/brianium/paratest.svg)](https://packagist.org/packages/brianium/paratest)

The objective of ParaTest is to support parallel testing in PHPUnit.

Installation
------------

### Composer ###

To install with composer add the following to your `composer.json` file:
```js
"require": {
    "brianium/paratest": "dev-master"
}
```
Then run `php composer.phar install`

Usage
-----

After installation, the binary can be found at `vendor/bin/paratest`. Usage is as follows:

```
Usage:
 paratest [-p|--processes="..."] [-f|--functional] [--no-test-tokens] [-h|--help] [--coverage-clover="..."] [--coverage-html="..."] [--coverage-php="..."] [-m|--max-batch-size="..."] [--filter="..."] [--phpunit="..."] [--runner="..."] [--bootstrap="..."] [-c|--configuration="..."] [-g|--group="..."] [--exclude-group="..."] [--stop-on-failure] [--log-junit="..."] [--colors] [--testsuite[="..."]] [--path="..."] [path]

Arguments:
 path                  The path to a directory or file containing tests. (default: current directory)

Options:
 --processes (-p)      The number of test processes to run. (default: 5)
 --functional (-f)     Run methods instead of suites in separate processes.
 --no-test-tokens      Disable TEST_TOKEN environment variables. (default: variable is set)
 --help (-h)           Display this help message.
 --coverage-clover     Generate code coverage report in Clover XML format.
 --coverage-html       Generate code coverage report in HTML format.
 --coverage-php        Serialize PHP_CodeCoverage object to file.
 --max-batch-size (-m) Max batch size (only for functional mode). (default: 0)
 --filter              Filter (only for functional mode).
 --phpunit             The PHPUnit binary to execute. (default: vendor/bin/phpunit)
 --runner              Runner or WrapperRunner. (default: Runner)
 --bootstrap           The bootstrap file to be used by PHPUnit.
 --configuration (-c)  The PHPUnit configuration file to use.
 --group (-g)          Only runs tests from the specified group(s).
 --exclude-group       Don't run tests from the specified group(s).
 --stop-on-failure     Don't start any more processes after a failure.
 --log-junit           Log test execution in JUnit XML format to file.
 --colors              Displays a colored bar as a test result.
 --testsuite           Filter which testsuite to run
 --path                An alias for the path argument.

```

### Optimizing Speed ###

To get the most out of paratest, you have to adjust the parameters carefully.

 1. ***Adjust the number of processes with ```-p```***

    To allow full usage of your cpu cores, you should have at least one process per core. More processes allow better resource usage but keep in mind that each process has it's own costs for spawning.
 2. ***Choose between per-testcase- and per-testmethod-parallelization with ```-f```***

    Given you have few testcases (classes) with many long running methods, you should use the ```-f``` option to enable the ```functional mode``` and allow different methods of the same class to be executed in parallel. Keep in mind that the default is per-testcase-parallelization to address inter-testmethod dependencies.
 3. ***Use the WrapperRunner if possible***

    The default Runner for PHPUnit spawns a new process for each testcase (or method in functional mode). This provides the highest compatibility but comes with the cost of many spawned processes and a bootstrapping for each process. Especially when you have a slow bootstrapping in your tests (like a database setup) you should try the WrapperRunner with ```--runner WrapperRunner```. It spawns one "worker"-process for each parallel process (```-p```), executes the bootstrapping once and reuses these processes for each test executed. That way the overhead of process spawning and bootstrapping is reduced to the minimum.
 4. ***Tune batch max size ```--max-batch-size```***.

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

### Windows ###

Windows users be sure to use the appropriate batch files.

An example being:

`vendor\bin\paratest.bat --phpunit vendor\bin\phpunit.bat ...`

ParaTest assumes [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for loading tests.

For convenience paratest windows version use 79 columns mode to prevent blank lines in standard
80x25 windows console.

PHPUnit Xml Config Support
--------------------------

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

Test token
----------

The `TEST_TOKEN` environment variable is guaranteed to have a value that is different
from every other currently running test. This is useful to e.g. use a different database
for each test:

```php
if (getenv('TEST_TOKEN') !== false) {  // Using partest
    $dbname = 'testdb_' . getenv('TEST_TOKEN');
} else {
    $dbname = 'testdb';
}
```

Running Tests
-------------

ParaTest's test suite depends on PHPUnit being installed via composer. Make sure you run `composer install` after cloning.

**Note that The `display_errors` php.ini directive must be set to `stderr` to run
the test suite.**

To run unit tests:
`vendor/bin/phpunit test/unit`

To run functional tests:
`vendor/bin/phpunit test/functional`

You can run all tests at once by running phpunit from the project directory.
`vendor/bin/phpunit`

ParaTest can run its own test suite by running it from the `bin` directory.
`bin/paratest`

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).
