
SRCS := $(shell find ./src ./test -type f -not -path "*/tmp/*")

LOCAL_BASE_BRANCH ?= $(shell git show-branch | sed "s/].*//" | grep "\*" | grep -v "$$(git rev-parse --abbrev-ref HEAD)" | head -n1 | sed "s/^.*\[//")
ifeq ($(strip $(LOCAL_BASE_BRANCH)),)
	LOCAL_BASE_BRANCH := HEAD^
endif
BASE_BRANCH ?= $(LOCAL_BASE_BRANCH)

FIXTURE_RESULT_DIR := test/fixtures/results

all: csfix static-analysis code-coverage
	@echo "Done."

vendor: composer.json
	composer update
	composer bump
	touch vendor

.PHONY: csfix
csfix: vendor
	vendor/bin/phpcbf || true
	vendor/bin/phpcs --cache

.PHONY: static-analysis
static-analysis: vendor
	php -d zend.assertions=1 vendor/bin/psalm $(PSALM_ARGS)

coverage/junit.xml: vendor $(SRCS) Makefile
	php -d zend.assertions=1 vendor/bin/phpunit \
		--no-coverage \
		--no-logging \
		$(PHPUNIT_ARGS)
	php -d zend.assertions=1 bin/paratest \
		--no-coverage \
		--processes=1 \
		--runner=Runner \
		$(PARATEST_ARGS)
	php -d zend.assertions=1 bin/paratest \
		--no-coverage \
		--processes=1 \
		--runner=WrapperRunner \
		$(PARATEST_ARGS)
	php -d zend.assertions=1 \
		-d pcov.enabled=1 \
		bin/paratest \
		--passthru-php="'-d' 'pcov.enabled=1'" \
		--coverage-clover=coverage/clover.xml \
		--coverage-xml=coverage/xml \
		--coverage-html=coverage/html \
		--log-junit=$@ \
		--processes=$(shell nproc) \
		$(PARATEST_ARGS) \
		|| (rm $@ && exit 1)

.PHONY: test
test: coverage/junit.xml

.PHONY: code-coverage
code-coverage: coverage/junit.xml
	echo "Base branch: $(BASE_BRANCH)"
	php -d zend.assertions=1 \
		-d pcov.enabled=1 \
		vendor/bin/infection run \
		--threads=$(shell nproc) \
		--git-diff-lines \
		--git-diff-base=$(BASE_BRANCH) \
		--skip-initial-tests \
		--initial-tests-php-options="'-d' 'pcov.enabled=1'" \
		--coverage=coverage \
		--show-mutations \
		--verbose \
		--min-msi=100 \
		$(INFECTION_ARGS)

.PHONY: regenerate-fixture-results
regenerate-fixture-results: vendor
	find "$(FIXTURE_RESULT_DIR)" -type f -name "*.xml" -print0 | xargs -0 rm
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/data-provider-errors.xml \
		--configuration test/fixtures/github/GH565/phpunit.xml \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/data-provider-with-special-chars.xml \
		--no-configuration \
		test/fixtures/special_chars/UnitTestWithDataProviderSpecialCharsTest.php \
		> /dev/null || true
	printf "" > $(FIXTURE_RESULT_DIR)/empty.xml
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/empty-test-suite.xml \
		--no-configuration \
		$(shell mktemp --directory) \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/junit-example-result.xml \
		--no-configuration \
		test/fixtures/failing_tests/ \
		--filter '/(UnitTestWithClassAnnotationTest|UnitTestWithErrorTest)/' \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/mixed-results.xml \
		--no-configuration \
		test/fixtures/failing_tests/ \
		--filter '/(UnitTestWithClassAnnotationTest|UnitTestWithMethodAnnotationsTest|UnitTestWithErrorTest)/' \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/mixed-results-with-system-out.xml \
		--no-configuration \
		test/fixtures/system_out/SystemOutTest.php \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-method.xml \
		--no-configuration \
		test/fixtures/failing_tests/UnitTestWithClassAnnotationTest.php \
		--filter '/UnitTestWithClassAnnotationTest::testArrayLength/' \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-passing.xml \
		--no-configuration \
		test/fixtures/passing_tests/level1/AnotherUnitTestInSubLevelTest.php \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-skipped.xml \
		--no-configuration \
		test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php \
		--filter testSkipped \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-warning.xml \
		--no-configuration \
		test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php \
		--filter testWarning \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-werror.xml \
		--no-configuration \
		test/fixtures/failing_tests/UnitTestWithErrorTest.php \
		--filter testTruth \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-wfailure.xml \
		--no-configuration \
		test/fixtures/failing_tests/UnitTestWithMethodAnnotationsTest.php \
		--filter '/(testTruth|testFalsehood|testArrayLength)/' \
		> /dev/null || true
	vendor/bin/phpunit \
		--log-junit $(FIXTURE_RESULT_DIR)/single-wfailure2.xml \
		--no-configuration \
		test/fixtures/failing_tests/FailingSymfonyOutputCollisionTest.php \
		> /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/01.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\FourTest::testSub' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/02.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\FourTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/03.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\OneTest::testWithProvider with data set "one"' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/04.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\OneTest::testWithProvider with data set #' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/05.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\OneTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/06.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\One\\(TwoTest|ThreeTest)::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/07.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\Two\\FourTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/08.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\Two\\OneTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/09.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\Two\\ThreeTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/10.xml --filter 'ParaTest\\Tests\\fixtures\\parallel_suite\\Two\\TwoTest::testToken' > /dev/null || true
	vendor/bin/phpunit --configuration test/fixtures/phpunit-parallel-suite.xml --log-junit $(FIXTURE_RESULT_DIR)/parallel/combined.xml > /dev/null || true
	find "$(FIXTURE_RESULT_DIR)" -type f -name "*.xml" -print0 | xargs -0 sed -i 's#$(PWD)#.#g'
	find "$(FIXTURE_RESULT_DIR)" -type f -name "*.xml" -print0 | xargs -0 sed -i 's#time="........"#time="1.234567"#g'
