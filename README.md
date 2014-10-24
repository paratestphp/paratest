ParaTest
========
[![Build Status](https://secure.travis-ci.org/brianium/paratest.png?branch=master)](https://travis-ci.org/brianium/paratest)
[![HHVM Status](http://hhvm.h4cc.de/badge/brianium/paratest.svg)](http://hhvm.h4cc.de/package/brianium/paratest)

The objective of ParaTest is to support parallel testing in a variety of PHP testing tools. Currently only PHPUnit is supported.

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
paratest [-p|--processes="..."] [-f|--functional] [--no-test-tokens] [-h|--help]
 [--coverage-clover="..."] [--coverage-html="..."] [--coverage-php="..."]
 [--phpunit="..."] [--runner="..."] [--bootstrap="..."] [-c|--configuration="..."]
 [-g|--group="..."] [--stop-on-failure] [--log-junit="..."] [--colors] [--path="..."] [path]
```

![ParaTest Usage](https://raw.github.com/brianium/paratest/master/paratest-usage.png "ParaTest Console Usage")

### Optimizing Speed ###
To get the most out of paratest, you have to adjust the parameters carefully.
 1. ***Adjust the number of processes with ```-p```***
 
    To allow full usage of your cpu cores, you should have at least one process per core. More processes allow better resource usage but keep in mind that each process has it's own costs for spawning.
 2. ***Choose between per-testcase- and per-testmethod-parallelization with ```-f```***
 
    Given you have few testcases (classes) with many long running methods, you should use the ```-f``` option to enable the ```functional mode``` and allow different methods of the same class to be executed in parallel. Keep in mind that the default is per-testcase-parallelization to address inter-testmethod dependencies.
 3. ***Use the WrapperRunner if possible***
 
    The default Runner for PHPUnit spawns a new process for each testcase (or method in functional mode). This provides the highest compatibility but comes with the cost of many spawned processes and a bootstrapping for each process. Especially when you have a slow bootstrapping in your tests (like a database setup) you should try the WrapperRunner with ```--runner WrapperRunner```. It spawns one "worker"-process for each parallel process (```-p```), executes the bootstrapping once and reuses these processes for each test executed. That way the overhead of process spawning and bootstrapping is reduced to the minimum.

### Windows ###
Windows users be sure to use the appropriate batch files.
An example being:

`vendor\bin\paratest.bat --phpunit vendor\bin\phpunit.bat ...`

ParaTest assumes [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for loading tests.

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
