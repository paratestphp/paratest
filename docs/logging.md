# Logging WrapperRunner output
The `--runner WrapperRunner` option will start the script in `bin/phpunit-wrapper` as a long running process
and send individual tests in via stdin/pipes. In order to make the execution of the process easier to understand,
you can set the environment variable `PT_LOGGING_ENABLE` to true.

## Enable logging: set `PT_LOGGING_ENABLE`
Set the `PT_LOGGING_ENABLE` variable only for the `paratest` process:
````
PT_LOGGING_ENABLE="true" vendor/bin/paratest
````

Set the `PT_LOGGING_ENABLE` variable globally:
````
export PT_LOGGING_ENABLE="true" 
vendor/bin/paratest
````

## Logfiles
The logfiles are placed in the directory returned by `sys_get_temp_dir()`. The filename is determined 
from the given `TEST_TOKEN`, `UNIQUE_TEST_TOKEN` and a random number via
````
$uniqueTestToken = getenv("UNIQUE_TEST_TOKEN") ?: "no_unique_test_token";
$testToken = getenv("TEST_TOKEN") ?: "no_test_token";
$filename = "paratest_t-{$testToken}_ut-{$uniqueTestToken}_r-{$rand}.log";
$path = sys_get_temp_dir()."/".$filename;
````
If in doubt just check the contents of `bin/phpunit-wrapper`. 

The resulting file names look like this:
````
ls -l /tmp
total 5
-rw-r--r-- 1 root root  Jan 25 17:57 paratest_t-1_ut-5c4b4e136692d_r-457700.log
-rw-r--r-- 1 root root  Jan 25 17:57 paratest_t-2_ut-5c4b4e136855c_r-916202.log
-rw-r--r-- 1 root root  Jan 25 17:57 paratest_t-3_ut-5c4b4e1368ada_r-75267.log
-rw-r--r-- 1 root root  Jan 25 17:57 paratest_t-4_ut-5c4b4e1368ecf_r-536174.log
-rw-r--r-- 1 root root  Jan 25 17:57 paratest_t-5_ut-5c4b4e136932c_r-666883.log
````

If paratest is run with the `--no-test-tokens` option, the files look like this:
````
ls -l /tmp
total 5
-rw-r--r-- 1 root root  Jan 25 17:59 paratest_t-no_test_token_ut-no_unique_test_token_r-142884.log
-rw-r--r-- 1 root root  Jan 25 17:59 paratest_t-no_test_token_ut-no_unique_test_token_r-351471.log
-rw-r--r-- 1 root root  Jan 25 17:59 paratest_t-no_test_token_ut-no_unique_test_token_r-455307.log
-rw-r--r-- 1 root root  Jan 25 17:59 paratest_t-no_test_token_ut-no_unique_test_token_r-824877.log
-rw-r--r-- 1 root root  Jan 25 17:59 paratest_t-no_test_token_ut-no_unique_test_token_r-827359.log
````

## Logged info
The logged information contains:
- Iteration: the incrementing job number
- Time: current timestamp in RFC3339 format
- Command: The command that is being executed
- verbatim output of the command captured by `ob_start()` and returned by `ob_get_clean()`

Example:
````
cat /tmp/paratest_t-5_ut-5c4b4e136932c_r-666883.log
Time: 2019-01-25T18:19:09+00:00
Iteration: 1
Command: '/codebase/paratest/vendor/phpunit/phpunit/phpunit' '--configuration' '/codebase/paratest/phpunit.xml.dist' '--log-junit' '/tmp/PT_e4yf1N' 'ParaTest\Console\VersionProviderTest' 'test/unit//Console/VersionProviderTest.php'


PHPUnit 7.5.2 by Sebastian Bergmann and contributors.

....                                                                4 / 4 (100%)

Time: 806 ms, Memory: 4.00MB

OK (4 tests, 7 assertions)
Time: 2019-01-25T18:19:10+00:00
Iteration: 2
Command: '/codebase/paratest/vendor/phpunit/phpunit/phpunit' '--configuration' '/codebase/paratest/phpunit.xml.dist' '--log-junit' '/tmp/PT_wGmQPE' 'ParaTest\Runners\PHPUnit\SuiteTest' 'test/unit//Runners/PHPUnit/SuiteTest.php'


PHPUnit 7.5.2 by Sebastian Bergmann and contributors.

.                                                                   1 / 1 (100%)

Time: 826 ms, Memory: 4.00MB

OK (1 test, 1 assertion)
Time: 2019-01-25T18:19:10+00:00
Iteration: 3
Command: '/codebase/paratest/vendor/phpunit/phpunit/phpunit' '--configuration' '/codebase/paratest/phpunit.xml.dist' '--log-junit' '/tmp/PT_E2vYmt' 'ParaTest\Runners\PHPUnit\TestMethodTest' 'test/unit//Runners/PHPUnit/TestMethodTest.php'


PHPUnit 7.5.2 by Sebastian Bergmann and contributors.

.                                                                   1 / 1 (100%)

Time: 845 ms, Memory: 4.00MB

OK (1 test, 1 assertion)
````
