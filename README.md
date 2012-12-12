ParaTest
========
[![Build Status](https://secure.travis-ci.org/ebi/paratest.png?branch=master)](https://travis-ci.org/ebi/paratest)
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
After installation, the binary can be found at `vendors/bin/paratest`. Usage is as follows:

`
paratest [-p|--processes="..."] [-f|--functional] [-h|--help] [--phpunit="..."]
`
`
[--bootstrap="..."] [-g|--group="..."] [--log-junit="..."] [--path="..."] [path]
`


![ParaTest Usage](https://raw.github.com/brianium/paratest/master/paratest-usage.png "ParaTest Console Usage")

### Windows ###
Windows users be sure to use the appropriate batch files.
An example being:

`vendors\bin\paratest.bat --phpunit vendors\bin\phpunit.bat ...`

ParaTest assumes [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) for loading tests. 

Running Tests
-------------
ParaTest's test suite depends on PHPUnit being installed via composer. Make sure you run `composer install` after cloning.

To run unit tests:
`vendor/bin/phpunit --bootstrap test/bootstrap.php test/ParaTest`

To run integration tests:
`vendor/bin/phpunit --bootstrap test/bootstrap.php it/ParaTest`

To run functional tests:
`vendor/bin/phpunit --bootstrap test/bootstrap.php functional`

There are a couple of shortcuts in the `bin` directory as well.

`bin/test` for unit tests.
`bin/test it` for integration tests.
`bin/test functional` for functional tests

You can run all tests at once by running phpunit from the project directory.
`vendor/bin/phpunit`

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).