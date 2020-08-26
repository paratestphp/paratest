all: csfix static-analysis test
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
	vendor/bin/phpstan analyse
	vendor/bin/psalm

.PHONY: test
test: vendor
	php -d zend.assertions=1 vendor/bin/phpunit ${arg}
