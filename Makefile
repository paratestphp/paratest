
SRCS := $(shell find ./src ./test -type f -not -path "*/tmp/*")

LOCAL_BASE_BRANCH ?= $(shell git show-branch | sed "s/].*//" | grep "\*" | grep -v "$$(git rev-parse --abbrev-ref HEAD)" | head -n1 | sed "s/^.*\[//")
ifeq ($(strip $(LOCAL_BASE_BRANCH)),)
	LOCAL_BASE_BRANCH := HEAD^
endif
BASE_BRANCH ?= $(LOCAL_BASE_BRANCH)

#all: csfix static-analysis code-coverage
all: csfix static-analysis test
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
	php -d zend.assertions=1 vendor/bin/phpstan $(PHPSTAN_ARGS)

coverage/junit.xml: vendor $(SRCS) Makefile
	php -d zend.assertions=1 \
		-d pcov.enabled=1 \
		vendor/bin/phpunit \
		--coverage-clover=coverage/clover.xml \
		--coverage-xml=coverage/xml \
		--coverage-html=coverage/html \
		--log-junit=$@ \
		$(PHPUNIT_ARGS) \
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
		--ignore-msi-with-no-mutations \
		--min-msi=100 \
		$(INFECTION_ARGS)
		
.PHONY: clean
clean:
	rm -r test/tmp/*

.PHONY: regenerate-fixture-results
regenerate-fixture-results: vendor
	vendor/bin/phpunit \
		--log-junit test/fixtures/special_chars/data-provider-with-special-chars.xml \
		--no-configuration \
		test/fixtures/special_chars/UnitTestWithDataProviderSpecialCharsTest.php \
		> /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/ErrorTest.php --log-junit test/fixtures/common_results/junit/ErrorTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/FailureTest.php --log-junit test/fixtures/common_results/junit/FailureTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/IncompleteTest.php --log-junit test/fixtures/common_results/junit/IncompleteTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/RiskyTest.php --log-junit test/fixtures/common_results/junit/RiskyTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/SkippedTest.php --log-junit test/fixtures/common_results/junit/SkippedTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/SuccessTest.php --log-junit test/fixtures/common_results/junit/SuccessTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/WarningTest.php --log-junit test/fixtures/common_results/junit/WarningTest.xml > /dev/null || true
	vendor/bin/phpunit --no-configuration test/fixtures/common_results/ --log-junit test/fixtures/common_results/combined.xml > /dev/null || true
	find test/fixtures/ -type f -name "*.xml" -print0 | xargs -0 sed -i 's#$(PWD)#.#g'
	find test/fixtures/ -type f -name "*.xml" -print0 | xargs -0 sed -i 's#time="........"#time="1.234567"#g'
	sed -i 's#name="./test/fixtures/common_results"#name=""#g' test/fixtures/common_results/combined.xml
