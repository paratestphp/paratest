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
Then from where you have composer installed run `php composer.phar install`

Usage
-----
After installation, the binary can be found at `vendors/bin/paratest`. Usage is as follows:
`paratest [--maxProcs number] [--path test_directory] [--bootstrap phpunit_bootstrap] [--configuration phpunit_config]`
`[--exclude-group group] [--group group]`

Output
------
Output is parsed from logged results and output in an identical manner to PHPUnit's text ui. A todo is to speed this process up. XML was chosen because it is supported accross a variety of testing tools. This may cause serial unit tests to run a bit faster, but ParaTest will outperform long running processes (i.e selenium)

Todo
----
* Behat runner
* Speed up log reading/class parsing
* Support colored results