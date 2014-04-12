ParaTest 
========
[![Build Status](https://secure.travis-ci.org/brianium/paratest.png?branch=master)](https://travis-ci.org/brianium/paratest)

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
The `display_errors` php.ini directive must be set to `stderr` to run the test suite.


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

To run unit tests:
`vendor/bin/phpunit test/ParaTest`

To run integration tests:
`vendor/bin/phpunit it/ParaTest`

To run functional tests:
`vendor/bin/phpunit functional`

There are a couple of shortcuts in the `bin` directory as well.

`bin/test` for unit tests.
`bin/test it` for integration tests.
`bin/test functional` for functional tests

You can run all tests at once by running phpunit from the project directory.
`vendor/bin/phpunit`

ParaTest can run its own test suite by running it from the `bin` directory.
`bin/paratest`

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).
