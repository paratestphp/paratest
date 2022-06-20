
SRCS := $(shell find ./src -type f)

LOCAL_BASE_BRANCH ?= $(shell git show-branch | sed "s/].*//" | grep "\*" | grep -v "$$(git rev-parse --abbrev-ref HEAD)" | head -n1 | sed "s/^.*\[//")
ifeq ($(strip $(LOCAL_BASE_BRANCH)),)
	LOCAL_BASE_BRANCH := HEAD^
endif
BASE_BRANCH ?= $(LOCAL_BASE_BRANCH)

all: csfix static-analysis code-coverage
	@echo "Done."

vendor: composer.json
	composer update
	touch vendor

.PHONY: csfix
csfix: vendor
	vendor/bin/phpcbf || true
	vendor/bin/phpcs --cache

.PHONY: static-analysis
static-analysis: vendor
	vendor/bin/psalm $(PSALM_ARGS)

coverage/junit.xml: vendor $(SRCS)
	php -d zend.assertions=1 vendor/bin/phpunit \
		--no-coverage \
		--no-logging \
		$(PHPUNIT_ARGS)
	php -d zend.assertions=1 bin/paratest \
		--coverage-clover=coverage/clover.xml \
		--coverage-xml=coverage/xml \
		--coverage-html=coverage/html \
		--log-junit=coverage/junit.xml \
		$(PARATEST_ARGS)

.PHONY: test
test: coverage/junit.xml

.PHONY: code-coverage
code-coverage: coverage/junit.xml
	echo "Base branch: $(BASE_BRANCH)"
	php -d zend.assertions=1 vendor/bin/infection \
		--threads=$(shell nproc) \
		--git-diff-lines \
		--git-diff-base=$(BASE_BRANCH) \
		--skip-initial-tests \
		--coverage=coverage \
		--show-mutations \
		--min-msi=100 \
		$(INFECTION_ARGS)
