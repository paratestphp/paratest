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
	vendor/bin/psalm

.PHONY: test
test: vendor
	php -d zend.assertions=1 vendor/bin/phpunit \
		--coverage-xml=coverage/coverage-xml \
		--coverage-html=coverage/html \
		--log-junit=coverage/junit.xml \
		${arg}

.PHONY: code-coverage
code-coverage: test
	php -d zend.assertions=1 vendor/bin/infection \
		--threads=$(shell nproc) \
		--coverage=coverage \
		--skip-initial-tests
