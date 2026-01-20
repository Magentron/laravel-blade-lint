OS:=$(shell uname -s)
NPROCS:=$(shell [ Darwin = $(OS) ] && sysctl -n hw.ncpu || nproc)

EXTRA=
MEMORY_LIMIT=4096M
PHP=php -d memory_limit=$(MEMORY_LIMIT)
PHPCPD_EXCLUDES=--exclude=app/Domains/Signicat/Api/Info/Generated --exclude=app/Domains/Signicat/Api/Sign/Generated --exclude=lang
PHPCPD_FLAGS=--fuzzy
PHPCS_CONFIG=.php-cs-fixer.php
PHPMD_BASELINE=phpmd-baseline.xml
PHPMD_FORMAT=ansi
PHPMD_RULES=cleancode,codesize,controversial,design,naming,unusedcode
PHPSTAN_LEVEL=9
SRC=src
PHP_SRC_TEST=*.php src tests

## Show this help message
help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@awk '/^## /{desc=substr($$0,4)} /^[a-zA-Z_-][a-zA-Z0-9_ -]*:/ && !/^[A-Z_]+:=/{split($$0,a,":"); gsub(/ .*/,"",a[1]); printf "  %-24s %s\n",a[1],desc; desc=""}' $(MAKEFILE_LIST)

## When 'make all' run tests and static analysis
all:	test static-analysis

## Run all static analysis tools
static-analysis sa:	php-cs-fixer-dry phpcpd phpmd phpstan psalm

## Run PHP CodeSniffer with PSR2 standard
phpcs:
	time $(PHP) vendor/bin/phpcs --standard=PSR2 -p --parallel=$(NPROCS) -s $(EXTRA) $(SRC)

## Check code style without making changes
php-cs-fixer-dry-run php-cs-fixer-dry phpcsfixerdry pcfd:
	time $(PHP) vendor/bin/php-cs-fixer --dry-run --diff --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix $(EXTRA)

## Fix code style issues
php-cs-fixer phpcsfixer pcf:
	time $(PHP) vendor/bin/php-cs-fixer --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix $(EXTRA)

## Fix code style for modified files only
php-cs-fixer-modified phpcsfixermod pcfm:
	git status | egrep -E '(modified|new file):.*\.php' | cut -d: -f 2- | xargs time $(PHP) vendor/bin/php-cs-fixer --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix --

## Detect copy/paste code
phpcpd:
	@time $(PHP) vendor/bin/phpcpd $(PHPCPD_FLAGS) $(PHPCPD_EXCLUDES) $(EXTRA) $(PHP_SRC_TEST)

## Run PHP Mess Detector
phpmd:
	-time $(PHP) vendor/bin/phpmd $(if $(wildcard $(PHPMD_BASELINE)),--baseline-file $(PHPMD_BASELINE)) -- $(SRC) $(PHPMD_FORMAT) $(PHPMD_RULES) $(EXTRA)

## Generate PHPMD baseline from current violations
phpmd-baseline phpmdbaseline phpmdbl:
	-time $(PHP) vendor/bin/phpmd --generate-baseline --baseline-file $(PHPMD_BASELINE) -- $(SRC) $(PHPMD_FORMAT) $(PHPMD_RULES) $(EXTRA)

## Update PHPMD baseline, removing fixed issues
phpmd-update-baseline phpmd-update phpmdub:
	-time $(PHP) vendor/bin/phpmd --update-baseline --baseline-file $(PHPMD_BASELINE) -- $(SRC) $(PHPMD_FORMAT) $(PHPMD_RULES) $(EXTRA)

## Run PHPStan static analysis
phpstan:
	time $(PHP) vendor/bin/phpstan analyse --level=$(PHPSTAN_LEVEL) $(EXTRA) $(SRC)

## Run Psalm static analysis
psalm:
	time $(PHP) vendor/bin/psalm --threads=$(NPROCS) $(EXTRA)

## Clear Psalm cache and run analysis
psalm-clear-cache psalm-clear psalmclear psalmc:
	@make psalm EXTRA='--clear-cache $(EXTRA)'

## Run PHPUnit tests
test t:
	time $(PHP) vendor/bin/phpunit $(EXTRA)

## Run tests with debug output
test-debug td:
	@make test EXTRA='--debug $(EXTRA)'

## Run tests, stop on first failure/error
test-stop ts:
	@make test EXTRA='--stop-on-failure --stop-on-error $(EXTRA)'

## Run tests with code coverage report
test-coverage tc coverage cover:
	@make test EXTRA='--coverage-html build/coverage $(EXTRA)'
