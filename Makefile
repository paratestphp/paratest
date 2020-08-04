all: csfix static-analysis test
	@echo "Done."

vendor: composer.lock
	composer install
	touch vendor

.PHONY: csfix
csfix: vendor
	vendor/bin/phpcbf || true
	vendor/bin/phpcs

.PHONY: static-analysis
static-analysis: vendor
	vendor/bin/phpstan analyse
	vendor/bin/psalm

.PHONY: test
test: vendor
	vendor/bin/phpunit
