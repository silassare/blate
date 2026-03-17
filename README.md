# Blate

A fast, expressive PHP template engine. Templates (`.blate` files) are compiled once to
plain PHP class files and cached on disk - subsequent renders just `include` the cached
file, so there is no parsing overhead at runtime.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Cache Configuration](#cache-configuration)
- [Printing Values](#printing-values)
- [Expressions](#expressions)
  - [Operators](#operators)
  - [Comparators](#comparators)
  - [Logical Operators](#logical-operators)
  - [Null Coalescing](#null-coalescing)
  - [Pipe Filters](#pipe-filters)
  - [Property Access](#property-access)
  - [Raw Data Context](#raw-data-context)
- [Blocks](#blocks)
  - [@set](#set)
  - [@if](#if--elseif--else)
  - [@each](#each--else)
  - [@switch](#switch--case--default)
  - [@repeat](#repeat)
  - [@capture](#capture)
  - [@scoped](#scoped)
  - [@slot and @extends](#slot--extends)
  - [@import](#import)
  - [@import_raw](#import_raw)
  - [@raw](#raw)
  - [Comments](#comments)
  - [Inline PHP](#inline-php)
- [Helpers Reference](#helpers-reference)
  - [Misc / Logic](#misc--logic)
  - [String](#string)
  - [Array](#array)
  - [Number](#number)
  - [Date](#date)
- [Custom Blocks](#custom-blocks)
- [Custom Helpers](#custom-helpers)
- [Template Scope](#template-scope)
- [Global Variables](#global-variables)
- [Project Configuration (`.blate.php`)](#project-configuration-blatephp)
- [Disabling Blocks and Helpers](#disabling-blocks-and-helpers)
- [Editor Support](#editor-support)
- [Comparison: Blate vs Twig vs Blade](#comparison-blate-vs-twig-vs-blade)

---

## Installation

```sh
composer require silassare/blate
```

---

## Quick Start

```php
use Blate\Blate;

// Render a template file
$html = Blate::fromPath('/path/to/views/page.blate')
    ->render(['title' => 'Home', 'user' => ['name' => 'Alice', 'admin' => true]]);

// Render a template string
$html = Blate::fromString('Hello, {name}!')
    ->render(['name' => 'World']);
```

A minimal template:

```blate
<!DOCTYPE html>
<html>
  <head><title>{title}</title></head>
  <body>
    {@if user.admin}
      <p>Welcome back, admin {user.name}!</p>
    {:else}
      <p>Hello, {user.name}.</p>
    {/if}
  </body>
</html>
```

---

## Cache Configuration

By default compiled templates are cached in `blate_cache/` relative to each source file.
You can set a global custom directory:

```php
Blate::setCacheDir('/var/cache/blate');

echo Blate::getCacheDir(); // /var/cache/blate
```

The cache is automatically invalidated whenever the source content, file path, or
`Blate::VERSION` changes. No manual cache busting is needed.

---

## Printing Values

| Syntax      | Effect                                     |
| ----------- | ------------------------------------------ |
| `{name}`    | Print escaped (htmlspecialchars, XSS-safe) |
| `{= name}`  | Print raw / unescaped                      |
| `{'hello'}` | Print a literal string                     |
| `{42}`      | Print a literal number                     |
| `{foo.bar}` | Print property chain (escaped)             |

---

## Expressions

### Operators

Standard arithmetic and string operators are supported inside `{ }`:

```blate
{a + b}
{price * qty}
{'Hello, ' + name + '!'}
{score - penalty}
{total / count}
```

### Comparators

```blate
{@if age >= 18}adult{/if}
{@if role == 'admin'}...{/if}
{@if x != y}...{/if}
{@if a <= b}...{/if}
```

### Logical Operators

```blate
{@if isLoggedIn && hasAccess}...{/if}
{@if isGuest || isBot}...{/if}
{@if !isHidden}...{/if}
```

### Null Coalescing

`??` returns the right-hand side when the left-hand value is `null`:

```blate
{user.nickname ?? 'Guest'}
{config.title ?? 'Untitled'}
```

### PHP Literals

`true`, `false`, and `null` are PHP literals when used as expression heads.
All casings are supported (`TRUE`, `FALSE`, `NULL`).
Inside a dot-chain they remain normal property lookups.

```blate
{@if true}always{/if}
{@if false}never{:else}always{/if}
{@if null}never{:else}always{/if}
{= value ?? null}         -- null as null-coalesce fallback
{$if(true, 'yes', 'no')}  -- literal as helper argument
{foo.null}                -- property lookup, not a literal
```

### String Literal Escaping

String literals in expressions follow the same escape rules as PHP
single-quoted and double-quoted strings:

- `\'` — escaped single quote inside a single-quoted string
- `\\` — escaped backslash (produces one `\` character)
- A trailing `\` before the closing quote (e.g. `'boo\'`) is treated as an
  escaped quote, so the string never closes and a parse error is thrown — same
  as PHP. Use `\\` to end a string with a literal backslash: `'boo\\'`.

```blate
{foo['bar\\baz']}     -- key is bar\baz (one backslash)
{foo['it\'s fine']}   -- key is it's fine
```

This escaping applies only to string literals **inside expressions** (`{...}`).
Backslashes in raw template text outside tags are passed through unchanged.

```blate
use {=namespace}\{=class_name};   -- \ is literal text between two expressions
```

### Pipe Filters

Apply a helper as a filter with `|`. The left-hand expression becomes the first
argument. Multiple pipes are chained left to right:

```blate
{name | upper}                     -- upper(name)
{body | truncate(120)}             -- truncate(body, 120)
{price | number(2) | escape}       -- escape(number(price, 2))
{tags | join(', ')}                -- join(tags, ', ')
```

Pipe-filter names always resolve against the helpers layer only — a callable
stored in user data cannot be used as a pipe filter, even if its key matches.
Use `{foo | upper}` only for registered helpers; to call a user-data callable
directly, use `{upper(foo)}` (full-stack lookup) or pass it through a helper.

### Property Access

Use dot notation to traverse nested data. Square-bracket subscripts are supported:

```blate
{user.address.city}
{items[0].name}
{items[idx].value}
```

### Raw Data Context

`$$` refers to the raw data passed to `render()`. Useful when forwarding context:

```blate
{@import 'partials/header.blate' $$}
{@extends 'layouts/base.blate' $$}
```

---

## Blocks

All block tags follow the pattern `{@name ...}...{/name}`.
Breakpoints use `{:name}`.

### @set

Assign one or more variables in the current data scope:

```blate
{@set count = items|length; label = 'Items'}

{count} {label}
```

### @if / :elseif / :else

```blate
{@if score >= 90}
  Excellent
{:elseif score >= 60}
  Passing
{:else}
  Below average
{/if}
```

### @each / :else

Iterate over a list. Supports `key` and `index` variables.
The optional `{:else}` branch renders when the list is empty or null.

Three built-in variables are injected into every iteration's scope regardless
of the syntax form used:

| Variable   | Type   | Description                   |
| ---------- | ------ | ----------------------------- |
| `is_first` | `bool` | `true` on the first iteration |
| `is_last`  | `bool` | `true` on the last iteration  |

At runtime the iterable is normalized to an `Iterator`: `IteratorAggregate`
instances are unwrapped, plain arrays are wrapped in `ArrayIterator`. The loop
then fetches each element via `current()`/`key()`, advances with `next()`, and
reads `valid()` to determine `is_last` — without materializing the whole
sequence first. Memory usage is O(1) even for generators or large database
cursors.

```blate
{@each item in products}
  <li
    {@if is_first}class="first"{/if}
    {@if is_last}class="last"{/if}
  >{item.name} - {item.price | $number(2)}</li>
{:else}
  <li>No products found.</li>
{/each}

<!-- with key -->
{@each item:key in map}
  {key}: {item}
{/each}

<!-- with key and index -->
{@each item:key:idx in list}
  {idx}. [{key}] {item} (first={is_first}, last={is_last})
{/each}
```

### @switch / :case / :default

Branch on a single expression using strict equality (`===`).
`{:default}` is optional and must come after all `{:case}` branches.

```blate
{@switch status}
  {:case 'active'}
    <span class="green">Active</span>
  {:case 'banned'}
    <span class="red">Banned</span>
  {:default}
    <span>Unknown</span>
{/switch}
```

### @repeat

Loop a block `n` times. Optionally expose the 0-based index as a variable.

Two built-in variables are injected on every iteration:

| Variable   | Type   | Description                   |
| ---------- | ------ | ----------------------------- |
| `is_first` | `bool` | `true` on the first iteration |
| `is_last`  | `bool` | `true` on the last iteration  |

```blate
{@repeat 3}*{/repeat}       -- outputs: ***

{@repeat count as i}
  Row {i}
{/repeat}

{@repeat 3}
  {@if is_first}<ul>{/if}
  <li>item</li>
  {@if is_last}</ul>{/if}
{/repeat}
```

The count expression can be any expression: `{@repeat items|length as i}`.

### @capture

Render a block body into a string variable instead of printing it immediately:

```blate
{@capture greeting}
  Hello, {user.name}! You have {count} messages.
{/capture}

<title>{greeting | stripTags}</title>
<body>{= greeting}</body>
```

### @scoped

Create an isolated child scope. Variables set inside do not leak to the parent.
Parent variables remain readable inside the scope:

```blate
{@set x = 10}

{@scoped}
  {@set x = 99}
  Inside: {x}        -- 99
{/scoped}

Outside: {x}          -- 10
```

### @slot / @extends

Define named slots in a base layout and override them in child templates.

**layouts/base.blate:**

```blate
<!DOCTYPE html>
<html>
  <head>
    <title>{@slot title}My Site{/slot}</title>
  </head>
  <body>
    <main>{@slot body}Default content{/slot}</main>
    <footer>{@slot footer}(c) 2025{/slot}</footer>
  </body>
</html>
```

**pages/about.blate:**

```blate
{@extends 'layouts/base.blate' $$}
  {@slot title}About Us{/slot}
  {@slot body}
    <p>We build great software.</p>
  {/slot}
{/extends}
```

The context argument (`$$` or any expression) is passed to the base template.
Only `{@slot}` tags and whitespace are allowed directly inside `{@extends}`.

### @import

Render another template inline with a given data context:

```blate
{@import 'partials/nav.blate' $$}
{@import 'partials/card.blate' product}
```

### @import_raw

Include a file's raw content without any template processing:

```blate
{@import_raw 'assets/logo.svg'}
{@import_raw 'content/terms.html'}
```

### @raw

Output a literal block bypassing all Blate processing:

```blate
{@raw}
  Example: {@if condition}...{/if}
{/raw}
```

### Comments

Stripped at compile time; never appear in rendered output:

```blate
{# This is a comment #}

{#
  Multi-line comment.
  Nothing here reaches the output.
#}
```

### Inline PHP

Embed arbitrary PHP expressions directly (use sparingly):

```blate
{~ $ts = time(); ~}
Created: {~ echo date('Y-m-d', $ts); ~}
```

The snippet is emitted verbatim into the compiled template's `build()` method,
which receives `$context` as its only parameter. `$context` is the
`Blate\DataContext` object for the current render and exposes the full scope
stack. Common uses:

```blate
{~ $val = $context->chain()->get('user')->get('name')->val(); ~}
{~ $context->set('computed', strtoupper((string) $val)); ~}
Name: {computed}
```

All standard PHP globals (`$_SERVER`, `$_SESSION`, etc.) and any PHP variables
defined in earlier `{~ ~}` blocks within the same template are also in scope.
Php snippets inside `{@extends}` child templates share the same scope as their
parent's `build()` call.

---

## Helpers Reference

Helpers are callable functions registered globally with `Blate::registerHelper`.
They can be called in expressions and as pipe filters.

There are three ways to invoke a helper named `upper`:

```blate
{upper(title)}     -- full stack lookup: user-data key 'upper' shadows the helper
{$upper(title)}    -- helper-only lookup: immune to user-data shadowing (preferred)
{title | upper}    -- pipe filter: always uses helper-only lookup (same as $upper)
```

The `$` prefix and pipe filters both bypass the variable scope stack and consult
only the registered helpers layer. A callable stored in user data can never be
invoked as a pipe filter; only registered helpers are resolved in that position.

**Prefer `$helper(...)` over `helper(...)` in template expressions.** The bare
form resolves through the full scope stack and silently changes behaviour if
render data contains a key with the same name — a hard-to-trace runtime bug.
Use the bare form only when intentional user-data shadowing is desired.

---

### Misc / Logic

| Helper    | Signature                  | Description                                                                                                                                                                      |
| --------- | -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `has`     | `has(target, prop)`        | `true` if `target` has property/index `prop`.                                                                                                                                    |
| `type`    | `type(value, type?)`       | Without type: returns debug type string. With type: returns `true`/`false`. Accepted types: `null`, `bool`, `int`, `float`, `string`, `array`, `object`, `numeric`, or any FQCN. |
| `cast`    | `cast(value, type)`        | Cast to `int`, `float`, `string`, `bool`, or `array`.                                                                                                                            |
| `default` | `default(value, fallback)` | Returns `value` unless `null` or `''`; otherwise `fallback`.                                                                                                                     |
| `if`      | `if(condition, a, b)`      | Returns `a` when truthy, `b` otherwise.                                                                                                                                          |

---

### String

| Helper       | Signature                        | Description                                                                                                                                                                                                                                                                                                              |
| ------------ | -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `escape`     | `escape(value)`                  | `htmlspecialchars` - applied automatically to `{expr}` output.                                                                                                                                                                                                                                                           |
| `escapeHtml` | `escapeHtml(value)`              | `htmlspecialchars` with `ENT_QUOTES\|ENT_SUBSTITUTE`. Preserves UTF-8 multibyte chars; use for explicit escaping.                                                                                                                                                                                                        |
| `attrs`      | `attrs(array, raw?)`             | Build an HTML attribute string. Default: `false`/`null`/`''` omit the attribute, `true` emits a standalone boolean attribute (e.g. `disabled`). Pass `raw=1` (in templates) or `true` (in PHP) to emit all values as strings instead -- useful for `data-*` / ARIA attributes where `false` means `"false"`, not absent. |
| `quote`      | `quote(str)`                     | Wrap in single quotes, escaping internal quotes.                                                                                                                                                                                                                                                                         |
| `unquote`    | `unquote(str)`                   | Strip surrounding single or double quotes.                                                                                                                                                                                                                                                                               |
| `concat`     | `concat(a, b, ...)`              | Concatenate strings (variadic).                                                                                                                                                                                                                                                                                          |
| `upper`      | `upper(str)`                     | Multibyte uppercase.                                                                                                                                                                                                                                                                                                     |
| `lower`      | `lower(str)`                     | Multibyte lowercase.                                                                                                                                                                                                                                                                                                     |
| `ucfirst`    | `ucfirst(str)`                   | Uppercase first character (multibyte-safe).                                                                                                                                                                                                                                                                              |
| `trim`       | `trim(str, chars?)`              | Strip leading/trailing characters (default: whitespace).                                                                                                                                                                                                                                                                 |
| `replace`    | `replace(str, search, replace)`  | `str_replace` wrapper.                                                                                                                                                                                                                                                                                                   |
| `split`      | `split(str, sep, limit?)`        | Split; empty separator splits into characters.                                                                                                                                                                                                                                                                           |
| `substr`     | `substr(str, start, length?)`    | Multibyte substring.                                                                                                                                                                                                                                                                                                     |
| `truncate`   | `truncate(str, length, suffix?)` | Truncate, appending `suffix` (default `'...'`).                                                                                                                                                                                                                                                                          |
| `nl2br`      | `nl2br(str)`                     | Insert `<br>` before newlines.                                                                                                                                                                                                                                                                                           |
| `url`        | `url(str)`                       | RFC-3986 URL-encode.                                                                                                                                                                                                                                                                                                     |
| `json`       | `json(value, flags?, pretty?)`   | `json_encode` with `JSON_HEX_TAG\|JSON_HEX_AMP\|JSON_THROW_ON_ERROR` by default. `<`, `>`, `&` are unicode-escaped so the output is safe to embed in HTML `<script>` blocks. Pass `true` as the third argument to enable `JSON_PRETTY_PRINT` formatting.                                                                 |
| `startsWith` | `startsWith(str, prefix)`        | `true` if `str` starts with `prefix`.                                                                                                                                                                                                                                                                                    |
| `endsWith`   | `endsWith(str, suffix)`          | `true` if `str` ends with `suffix`.                                                                                                                                                                                                                                                                                      |
| `contains`   | `contains(haystack, needle)`     | Substring check for strings; membership check for arrays.                                                                                                                                                                                                                                                                |
| `repeat`     | `repeat(str, times)`             | Repeat a string N times.                                                                                                                                                                                                                                                                                                 |
| `pad`        | `pad(str, length, pad?, side?)`  | Pad to length. `side`: `'right'` (default), `'left'`, `'both'`.                                                                                                                                                                                                                                                          |
| `sprintf`    | `sprintf(format, ...)`           | `sprintf`-style placeholder formatting.                                                                                                                                                                                                                                                                                  |
| `stripTags`  | `stripTags(str, allowed?)`       | Strip HTML/PHP tags; `allowed` keeps specified tags.                                                                                                                                                                                                                                                                     |

---

### Array

| Helper    | Signature                       | Description                                                                                        |
| --------- | ------------------------------- | -------------------------------------------------------------------------------------------------- |
| `join`    | `join(array, glue?)`            | `implode` (default glue `''`).                                                                     |
| `keys`    | `keys(array)`                   | `array_keys`.                                                                                      |
| `values`  | `values(array)`                 | `array_values`.                                                                                    |
| `length`  | `length(value)`                 | String length (`mb_strlen`) or array count.                                                        |
| `count`   | `count(array)`                  | Alias for `length`.                                                                                |
| `first`   | `first(array)`                  | First element, or `null`.                                                                          |
| `last`    | `last(array)`                   | Last element, or `null`.                                                                           |
| `slice`   | `slice(array, offset, length?)` | `array_slice`.                                                                                     |
| `reverse` | `reverse(array\|string)`        | Reverse array or string (multibyte-safe).                                                          |
| `unique`  | `unique(array)`                 | Remove duplicate values.                                                                           |
| `flatten` | `flatten(array)`                | Flatten one level deep.                                                                            |
| `chunk`   | `chunk(array, size)`            | Split into chunks of `size`.                                                                       |
| `merge`   | `merge(a, b, ...)`              | `array_merge` (variadic).                                                                          |
| `sort`    | `sort(array)`                   | Sort ascending, re-indexed from 0.                                                                 |
| `sortBy`  | `sortBy(array, key)`            | Sort array of objects/maps by field `key`.                                                         |
| `range`   | `range(start, end, step?)`      | Create a range array.                                                                              |
| `min`     | `min(array)`                    | Minimum value.                                                                                     |
| `max`     | `max(array)`                    | Maximum value.                                                                                     |
| `sum`     | `sum(array)`                    | Sum of all values.                                                                                 |
| `avg`     | `avg(array)`                    | Arithmetic mean (returns `0.0` for empty arrays).                                                  |
| `shuffle` | `shuffle(array)`                | Return a shuffled copy.                                                                            |
| `filter`  | `filter(array, value?)`         | Remove falsy values; or keep only elements `=== value`.                                            |
| `map`     | `map(k1, v1, k2, v2, ...)`      | Build an associative array from key/value pairs. Keys are DotPath expressions (`'foo.bar'` nests). |
| `list`    | `list(v1, v2, ...)`             | Build an indexed array from the given values.                                                      |
| `store`   | `store(array?)`                 | Wrap an array in a mutable `Store` for chained `.set()` calls.                                     |

#### Inline Array Construction

Blate has no array-literal syntax (`[...]` is a subscript operator only), so the
three helpers above cover those use cases:

```blate
{# pass an associative array to a helper #}
{i18n('KEY', $map('name', user.name, 'count', total))}

{# DotPath keys produce nested arrays #}
{= json($map('user.name', user.name, 'addr.city', user.address.city))}
{# -> {"user":{"name":"..."},"addr":{"city":"..."}} #}

{# indexed array #}
{join($list(1, 2, 3), '-')}    {# -> 1-2-3 #}

{# start from existing data, mutate, then pass on #}
{= json($store(defaults).set('extra', 1).getData())}
```

Keys passed to `$map` and `$store().set()` are full DotPath expressions — dots
create intermediate objects and bracket subscripts (`items[0]`) address array
indices.

---

### Number

| Helper   | Signature                                          | Description                                        |
| -------- | -------------------------------------------------- | -------------------------------------------------- |
| `number` | `number(n, decimals?, dec_point?, thousands_sep?)` | Format with thousands separator and decimal point. |
| `abs`    | `abs(n)`                                           | Absolute value.                                    |
| `round`  | `round(n, precision?)`                             | Round to decimal places.                           |
| `floor`  | `floor(n)`                                         | Round down to integer.                             |
| `ceil`   | `ceil(n)`                                          | Round up to integer.                               |
| `clamp`  | `clamp(n, min, max)`                               | Constrain to `[min, max]`.                         |
| `random` | `random(min?, max?)`                               | Cryptographically secure random integer.           |

---

### Date

| Helper | Signature                        | Description                                                                        |
| ------ | -------------------------------- | ---------------------------------------------------------------------------------- |
| `now`  | `now(microtime?)`                | Current Unix timestamp; `true` returns float with microseconds.                    |
| `date` | `date(date, format?, timezone?)` | Format a `DateTimeInterface`, timestamp, or date string. Default: `'Y-m-d H:i:s'`. |

---

## Custom Blocks

1. Create a class extending `Blate\Features\Block`.
2. Define `public const NAME = 'myblock';`.
3. Override the lifecycle hooks you need.
4. Call `Blate::registerBlock(MyBlock::NAME, MyBlock::class)`.

```php
use Blate\Blate;
use Blate\Features\Block;
use Blate\Token;

class BlockAlert extends Block
{
    public const NAME = 'alert';

    public function getName(): string { return self::NAME; }

    public function onOpen(): void
    {
        $this->lexer->nextIs(Token::T_TAG_CLOSE, null, true); // consume `}`
        $this->parser->writeCode('echo \'<div class="alert">\';');
    }

    public function onClose(): void
    {
        $this->parser->writeCode('echo \'</div>\';');
    }

    public function requireClose(): bool { return true; }
}

Blate::registerBlock(BlockAlert::NAME, BlockAlert::class);
```

**Lifecycle hooks** (all have default no-op implementations in the base class):

| Method                                                        | When it fires                           |
| ------------------------------------------------------------- | --------------------------------------- |
| `onOpen()`                                                    | Opening tag `{@blockname ...}`          |
| `onClose()`                                                   | Closing tag `{/blockname}`              |
| `onBreakPoint(TokenInterface $token)`                         | A `{:name}` tag inside the block        |
| `onChildBlockFound(BlockInterface $block)`                    | A nested block opens                    |
| `onChildContentFound(TokenInterface $token)`                  | Raw text inside the block               |
| `onChildExpressionFound(TokenInterface $token, bool $escape)` | An expression inside the block          |
| `requireClose(): bool`                                        | Return `true` to require `{/blockname}` |

---

## Custom Helpers

```php
use Blate\Blate;

Blate::registerHelper('slugify', function (string $str): string {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($str)));
});
```

Use in templates:

```blate
{slugify(article.title)}         -- may be shadowed by user data
{$slugify(article.title)}        -- guaranteed helper, ignores user data
{article.title | slugify}        -- pipe filter: also guaranteed, ignores user data
```

The `$` prefix and pipe-filter syntax always resolve against the helpers layer
only, so a `slugify` key in the template data cannot intercept the call.

---

## Template Scope

`Blate::scope()` returns a `BlateTemplateScope` instance while a template is
being rendered. It throws `BlateRuntimeException` when called from outside any
active render call.

```php
use Blate\Blate;
use Blate\BlateTemplateScope;

$scope = Blate::scope(); // BlateTemplateScope
$scope->data;            // Blate\DataContext - full runtime scope stack
$scope->template;        // Blate\Blate      - the Blate instance for the running template
```

For nested templates (`{@import}` / `{@extends}`) the scope stack grows, so
`Blate::scope()` always reflects the **innermost currently executing** template.

The primary use case is reading render data inside a helper without requiring
the template author to pass extra arguments:

```php
Blate::registerHelper('i18n', function (string $key) {
    $locale = Blate::scope()->data->get('locale') ?? 'en';
    return translate($key, $locale);
});
```

```blate
{$i18n('WELCOME_MSG')}
```

Alternatively, pass `$$` as an explicit argument to give a helper access to the
current `DataContext` directly, without the static scope stack:

```blate
{$i18n('WELCOME_MSG', $$)}
```

```php
use Blate\DataContext;

Blate::registerHelper('i18n', function (string $key, DataContext $ctx) {
    $locale = $ctx->get('locale') ?? 'en';
    return translate($key, $locale);
});
```

---

## Global Variables

Global variables are values registered once at application bootstrap time and
available in every template without being part of the per-render data. They
sit between the helpers layer and the user data in the resolution stack, so
user data can shadow them when needed.

### Built-in globals

| Name                 | Value              | Notes                 |
| -------------------- | ------------------ | --------------------- |
| `BRACE_OPEN`         | `{`                | Literal opening brace |
| `BRACE_CLOSE`        | `}`                | Literal closing brace |
| `BLATE_VERSION`      | e.g. `1.1.0`       | Engine version string |
| `BLATE_VERSION_NAME` | e.g. `Blate 1.1.0` | Engine display name   |

Outputting a literal brace without `{@raw}`:

```blate
Example code: {BRACE_OPEN}@if condition{BRACE_CLOSE}...{BRACE_OPEN}/if{BRACE_CLOSE}
```

Outputs:

```
Example code: {@if condition}...{/if}
```

### Registering custom globals

Names must be valid Blate identifiers: a letter or underscore followed by
letters, digits, `$`, or underscores (`APP_NAME`, `requestId`, `_debug`).
An invalid name throws a `BlateRuntimeException` at registration time.

```php
// Read-only constant (default) - throws if registered again
Blate::registerGlobalVar('APP_NAME', 'My App');

// With description shown in LSP hover / completions
Blate::registerGlobalVar('APP_NAME', 'My App', ['description' => 'The application display name.']);

// Editable - can be updated between renders
Blate::registerGlobalVar('REQUEST_ID', '', ['editable' => true]);
Blate::registerGlobalVar('REQUEST_ID', $requestId, ['editable' => true]);
```

Use in templates exactly like any other variable:

```blate
<title>{APP_NAME}</title>
<footer>Powered by Blate {BLATE_VERSION}</footer>
```

User data with the same name takes priority over the global variable.

### $global reference

`$global` is a special chain-head that resolves a property **directly from the global
vars layer**, bypassing user data entirely. It is the mirror of `$$` for global variables:

```blate
{APP_NAME}           -- user data can shadow this
{$global.APP_NAME}   -- always reads the registered global, never shadowed
```

The dot-chain can be as deep as needed:

```blate
{$global.APP_NAME}
{$global.THEME.color}   -- if THEME is an object / associative array
```

This is useful in base layouts where render data from a child template may
contain keys that coincidentally match global variable names.

### Computed (lazy) globals

A computed global has a factory callable instead of a static value. The factory
is called on **every template access** — there is no memoization.

```php
// Read-only computed global
Blate::registerComputedGlobalVar('NOW', fn () => date('Y-m-d H:i:s'));

// Editable computed global - factory can be replaced later
Blate::registerComputedGlobalVar(
    'REQUEST_LOCALE',
    fn () => Blate::scope()->data->get('locale') ?? 'en',
    ['editable' => true],
);
```

`Blate::getGlobalVars()` returns the `GlobalVarsContext` singleton
(`ArrayAccess`). `->getNames()` lists all registered names (static and
computed).

---

## Project Configuration (`.blate.php`)

A `.blate.php` file placed next to `composer.json` in your project root is
the conventional place to register project-specific helpers, global variables,
and computed globals once. It is a plain PHP file that calls
`Blate::register*` methods:

```php
<?php
// .blate.php

use Blate\Blate;

Blate::registerHelper('currency', fn (float $v) => '$' . number_format($v, 2));
Blate::registerGlobalVar('APP_NAME', 'My App');
Blate::registerComputedGlobalVar('NOW', fn () => date('Y-m-d'));
```

### Loading the config file

**Via `autoLoad()` in your application bootstrap:**

```php
// e.g. in public/index.php or config/blate.php
Blate::autoLoad();            // auto-discovers composer.json upward from getcwd()
Blate::autoLoad('/path/to/project');  // explicit root
```

`autoLoad()` returns `true` when the file was loaded, `false` when `.blate.php`
was not found or the project root could not be determined. Double-calls with
the same resolved path are silently skipped.

**The LSP loads it automatically.** When the language server receives the editor
workspace root it calls `Blate::autoLoad($root)` so that project-specific
helpers and global variables appear in completions and hover documentation
without any manual configuration.

**The VS Code extension restarts the LSP automatically** when `.blate.php` is
saved, created, or deleted, so changes to your config are reflected immediately
in completions and hover without reloading the editor window.

### How project root detection works

`Blate::findProjectRoot(?string $start)` walks upward from `$start`
(or `getcwd()` when `null`) until it finds a directory containing
`composer.json`. It returns the absolute path of that directory, or `null`
if none is found before the filesystem root.

### Inspecting loaded configs

`Blate::getLoadedConfigs()` returns a `list<string>` of the real paths of all
`.blate.php` files that have been loaded so far (via `autoLoad()` or any other
path). Useful for debugging or logging which config files are active:

```php
foreach (Blate::getLoadedConfigs() as $path) {
    echo "Loaded: $path\n";
}
```

---

## Disabling Blocks and Helpers

Any registered block or helper can be disabled at runtime without unregistering
it. Disabled blocks and helpers retain their registration and can be fully
restored with a single call.

### Blocks

A disabled block behaves as if it is not registered: any template that
references it will fail at **compile time** with an `Unknown block name` error.

```php
// Disallow inline PHP blocks in user-supplied templates
Blate::disableBlock('php');

// Restore
Blate::enableBlock('php');

// Query
Blate::isBlockEnabled('php'); // false while disabled
```

### Helpers

A disabled helper is excluded from the runtime helpers layer. Helper-only
lookups (`{$name()}` and pipe filters) will fail at **render time** with a
`Helper "name" is not registered` error. Plain-name lookups (`{name()}`) may
still resolve through user data if a matching key exists there.

```php
// Remove a helper from template context
Blate::disableHelper('json');

// Restore
Blate::enableHelper('json');

// Query (the '$' prefix is accepted but optional)
Blate::isHelperEnabled('json'); // false while disabled
```

Both methods accept the helper name with or without the leading `$` prefix.

---

## Editor Support

Syntax-highlighting files for `.blate` templates live in the [`editors/`](editors/)
directory. Installation instructions per editor are below and also in
[`editors/README.md`](editors/README.md).

### VS Code

**Local install (development / personal use):**

```sh
cp -r editors/vscode ~/.vscode/extensions/blate
```

Restart VS Code (or run `Developer: Reload Window`). `.blate` files are detected
and highlighted automatically.

**Build `.vsix`:**

```sh
npm install -g @vscode/vsce
cd editors/vscode
vsce package            # produces blate-1.0.0.vsix
code --install-extension blate-1.0.0.vsix
```

---

### IntelliJ / PhpStorm / WebStorm

The VS Code extension directory doubles as a TextMate bundle that JetBrains IDEs
accept directly via the **TextMate Bundles** plugin (bundled since 2023.2):

1. `Settings > Editor > TextMate Bundles > +`
2. Select the `editors/vscode/` directory.
3. Click **OK** and restart the IDE.

`.blate` files are recognised and highlighted without any further configuration.

---

### Sublime Text 3 / 4

```sh
# macOS
cp editors/sublime/Blate.sublime-syntax \
  "$HOME/Library/Application Support/Sublime Text/Packages/User/"

# Linux
cp editors/sublime/Blate.sublime-syntax \
  "$HOME/.config/sublime-text/Packages/User/"

# Windows (PowerShell)
Copy-Item editors\sublime\Blate.sublime-syntax `
  "$env:APPDATA\Sublime Text\Packages\User\"
```

Sublime Text detects `.blate` files automatically after the file is in place
(no restart required).

**Publish to Package Control:**

1. Fork the [package_control_channel](https://github.com/wbond/package_control_channel) repository.
2. Add an entry pointing to this repository under `repository/b.json`.
3. Open a pull request.

---

### Vim / Neovim

**Manual install:**

```sh
# Vim
cp editors/vim/syntax/blate.vim   ~/.vim/syntax/
cp editors/vim/ftdetect/blate.vim ~/.vim/ftdetect/

# Neovim
cp editors/vim/syntax/blate.vim   ~/.config/nvim/syntax/
cp editors/vim/ftdetect/blate.vim ~/.config/nvim/ftdetect/
```

**Via a plugin manager (recommended):**

Add to your plugin manager config, pointing to this repository. For example:

```vim
" vim-plug
Plug 'silassare/blate', { 'rtp': 'editors/vim' }
```

```lua
-- lazy.nvim
{ 'silassare/blate', config = false, opts = {}, dir = 'editors/vim' }
-- or directly:
{ dir = '/path/to/blate/editors/vim' }
```

PHP syntax inside `{~ ... ~}` blocks is highlighted automatically when
`$VIMRUNTIME/syntax/php.vim` is present (standard Vim/Neovim distribution).

---

## Comparison: Blate vs Twig vs Blade

All three engines use the same fundamental strategy: compile to native PHP once,
cache on disk, and `include` the cached file on subsequent requests. Hot-path
render performance is functionally equivalent across all three.

### Compilation pipeline

| Aspect             | Blate                                     | Blade (Laravel)                       | Twig                                   |
| ------------------ | ----------------------------------------- | ------------------------------------- | -------------------------------------- |
| Compiled output    | PHP class extending `BlateTemplateParsed` | Plain PHP file with echo/control-flow | PHP class extending `Twig\Template`    |
| Cache key          | content hash + file path + engine version | file path + mtime                     | source hash                            |
| Cache invalidation | file change OR engine version bump        | file change                           | file change                            |
| Compile overhead   | Minimal - single-pass lexer + parser      | Medium - multiple compiler passes     | Highest - full AST with visitor passes |

Blate has the lightest compile step of the three because it is a single-pass
lexer/parser with no component resolver or service-container lookup.

### Iteration

All three engines inject loop metadata into the iteration scope. Blate and Twig
keep memory usage O(1) for streaming iterables (generators, database cursors);
Blade materialises the entire `$loop` object upfront.

| Feature               | Blate                                               | Blade               | Twig                            |
| --------------------- | --------------------------------------------------- | ------------------- | ------------------------------- |
| Basic iteration       | `{@each val in list}`                               | `@foreach`          | `{% for %}`                     |
| Key access            | `{@each val:key in list}`                           | `$loop->index`      | `loop.key` (assoc only)         |
| Iteration index       | `{@each val:key:idx in list}`                       | `$loop->index`      | `loop.index` (1-based)          |
| First / last flags    | `is_first` / `is_last` (always in scope)            | `$loop->first/last` | `loop.first` / `loop.last`      |
| Memory for generators | **O(1)** - lookahead via `Iterator::next()/valid()` | O(n) materialises   | **O(1)** - native `for` loop    |
| Loop of N with index  | `{@repeat n as i}` + `is_first`/`is_last`           | `@for`              | `{% for i in 0..n-1 %}`         |
| Empty-list fallback   | `{:else}` branch on `@each`                         | `@forelse`          | `{% else %}` inside `{% for %}` |

### Security

All three engines auto-escape HTML output by default, which is the most
important XSS protection:

|                               | Blate                            | Blade                 | Twig                                              |
| ----------------------------- | -------------------------------- | --------------------- | ------------------------------------------------- |
| Auto-escape                   | `{expr}` -> `htmlspecialchars()` | `{{ }}` -> `e()`      | `{{ }}` -> `twig_escape_filter()`                 |
| Raw / unescaped output        | `{= expr}`                       | `{!! !!}`             | `{{ expr\|raw }}`                                 |
| Inline PHP in templates       | Yes - `{~ ... ~}`                | Yes - `@php`          | **No**                                            |
| Sandbox for untrusted authors | **No**                           | **No**                | **Yes** - restricts accessible methods/properties |
| Disable built-in blocks       | Yes - `Blate::disableBlock()`    | No built-in mechanism | Partial via extension                             |
| Disable helpers               | Yes - `Blate::disableHelper()`   | No built-in mechanism | Partial via extension                             |

**Twig** is the only engine with a proper sandbox, making it suitable for
cases where template authors are untrusted (e.g., user-editable templates).
Blate and Blade both allow escaping to full PHP when needed, which is powerful
but means a malicious template author has full server access.

Blate's `disableBlock()` / `disableHelper()` API provides a lighter-weight
alternative: you can strip dangerous blocks like `{@php}` or restrict the
helper surface without a full sandbox.

### Template reuse

| Feature              | Blate                                            | Blade                            | Twig                            |
| -------------------- | ------------------------------------------------ | -------------------------------- | ------------------------------- |
| Inheritance          | `{@extends 'base' ctx}{@slot name}...{/extends}` | `@extends` + `@section`/`@yield` | `{% extends %}` + `{% block %}` |
| Inclusion            | `{@import 'partial' ctx}`                        | `@include`                       | `{% include %}`                 |
| Raw file embed       | `{@import_raw 'file'}`                           | `@includeRaw` (not built-in)     | N/A                             |
| Default slot content | `{:default}` breakpoint inside `{@slot}`         | `@section` with fallback         | `{{ block() }}` in child        |

### Data and helpers

| Feature                   | Blate                                                            | Blade                        | Twig                          |
| ------------------------- | ---------------------------------------------------------------- | ---------------------------- | ----------------------------- |
| Global variables          | `Blate::registerGlobalVar()`                                     | `View::share()`              | `$twig->addGlobal()`          |
| Computed global variables | `Blate::registerComputedGlobalVar()` (lazy, no memoization)      | No built-in                  | No built-in                   |
| Project config file       | `.blate.php` auto-loaded by LSP; opt-in via `Blate::autoLoad()`  | `AppServiceProvider` (PHP)   | `ExtensionInterface` (PHP)    |
| Custom helpers / filters  | `Blate::registerHelper()`                                        | Custom directives / Blade X  | `$twig->addFilter/Function()` |
| Inline array construction | `$map()`, `$list()`, `$store()` helpers                          | PHP array literals in `@php` | `{}` object / `[]` array      |
| Pipe filters              | `{expr \| helperName}` (helper-only lookup)                      | No native pipe syntax        | `{{ expr\|filtername }}`      |
| Variable assignment       | `{@set name = expr}`                                             | `@php $name = expr; @endphp` | `{% set name = expr %}`       |
| PHP literals in exprs     | `true`/`false`/`null` (any case)                                 | Yes (full PHP)               | Yes (`true`/`false`/`null`)   |
| Render context in helpers | `Blate::scope()->data` / `Blate::scope()->template` or pass `$$` | No direct mechanism          | No direct mechanism           |

### Editor tooling

| Feature               | Blate                                            | Blade                   | Twig                   |
| --------------------- | ------------------------------------------------ | ----------------------- | ---------------------- |
| Syntax highlighting   | VS Code, Sublime Text, Vim/Neovim                | VS Code (official ext.) | VS Code + many editors |
| Language server (LSP) | **Built-in PHP LSP server**                      | No official LSP         | No official LSP        |
| Parse diagnostics     | Exact line/column squiggles                      | No                      | No                     |
| Shadow warnings       | Unqualified helper and global-var access         | No                      | No                     |
| Unknown ref errors    | `$global.UNKNOWN` and `$noSuchHelper()` flagged  | No                      | No                     |
| Completions           | Blocks, helpers, global vars, in-scope variables | Snippets only           | No                     |
| Hover documentation   | Helpers and global variables with descriptions   | No                      | No                     |
| Variable rename       | In-document rename                               | No                      | No                     |
| Quick fix             | Prepend `$` to shadowed helper calls             | No                      | No                     |

### Summary

|                               | Blate             | Blade                       | Twig                             |
| ----------------------------- | ----------------- | --------------------------- | -------------------------------- |
| Hot render speed              | Fast              | Fast                        | Fast                             |
| Cold compile speed            | **Fastest**       | Medium                      | Slowest                          |
| Memory footprint (iteration)  | **O(1)**          | O(n) for `@foreach`         | **O(1)**                         |
| Auto-escaping                 | Yes               | Yes                         | Yes                              |
| PHP literals in expressions   | Yes               | Yes (full PHP)              | Yes                              |
| Sandbox for untrusted authors | No                | No                          | **Yes**                          |
| Disable blocks/helpers        | **Yes**           | No                          | Partial                          |
| Global variables              | Yes               | Yes (`View::share`)         | Yes (`addGlobal`)                |
| Computed global variables     | **Yes**           | No                          | No                               |
| Project config file           | **`.blate.php`**  | `AppServiceProvider`        | Extension class                  |
| Built-in LSP server           | **Yes**           | No                          | No                               |
| Framework coupling            | None - standalone | Laravel only                | Framework-agnostic               |
| Feature richness              | Focused           | Rich (Livewire, components) | Rich (macros, extensions, tests) |

**Choose Blate when** you want a fast, lightweight, framework-agnostic engine
with a composable security surface, and templates are written by trusted
developers.

**Choose Twig when** templates may be written by untrusted users, or you
need the sandbox and the extension ecosystem.

**Choose Blade when** you are already in Laravel and need native integration
with components, Livewire, etc.
