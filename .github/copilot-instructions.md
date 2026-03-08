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
.blate source -> Lexer (tokens) -> Parser (PHP codegen) -> cached TemplateParsed class -> rendered output
```

The cached files live next to their source under `blate_cache/<version>/<hash[0:2]>/<hash[2:4]>/`. Cache is invalidated by content hash, file path, or `Blate::VERSION` change.

---

## Key Files

| File                               | Role                                                                                                                 |
| ---------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `src/Blate.php`                    | Main entry: factory (`fromPath`/`fromString`), cache management, block/helper registry                               |
| `src/Lexer.php`                    | Tokenizes raw template text into `Token` objects                                                                     |
| `src/Parser.php`                   | Walks tokens, dispatches to `BlockInterface` implementations, emits PHP code                                         |
| `src/Traits/ParserOutputTrait.php` | PHP codegen helpers (`writeCode`, `write`, `writeExpression`, `getClassBody`) used by `Parser`                       |
| `src/DataContext.php`              | Runtime scope stack - wraps user data with a helpers layer; supports `newContext()`/`popContext()` for scoped blocks |
| `src/SimpleChain.php`              | Fluent path resolver used at runtime: `{foo.bar}` -> `$context->chain()->get('foo')->get('bar')->val()`              |
| `src/TemplateParsed.php`           | Abstract base for compiled templates; handles slot injection for `extends`                                           |
| `src/Features/Block*.php`          | Built-in block implementations (`BlockIf`, `BlockEach`, `BlockSlot`, `BlockExtends`, etc.)                           |
| `src/Expressions/`                 | Expression parser + grammar rules (`Grammar/VarName.php`, `Operator.php`, etc.)                                      |
| `src/bootstrap.php`                | Registers all built-in blocks and helpers at autoload time                                                           |
| `src/assets/output.php.sample`     | Scaffold for compiled template files                                                                                 |

---

## Template Syntax at a Glance

```blate
{varName}            -- print, auto-escaped (htmlspecialchars)
{= varName}          -- print, raw/unescaped
{foo.bar}            -- property access chain
{helper('arg')}      -- call a registered helper
{$helper('arg')}     -- force helper lookup (when name shadows a var)
{# comment #}        -- template comment (stripped at compile time)
{~ echo 'php'; ~}    -- inline PHP code
{@set x = expr; y = expr}
{@if expr}...{:elseif expr}...{:else}...{/if}
{@each val:key:idx in list}...{/each}
{@scoped}...{/scoped}
{@slot name}default{/slot}
{@extends 'path/to/base' context}{@slot name}override{/slot}{/extends}
{@import 'path/to/partial' context}
{@import_raw 'path/to/file'}
{@raw}...literal braces...{/raw}
$$                   -- reference to the raw DataContext inside expressions
```

---

## Adding a Custom Block

1. Extend `Blate\Features\Block` and implement `BlockInterface`.
2. Define a `public const NAME = 'myblock';` constant.
3. Implement the lifecycle hooks: `onOpen()`, `onClose()`, `onBreakPoint()`, `onChildBlockFound()`, `onChildContentFound()`, `onChildExpressionFound()`, `requireClose()`.
4. Register in `bootstrap.php` (or your app bootstrap):
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

Built-in helpers are in `src/Helpers/Helpers.php` and registered in `bootstrap.php`.

---

## Developer Workflows

```sh
# run tests
./run_test
# or
./vendor/bin/phpunit --testdox --do-not-cache-result

# static analysis + code style fix
./csfix
# which runs:
./vendor/bin/psalm --no-cache
./vendor/bin/oliup-cs fix
```

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
- Template variable resolution at runtime goes through `DataContext->chain()->get(key)`, never direct array access.
