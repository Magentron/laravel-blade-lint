OS:=$(shell uname -s)
NPROCS:=$(shell [ Darwin = $(OS) ] && sysctl -n hw.ncpu || nproc)

EXTRA=
MEMORY_LIMIT=4096M
PHP=php -d memory_limit=$(MEMORY_LIMIT)
PHPCS_CONFIG=.php-cs-fixer.php
PHPMD_FORMAT=ansi
PHPMD_RULES=cleancode,codesize,controversial,design,naming,unusedcode
PHPSTAN_LEVEL=9
SRC=src

all:
	@echo No default target...

static-analysis sa:	php-cs-fixer-dry phpmd phpstan psalm

phpcs:
	time $(PHP) vendor/bin/phpcs --standard=PSR2 -p --parallel=$(NPROCS) -s $(EXTRA) $(SRC)

php-cs-fixer-dry-run php-cs-fixer-dry phpcsfixerdry pcfd:
	time $(PHP) vendor/bin/php-cs-fixer --dry-run --diff --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix $(EXTRA)

php-cs-fixer phpcsfixer pcf:
	time $(PHP) vendor/bin/php-cs-fixer --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix $(EXTRA)

php-cs-fixer-modified phpcsfixermod pcfm:
	git status | egrep -E '(modified|new file):.*\.php' | cut -d: -f 2- | xargs time $(PHP) vendor/bin/php-cs-fixer --verbose --using-cache=no --config=$(PHPCS_CONFIG) fix --

phpmd:
	-time $(PHP) vendor/bin/phpmd $(SRC) $(PHPMD_FORMAT) $(PHPMD_RULES) $(EXTRA)

phpstan:
	time $(PHP) vendor/bin/phpstan analyse --level=$(PHPSTAN_LEVEL) $(EXTRA) $(SRC)

psalm:
	time $(PHP) vendor/bin/psalm --threads=$(NPROCS) $(EXTRA)

