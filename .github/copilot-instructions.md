# Blate - AI Agent Instructions

IMPORTANT:

- no hallucination or invention. Go through the entire code base to understand before generating code, the `.github/copilot-instructions.md` or docs. Focus on what can be directly observed in the codebase, not on idealized practices or assumptions.
- When a bug or issue is found in the codebase, do not fix it directly, but rather ask for feedback and approval.
- If `AGENTS.md`, `CLAUDE.md`, `GEMINI.md` do not exist, symlink them to `.github/copilot-instructions.md`.
- **No Unicode shortcut characters in comments or docblocks.** Always use plain ASCII equivalents:

| use      | don't use  |
| -------- | ---------- |
| `->`     | `→`        |
| `<-`     | `←`        |
| `<->`    | `↔`        |
| `-->`    | `───▶`     |
| `>=`     | `≥`        |
| `<=`     | `≤`        |
| `!=`     | `≠`        |
| `*`      | `×`        |
| `/`      | `÷`        |
| `-`      | `—` or `–` |
| `IN`     | `∈`        |
| `NOT IN` | `∉`        |
| `...`    | `…`        |

---

## Overview

Blate is a PHP template engine. Templates (`.blate` files) are compiled to PHP class files and cached on disk. The pipeline is:

```
.blate source -> Lexer (tokens) -> Parser (PHP codegen) -> cached BlateTemplateParsed class -> rendered output
```

The cached files live next to their source under `blate_cache/<version>/<hash[0:2]>/<hash[2:4]>/`. Cache is invalidated by content hash, file path, or `Blate::VERSION` change.

---

## Key Files

| File                               | Role                                                                                                                                         |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `src/Blate.php`                    | Main entry: factory (`fromPath`/`fromString`), cache management, block/helper registry                                                       |
| `src/Lexer.php`                    | Tokenizes raw template text into `Token` objects                                                                                             |
| `src/Parser.php`                   | Walks tokens, dispatches to `BlockInterface` implementations, emits PHP code                                                                 |
| `src/Traits/ParserOutputTrait.php` | PHP codegen helpers (`writeCode`, `write`, `writeExpression`, `getClassBody`) used by `Parser`                                               |
| `src/DataContext.php`              | Runtime scope stack - wraps user data with a global-vars layer and a helpers layer; supports `newContext()`/`popContext()` for scoped blocks |
| `src/GlobalVarsContext.php`        | `ArrayAccess` container for global variables; holds both static values and computed (lazy) factories; singleton managed by `Blate`           |
| `src/SimpleChain.php`              | Fluent path resolver used at runtime: `{foo.bar}` -> `$context->chain()->get('foo')->get('bar')->val()`                                      |
| `src/BlateTemplateParsed.php`      | Abstract base for compiled templates; handles slot injection for `extends`                                                                   |
| `src/BlateTemplateScope.php`       | Snapshot of the executing template: `data` (DataContext) + `template` (Blate); returned by `Blate::scope()`                                  |
| `src/Features/Block*.php`          | Built-in block implementations (`BlockIf`, `BlockEach`, `BlockSlot`, `BlockExtends`, etc.)                                                   |
| `src/Expressions/`                 | Expression parser + grammar rules (`Grammar/VarName.php`, `Operator.php`, etc.)                                                              |
| `src/assets/output.php.sample`     | Scaffold for compiled template files                                                                                                         |
| `src/Lsp/BlateLspServer.php`       | LSP server implementation (diagnostics, completions, hover, rename, code actions) over stdio                                                 |
| `editors/lsp/server.php`           | LSP entry point: bootstraps Composer and starts `BlateLspServer`                                                                             |

---

## Template Syntax at a Glance

```blate
{varName}            -- print, auto-escaped (htmlspecialchars)
{= varName}          -- print, raw/unescaped
{foo.bar}            -- property access chain
{helper('arg')}      -- call a helper; user data with key 'helper' shadows it
{$helper('arg')}     -- helper-only lookup: always resolves to the registered helper (preferred)
{$global.foo}        -- global vars layer access: foo cannot be shadowed by user data
{expr | fn}          -- pipe filter: fn(expr); fn is helper-only lookup (same as $fn); user-data callables cannot be used here
{expr | fn(a, b)}    -- pipe filter with extra args: fn(expr, a, b)
{expr | f1 | f2(x)}  -- chained pipes: f2(f1(expr), x)
{# comment #}        -- template comment (stripped at compile time)
{~ echo 'php'; ~}    -- inline PHP code; $context (DataContext) is in scope
{@set x = expr; y = expr}
{@if expr}...{:elseif expr}...{:else}...{/if}
{@each val:key:idx in list}...{/each}  -- is_first, is_last always in scope
{@scoped}...{/scoped}
{@slot name}default{/slot}
{@extends 'path/to/base' context}{@slot name}override{/slot}{/extends}
{@import 'path/to/partial' context}
{@import_raw 'path/to/file'}
{@raw}...literal braces...{/raw}
{BRACE_OPEN}          -- literal { via built-in global var (no raw block needed)
{BRACE_CLOSE}         -- literal } via built-in global var
{BLATE_VERSION}       -- engine version string (built-in global var)
$$                   -- reference to the raw DataContext inside expressions
true / false / null  -- PHP literals at expression head (any case: TRUE, FALSE, NULL)
                     -- inside a dot-chain (foo.true, foo.null) they are normal property lookups
-- String literals in expressions follow PHP escape rules:
--   \\  -> one backslash      'bar\\baz' produces bar\baz
--   \'  -> escaped quote      'it\'s'   produces it's
--   a trailing \ before the closing quote (e.g. 'boo\') is an error (same as PHP)
-- Backslashes in raw template text outside tags pass through unchanged:
--   use {=namespace}\{=class_name};   -- \ is plain text between two expressions
```

---

## Adding a Custom Block

1. Extend `Blate\Features\Block` and implement `BlockInterface`.
2. Define a `public const NAME = 'myblock';` constant.
3. Implement the lifecycle hooks: `onOpen()`, `onClose()`, `onBreakPoint()`, `onChildBlockFound()`, `onChildContentFound()`, `onChildExpressionFound()`, `requireClose()`.
4. Register in your app bootstrap:
   ```php
   Blate::registerBlock(MyBlock::NAME, MyBlock::class);
   ```

See `src/Features/BlockIf.php` for a compact block with breakpoints, or `src/Features/BlockExtends.php` for a block with strict child validation.

---

## Adding a Global Helper

```php
Blate::registerHelper('myHelper', function (mixed $value): string {
    return (string) $value;
});
```

Built-in helpers are in `src/Helpers/Helpers.php`. The built-in blocks, helpers and
globals are registered by `src/builtins.php` when Blate is first used (`Blate::builtins()`,
called by every registry method and by the constructor): a program
that loads Blate without rendering a template pays nothing for them. A registration still
cannot replace a built-in, since registering runs them first.

Prefer `{$helperName(...)}` over `{helperName(...)}` in all generated template
code. The bare form resolves through the full scope stack and will silently
change behaviour if render data contains a matching key. The `$` prefix always
resolves to the registered helper regardless of user data.

---

## Accessing the Template Scope from a Helper

`Blate::scope()` returns a `BlateTemplateScope` instance while a template is
being rendered. It throws `BlateRuntimeException` (`Message::SCOPE_NOT_AVAILABLE`)
when called outside of any render call.

```php
$scope = Blate::scope();
$scope->data;     // DataContext - full runtime scope stack for the running template
$scope->template; // Blate      - the Blate instance for the running template
```

For nested templates (`{@import}` / `{@extends}`) the stack grows, so
`Blate::scope()` always reflects the **innermost currently executing** template.

```php
Blate::registerHelper('i18n', function (string $key) {
    $locale = Blate::scope()->data->get('locale') ?? 'en';
    return translate($key, $locale);
});
```

Alternatively, pass `$$` as an explicit argument to give a helper access to the
`DataContext` without relying on the static scope stack:

```blate
{$i18n('KEY', $$)}
```

```php
Blate::registerHelper('i18n', function (string $key, DataContext $ctx) {
    $locale = $ctx->get('locale') ?? 'en';
    return translate($key, $locale);
});
```

---

## Inline Array Construction

Blate has no array-literal syntax. Use the three built-in helpers to build arrays
inline within template expressions:

| Helper   | Returns                  | Description                                             |
| -------- | ------------------------ | ------------------------------------------------------- |
| `$map`   | `array` (associative)    | Alternating k/v pairs; keys are DotPath expressions     |
| `$list`  | `array` (indexed)        | Variadic values; always re-indexed from 0               |
| `$store` | `Store` (mutable object) | Wrap existing array for chained `.set()` / `.getData()` |

```blate
{i18n('KEY', $map('name', user.name, 'count', total))}
{= json($map('x.y', 3))}            -- {"x":{"y":3}}
{join($list('a', 'b', 'c'), '-')}    -- a-b-c
{= json($store(base).set('k', v).getData())}
```

`$map` and `$store().set()` accept full DotPath keys (`'foo.bar'`, `'items[0]'`).

---

## Global Variables

Global variables are registered once and available in every template
without being part of the per-render data. They sit between the helpers layer and
user data in the `DataContext` resolution stack; user data shadows them.

Built-in globals (registered by `src/builtins.php` on first use):

| Name                 | Value                 |
| -------------------- | --------------------- |
| `BRACE_OPEN`         | `{`                   |
| `BRACE_CLOSE`        | `}`                   |
| `BLATE_VERSION`      | `Blate::VERSION`      |
| `BLATE_VERSION_NAME` | `Blate::VERSION_NAME` |

```php
// Read-only constant (default) - throws GLOBAL_VAR_IS_NOT_EDITABLE if registered again
Blate::registerGlobalVar('APP_NAME', 'My App');

// With LSP description
Blate::registerGlobalVar('APP_NAME', 'My App', ['description' => 'The application display name.']);

// Editable - can be updated between renders
Blate::registerGlobalVar('REQUEST_ID', $requestId, ['editable' => true]);
```

**Computed (lazy) global variables** are registered with a factory callable instead of a static value. The factory is called on every template access with no arguments and no memoization. Use `Blate::scope()` inside the factory when the current render context is needed.

```php
// Read-only computed global - factory called each time {NOW} is rendered
Blate::registerComputedGlobalVar('NOW', fn () => date('Y-m-d H:i:s'));

// Editable computed global - factory can be replaced later
Blate::registerComputedGlobalVar('REQUEST_LOCALE', fn () => Blate::scope()->data->get('locale') ?? 'en', ['editable' => true]);
```

`Blate::getGlobalVars()` returns the `GlobalVarsContext` instance (implements `ArrayAccess`) used by `DataContext` and the LSP completion provider. Call `->getNames()` to list all registered names.

---

## Disabling Blocks and Helpers

Any registered block or helper can be disabled at runtime without removing its
registration. It can be fully restored with a single enable call.

```php
// Blocks - disabled block causes BLOCK_UNDEFINED parse error in any template
// that references it.
Blate::disableBlock('php');        // prevent {~ ... ~} inline PHP
Blate::enableBlock('php');
Blate::isBlockEnabled('php');      // bool

// Helpers - disabled helper is excluded from the DataContext helpers layer;
// helper-only lookups ({$name()} and pipe filters) fail at render time.
Blate::disableHelper('json');      // accepts name with or without '$' prefix
Blate::enableHelper('json');
Blate::isHelperEnabled('json');    // bool
```

`Message::BLOCK_NOT_REGISTERED` is thrown when disabling an unregistered block.
`Message::HELPER_NOT_FOUND` is thrown when disabling an unregistered helper.

---

## Project Configuration (`.blate.php`)

A `.blate.php` file placed next to `composer.json` is the conventional location
for project-specific helper and global variable registration. The file is a
plain PHP script that calls `Blate::register*` methods.

**Loading**:

```php
Blate::autoLoad();                    // discovers root via getcwd() -> up to composer.json
Blate::autoLoad('/path/to/project');  // explicit root
```

`autoLoad()` returns `true` if the file was loaded, `false` if `.blate.php` was
not found or no project root could be discovered. Already-loaded paths (by
`realpath`) are silently skipped.

`Blate::findProjectRoot(string $start): ?string` is the helper that walks up
from `$start` looking for `composer.json`.

**Deferring the set-up**: `Blate::onFirstUse(callable)` runs a callable when Blate is
first used -- a template constructed (compiled or rendered), or the blocks, helpers or
global variables read -- after the built-ins; one added after the first use runs at once.
A program that renders templates on few of its runs (an API) defers its `autoLoad()`,
registrations and `setCacheDir()` there. The constructor runs it before reading the cache
directory, so a deferred `setCacheDir()` applies to the first template.

The **LSP server** loads it automatically, so completions, hover and
diagnostics reflect project-specific helpers and globals without any editor
configuration: the project of each opened document (the nearest `.blate.php`
at or above it), the `.blate.php` of the packages that project installed, and
the workspace root when it is itself a project. See the LSP section below.

---

## Developer Workflows

A `Makefile` at the project root wraps all common tasks:

```sh
make install      # composer install
make test         # run PHPUnit test suite
make psalm        # run Psalm static analysis
make cs           # check code style (no auto-fix)
make fix          # psalm + oliup-cs fix
make ext          # build VS Code extension -> out/extension.js (installs then removes node_modules)
make ext-watch    # watch-mode build for the VS Code extension
make ext-clean    # remove editors/vscode/out and editors/vscode/node_modules
```

The underlying commands are also available directly:

```sh
./vendor/bin/phpunit --testdox --do-not-cache-result
./vendor/bin/psalm --no-cache
./vendor/bin/oliup-cs fix
```

---

## Editor Support

Editor integrations live under `editors/`:

| Path                     | What it is                                     |
| ------------------------ | ---------------------------------------------- |
| `editors/lsp/server.php` | LSP server entry point (spawns BlateLspServer) |
| `editors/vscode/`        | VS Code extension (syntax + LSP client)        |
| `editors/sublime/`       | Sublime Text syntax definition                 |
| `editors/vim/`           | Vim/Neovim ftdetect + syntax file              |

### LSP Server

`src/Lsp/BlateLspServer.php` implements the Language Server Protocol over stdio.
The entry point `editors/lsp/server.php` bootstraps Composer and starts it.
The `bin/blate-lsp` wrapper provides a convenient CLI entry point.

**Diagnostic codes** emitted by `diagnose()` via `textDocument/publishDiagnostics`:

| Code                   | Severity | Trigger                                                                      |
| ---------------------- | -------- | ---------------------------------------------------------------------------- |
| `blate.helper.shadow`  | Warning  | Unqualified helper call `{upper(...)}` that could be shadowed by render data |
| `blate.helper.unknown` | Error    | `{$name(...)}` where `$name` is not a registered (and enabled) helper        |
| `blate.global.shadow`  | Warning  | Bare global-var access `{BRACE_OPEN}` that could be shadowed by render data  |
| `blate.global.unknown` | Error    | `{$global.FOO}` where `FOO` is not a registered global variable              |

All four scanners read the document through `blankDeadZones()`, which replaces
what Blate does not parse as an expression with spaces of the same byte length,
so match offsets stay those of the document: plain text, `{# #}`, `{~ ~}`,
`{@raw}...{/raw}`, and **string literals** -- a name inside a literal is text, so
`{$map('lang', lang)}` names a key and a variable and only the variable can be
shadowed. Add a scanner and it reads that content, not the raw document.

**Startup and registry logging** - the server logs via `window/logMessage` (type Log) to the VS Code output channel:

- On `initialize`: `logStartupInfo()` emits composer autoload path, all loaded `.blate.php` config paths, and the full registry (globals, blocks, helpers). Any errors thrown while loading `.blate.php` are shown as `window/showMessage` Error popups (with full PHP stack trace) so the user sees them immediately, and are also included in the startup log.
- On subsequent registry changes: `logRegistryUpdate()` is triggered (throttled - once per dispatch cycle) whenever `BlateRegistryChangedEvent` fires. This event is dispatched by `Blate::registerBlock()`, `registerHelper()`, `registerGlobalVar()`, and `registerComputedGlobalVar()` after each successful registration. Logs updated config paths and registry.
- On `initialized`: sends `client/registerCapability` to watch `**/.blate.php`. When any of them changes, the server calls `exit(0)` (triggering a VS Code language-client restart) so the new config loads from a clean slate: a registration cannot be undone, and the project may sit in any subdirectory of the workspace.

**Autoload and config loading** - separation of responsibilities:

- `editors/lsp/server.php`: walks up from `__DIR__` to find `ROOT/vendor/autoload.php`; passes the autoload path to `BlateLspServer`.
- `BlateLspServer` constructor: subscribes to `BlateRegistryChangedEvent`.
- **A project is loaded per document, not per workspace** (`loadProjectOf()`, called from `textDocument/didOpen`): `findProjectRootOf()` walks up from the document's own directory to the nearest `.blate.php`, or failing that to the nearest `composer.json`, whose autoloader the editor still needs; the workspace root is the fallback. The workspace root alone says nothing: a project commonly sits in a subdirectory of it (`backend/`, `api/`, ...), and `Blate::autoLoad()` only ever walks up. `handleInitialize` loads the workspace root the same way, which covers the project that is its own workspace.
- **`loadProject($root)` loads a root once** (`$loadedRoots`, by real path): `{root}/vendor/autoload.php`, then the `.blate.php` of every package `{root}/vendor/composer/installed.json` lists that ships one (`packageConfigDirs()`), then `{root}/.blate.php`. A package registers before the project that installed it, since a registration cannot be replaced and the project must have the last word on a shared name.
- **A package's config matters**: a package registers its template API there and loads it from its own boot code at runtime (OZone's `.blate.php` calls `BlatePlugin::register()`, loaded by its boot hook through `Blate::autoLoad(OZ_OZONE_DIR)`). The editor boots nothing, so without that sweep every helper and global variable a package brings reads as unknown.
- Errors thrown while loading reach the editor through `reportLoadError()`: a `window/showMessage` Error at once once the handshake is done, with the startup summary before that.
- The host project may also call `Blate::autoLoad(ANOTHER_DIR)` independently. Since LSP server and host share the same PHP process and `Blate` static state, all registrations are visible and trigger events.
- `Blate::getLoadedConfigs()` returns `list<string>` of real paths of all loaded `.blate.php` files.
- One process holds one registry: two projects of one workspace that register the same helper name collide (the second throws `HELPER_ALREADY_REGISTERED`, reported to the editor), and nothing can be unregistered between them.

**Startup log** example logged to the VS Code output channel:

```
[blate-lsp] Server initialized (Blate 1.1.1)

Composer autoload : /path/to/blate/vendor/autoload.php
Workspace root    : /path/to/workspace
Loaded configs    : /path/to/workspace/backend/vendor/silassare/ozone/.blate.php, /path/to/workspace/backend/.blate.php

Global variables (4):
  BLATE_VERSION = "1.1.1"  -- Engine version string
  BRACE_CLOSE = "}"
  BRACE_OPEN = "{"
  ...

Blocks (12): capture, comment, each, extends, if, ...
Helpers (18): abs, chunk, date, json, lower, ...
```

**Hover and completions** surface the `description` option registered via
`registerGlobalVar` / `registerComputedGlobalVar`. Both completion items and
hover cards include the description text when it is provided.

**Testing approach** for `BlateLspServerTest`: all pure private helpers are
called via `ReflectionClass`/`setAccessible`. I/O-bound handlers
(`handleCompletion`, `handleHover`, `handleRename`, `handleCodeAction`,
`logStartupInfo`) write directly to `STDOUT` with `fwrite()` and are
intentionally not unit-tested; they are thin wiring over the pure methods that
are tested.

### VS Code Extension

- Source: `editors/vscode/src/extension.ts` (TypeScript)
- Output: `editors/vscode/out/extension.js` (single esbuild bundle, committed)
- Build: `esbuild.js` - platform=node, external=['vscode'], format=cjs; supports `--watch` and `--minify` flags
- No `tsconfig.json` (esbuild transpiles TS natively; tsc is not used)
- Runtime deps: none (`dependencies: {}`); `vscode-languageclient` is bundled at build time
- Dev deps: `esbuild ^0.25`, `vscode-languageclient ^9.0.1`, `@types/node ^20`, `@types/vscode ^1.60`
- The extension spawns `php <editors/lsp/server.php>` via stdio; the PHP executable is configurable via the `blate.phpExecutable` VS Code setting (default: `php`)
- `out/extension.js` is a committed build artifact - run `make ext` after any change to `src/extension.ts`

---

## Test Sample Conventions

Each test case under `tests/samples/<name>/` contains:

- `template.blate` - the template source
- `inject.php` - returns a PHP array of data passed to the template (optional)
- `output.txt` - expected rendered output for valid cases
- `tokens.json` - expected lexer output (for `LexerTest`)

`TemplateSyntaxTest` uses `runValid()` / `runInvalid()` helpers. When `output.txt` does not exist on first run, it is written automatically; edit it to lock the expected output.

---

## Conventions

- `declare(strict_types=1)` is required in every PHP file.
- All files carry the copyright header from `src/Blate.php`.
- PSR-4: namespace `Blate\` maps to `src/`; `Blate\Tests\` maps to `tests/`.
- Error messages are centralized in `src/Message.php` as named constants with `{placeholder}` tokens.
- Exception hierarchy: `BlateException` (base, checked-like) -> `BlateRuntimeException` -> `BlateParserException`.
- `BlateParserException::withToken(Message::CONSTANT, $token)` is the standard way to throw parse errors.
- Template variable resolution at runtime goes through `DataContext->chain('L:I')->get('L:I', key)`, never direct array access.
