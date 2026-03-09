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

### Pipe Filters

Apply a helper as a filter with `|`. The left-hand expression becomes the first
argument. Multiple pipes are chained left to right:

```blate
{name | upper}                     -- upper(name)
{body | truncate(120)}             -- truncate(body, 120)
{price | number(2) | escape}       -- escape(number(price, 2))
{tags | join(', ')}                -- join(tags, ', ')
```

Pipe-filter names always resolve against the helpers layer only, so a user-data
key with the same name cannot shadow the helper.

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

```blate
{@each item in products}
  <li>{item.name} - {item.price | number(2)}</li>
{:else}
  <li>No products found.</li>
{/each}

<!-- with key -->
{@each item:key in map}
  {key}: {item}
{/each}

<!-- with key and index -->
{@each item:key:idx in list}
  {idx}. [{key}] {item}
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

Loop a block `n` times. Optionally expose the 0-based index as a variable:

```blate
{@repeat 3}*{/repeat}       -- outputs: ***

{@repeat count as i}
  Row {i}
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

---

## Helpers Reference

Helpers are callable functions registered globally with `Blate::registerHelper`.
They can be called in expressions and as pipe filters.

There are three ways to invoke a helper named `upper`:

```blate
{upper(title)}     -- full stack lookup: user-data key 'upper' shadows the helper
{$upper(title)}    -- helper-only lookup: immune to user-data shadowing
{title | upper}    -- pipe filter: always uses helper-only lookup (same as $upper)
```

The `$` prefix and pipe filters both bypass the variable scope stack and consult
only the registered helpers layer. Use them whenever template data comes from
untrusted sources, to prevent a malicious `upper` key in the data from hijacking
the helper call.

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
| `json`       | `json(value, flags?)`            | `json_encode` with `JSON_HEX_TAG\|JSON_HEX_AMP\|JSON_THROW_ON_ERROR` by default. `<`, `>`, `&` are unicode-escaped so the output is safe to embed in HTML `<script>` blocks.                                                                                                                                             |
| `startsWith` | `startsWith(str, prefix)`        | `true` if `str` starts with `prefix`.                                                                                                                                                                                                                                                                                    |
| `endsWith`   | `endsWith(str, suffix)`          | `true` if `str` ends with `suffix`.                                                                                                                                                                                                                                                                                      |
| `contains`   | `contains(haystack, needle)`     | Substring check for strings; membership check for arrays.                                                                                                                                                                                                                                                                |
| `repeat`     | `repeat(str, times)`             | Repeat a string N times.                                                                                                                                                                                                                                                                                                 |
| `pad`        | `pad(str, length, pad?, side?)`  | Pad to length. `side`: `'right'` (default), `'left'`, `'both'`.                                                                                                                                                                                                                                                          |
| `sprintf`    | `sprintf(format, ...)`           | `sprintf`-style placeholder formatting.                                                                                                                                                                                                                                                                                  |
| `stripTags`  | `stripTags(str, allowed?)`       | Strip HTML/PHP tags; `allowed` keeps specified tags.                                                                                                                                                                                                                                                                     |

---

### Array

| Helper    | Signature                       | Description                                             |
| --------- | ------------------------------- | ------------------------------------------------------- |
| `join`    | `join(array, glue?)`            | `implode` (default glue `''`).                          |
| `keys`    | `keys(array)`                   | `array_keys`.                                           |
| `values`  | `values(array)`                 | `array_values`.                                         |
| `length`  | `length(value)`                 | String length (`mb_strlen`) or array count.             |
| `count`   | `count(array)`                  | Alias for `length`.                                     |
| `first`   | `first(array)`                  | First element, or `null`.                               |
| `last`    | `last(array)`                   | Last element, or `null`.                                |
| `slice`   | `slice(array, offset, length?)` | `array_slice`.                                          |
| `reverse` | `reverse(array\|string)`        | Reverse array or string (multibyte-safe).               |
| `unique`  | `unique(array)`                 | Remove duplicate values.                                |
| `flatten` | `flatten(array)`                | Flatten one level deep.                                 |
| `chunk`   | `chunk(array, size)`            | Split into chunks of `size`.                            |
| `merge`   | `merge(a, b, ...)`              | `array_merge` (variadic).                               |
| `sort`    | `sort(array)`                   | Sort ascending, re-indexed from 0.                      |
| `sortBy`  | `sortBy(array, key)`            | Sort array of objects/maps by field `key`.              |
| `range`   | `range(start, end, step?)`      | Create a range array.                                   |
| `min`     | `min(array)`                    | Minimum value.                                          |
| `max`     | `max(array)`                    | Maximum value.                                          |
| `sum`     | `sum(array)`                    | Sum of all values.                                      |
| `avg`     | `avg(array)`                    | Arithmetic mean (returns `0.0` for empty arrays).       |
| `shuffle` | `shuffle(array)`                | Return a shuffled copy.                                 |
| `filter`  | `filter(array, value?)`         | Remove falsy values; or keep only elements `=== value`. |

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
