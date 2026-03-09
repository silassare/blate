# Blate - project tasks
# Usage: make <target>

PHP     ?= php
PHPUNIT  = ./vendor/bin/phpunit
PSALM    = ./vendor/bin/psalm
CS       = ./vendor/bin/oliup-cs
EDITOR   = editors/vscode

.PHONY: help test psalm cs fix install ext ext-watch ext-clean

## help: show this help message
help:
	@grep -E '^## ' $(MAKEFILE_LIST) | sed 's/^## /  /' | column -t -s ':'

## install: install PHP dependencies
install:
	composer install

## test: run the full PHPUnit test suite
test:
	$(PHPUNIT) --testdox --do-not-cache-result

## psalm: run Psalm static analysis
psalm:
	$(PSALM) --no-cache

## cs: check code style (no auto-fix)
cs:
	$(CS) check

## fix: fix code style + run Psalm
fix:
	$(PSALM) --no-cache
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
