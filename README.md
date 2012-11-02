ParaTest
========
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
`paratest [--processes number] [--path test_directory] [--bootstrap phpunit_bootstrap] `
`[--phpunit phpunit_binary] [--functional][-h|--help] [--group group]`

The following defaults are used: --processes=5, --path=current directory, --phpunit=vendor/bin/phpunit

The `--functional` switch will tell paratest to run each test method in its own process, rather than each suite.

### Windows ###
Windows users be sure to use the appropriate batch files.
An example being:

`vendors\bin\paratest.bat --phpunit vendors\bin\phpunit.bat ...`

Output
------
Output is parsed from logged results and output in an identical manner to PHPUnit's text ui. XML was chosen because it is supported accross a variety of testing tools.

Running Tests
-------------
ParaTest's test suite depends on PHPUnit being installed via composer. Make sure you run `composer install` after cloning.

Unit tests for this project are in the `test/ParaTest` directory and the `it/ParaTest` directory. The bootstrap file is contained in the `test` directory.

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

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).