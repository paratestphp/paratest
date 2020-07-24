#!/bin/bash

set -ex

xmllint --noout --schema vendor/phpunit/phpunit/phpunit.xsd phpunit.xml.dist
xmllint --noout --schema vendor/squizlabs/php_codesniffer/phpcs.xsd phpcs.xml.dist
xmllint --noout --schema vendor/vimeo/psalm/config.xsd psalm.xml.dist
find test/ -name "phpunit*.xml*" -not -name "phpunit-files-dirs-mix-nested.xml" -print0 | xargs -0 xmllint --noout --schema vendor/phpunit/phpunit/phpunit.xsd