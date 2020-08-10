#!/bin/bash

set -ex

phpunitXsd="vendor/phpunit/phpunit/phpunit.xsd"

xmllint --noout --schema "$phpunitXsd" phpunit.xml.dist
xmllint --noout --schema vendor/squizlabs/php_codesniffer/phpcs.xsd phpcs.xml.dist
xmllint --noout --schema vendor/vimeo/psalm/config.xsd psalm.xml.dist
find test/ -name "phpunit*.xml*" -print0 | xargs -0 xmllint --noout --schema "$phpunitXsd"
