# Blate - project tasks
# Usage: make <target>
#
# Three tiers of verification, the cadence every repository of the suite follows:
#
#   make check                        every change: code style and static analysis.
#   make test FILE=... FILTER=...     a feature is done: the tests that cover it.
#   make test                         end of a cycle, and before anything is handed over.
#
# The whole suite runs in a few seconds here, so FILE and FILTER buy focus and not time. What costs
# is `check`: phpcs and psalm both read every file. phpcs is told to fork over this machine's cores,
# and both keep a cache keyed by file content and by their own configuration, so a second run reads
# only what changed. `make check-full` ignores both caches: it is the run the cached one is checked
# against, and what CI runs. Neither the forking nor the cache changes what a check reports.

PHP     ?= php
PHPUNIT  = ./vendor/bin/phpunit
PSALM    = ./vendor/bin/psalm
CS       = ./vendor/bin/oliup-cs
EDITOR   = editors/vscode

# phpcs reads one file at a time unless told to fork (it needs ext-pcntl). psalm counts the cores
# itself, and takes one in CI on purpose, so it is left to decide.
PHPCS_JOBS  ?= $(shell nproc 2>/dev/null || echo 1)
# phpcs caches only when given a file to cache into; psalm caches unless told not to.
PHPCS_CACHE ?= --cache=.phpcs.cache
PSALM_CACHE ?=

.PHONY: help test check check-full psalm cs fix install ext ext-watch ext-clean

## help: show this help message
help:
	@grep -E '^## ' $(MAKEFILE_LIST) | sed 's/^## /  /' | column -t -s ':'

## install: install PHP dependencies
install:
	composer install

## test: run the PHPUnit test suite (FILE=tests/FooTest.php one file, FILTER=testBar one case)
test:
	$(PHPUNIT) --testdox --do-not-cache-result $(if $(FILTER),--filter '$(FILTER)',) $(FILE)

## check: code style and static analysis, the gate for every change
check: cs psalm

## check-full: the same two with their caches ignored, what a handover and CI run
check-full: PHPCS_CACHE := --no-cache
check-full: PSALM_CACHE := --no-cache
check-full: cs psalm

## psalm: run Psalm static analysis
psalm:
	$(PSALM) $(PSALM_CACHE)

## cs: check code style (no auto-fix)
cs:
	$(CS) check --parallel=$(PHPCS_JOBS) $(PHPCS_CACHE)

## fix: fix code style + run Psalm
fix:
	$(PSALM) $(PSALM_CACHE)
	$(CS) fix

## ext: build the VS Code extension (out/extension.js)
ext:
	cd $(EDITOR) && npm install && node esbuild.js && rm -rf node_modules

## ext-watch: watch-mode build for the VS Code extension
ext-watch:
	cd $(EDITOR) && npm install && node esbuild.js --watch

## ext-clean: remove VS Code extension build artefacts
ext-clean:
	rm -rf $(EDITOR)/out $(EDITOR)/node_modules
