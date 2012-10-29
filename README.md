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
`[--phpunit phpunit_binary] [--functional][-h|--help]`

The `--functional` switch will tell paratest to run each test method in its own process, rather than each suite.

Any switches not officially supported by ParaTest will be passed directly on to PHPUnit.

Output
------
Output is parsed from logged results and output in an identical manner to PHPUnit's text ui. XML was chosen because it is supported accross a variety of testing tools.

Running Tests
-------------
Unit tests for this project are in the `test/ParaTest` directory and the `it/ParaTest` directory. The bootstrap file is contained in the `test` directory.

To run unit tests:
`phpunit --bootstrap test/bootstrap.php test/ParaTest`

To run integration tests:
`phpunit --bootstrap test/bootstrap.php it/ParaTest`

To run functional tests:
`phpunit --bootstrap test/bootstrap.php functional`

If phpunit is on your path, there are a couple of shortcuts in the `bin` directory.

`bin/test` for unit tests.
`bin/test it` for integration tests.
`bin/test functional` for functional tests

For an example of ParaTest out in the wild check out the [example](https://github.com/brianium/paratest-selenium).

Todo
----
* Behat runner
* Support colored results