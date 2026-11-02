# Changelog

The repository carries no release tag, so this file starts at the 1.x development line. What came
before it is its history.

## Unreleased (1.x-dev)

### Fixed

- **Two templates that import each other compiled cleanly and exhausted the process at render.**
  `{@import}` and `{@extends}` refused only a template naming *itself*, and they do not compile what
  they name: `onOpen()` emits `Blate::fromPath(...)->getParsedInstance()->run(...)` into the compiled
  class, so compiling A never opens B and the chain exists only while the templates run. Two
  ordinary partials importing each other -- no privileges, no unusual filesystem -- grew the heap and
  the stack with a `Blate`, a `DataContext` and a scope per turn until the request died.

  `Blate::pushScope()` now refuses a template already on the render stack, which is the stack that
  already had the right lifetime: pushed before `build()`, popped in a `finally`. The error names the
  chain in the order it opened (`a.blate -> b.blate -> c.blate -> a.blate`), so an author reads which
  templates to look at rather than a stack trace. Rendering the same partial twice side by side is
  untouched: what is refused is a template inside itself.

- **`Blate::getParsedInstance()` could call itself for ever.** Its recovery path -- a compiled file
  that exists but declares no class -- unlinked the file and recompiled, with no bound. When the
  unlink fails (a cache directory this user may not write, with the failure silenced by `@`) and
  `parse()` skips compiling because the file still exists, it recursed, including the file and
  re-reading the source on every turn. It now compiles once more and then reports the file and the
  class it does not declare.

- **Paths are compared on one spelling.** `{@import}` and `{@extends}` compared the resolved path
  against the template's own as strings, and `PathUtils::resolve()` is textual: it leaves a doubled
  separator alone and follows no symlink. `Blate::pathKey()` is now that one spelling, used by both
  features and by the render-time guard above.

### Breaking changes

- **PHP 8.5 is the minimum** (`"php": ">=8.5"`), and `silassare/php-utils` is `^2.0`, since the
  engine stands on what that package declares.

- **Every class constant whose value settles its type is typed** (`public const string`,
  `private const int`, ...), and PHP refuses a subclass that redeclares a typed constant without
  the type. A project extending a Blate class and restating a constant must write the type too.

- **Writing to a context layer that cannot hold a key says so.** `DataContext::set()` writes into
  an array layer or an `ArrayAccess` one, and throws `LogicException` naming the type
  (`get_debug_type()`) for anything else. A plain object there used to reach `$top[$key] = ...` and
  end the request with a fatal `Error`, which no handler could catch and which named nothing.
  `DataContext::get()` reads through `ArrayAccess` the same way.

### Added

- **The 8.5 syntax, written by the shared coding standard.** The floor moved to PHP 8.5 and the
  fixer carried no version migration set, so each file kept the syntax of the year it was written.
  The standard now applies the 8.5 migration: `$x::class` over `get_class($x)`, `0o755` over
  `0755`, an arrow function over a closure that only returns, a heredoc indented with its code. Fifteen files here.

- **Global variables**, registered once and read by every template:
  `Blate::registerGlobalVar(string $name, mixed $value, array $options = [])`, with a description
  for the editor tooling, and reachable as a whole through `Blate::GLOBAL_CONTEXT_REF` (`$global`)
  where a local name shadows one. `Blate::getLoadedConfigs()` says which configuration files were
  read, so a project can see what registered what.

- **The built-ins register on first use**, not on load: the built-in blocks, the helpers and the
  globals are installed when a template is first compiled or rendered, or when the registry is
  first read. `Blate::onFirstUse()` queues a project's own registration into that same point, so a
  process that never renders a template pays for none of it.

- **A language server** (`bin/blate-lsp`, `Lsp\BlateLspServer`): hover, diagnostics for an unknown
  helper and for a local name shadowing a global, project autoloading so a project's own blocks and
  helpers are known, and a watcher on `.blate.php` that restarts the server when the configuration
  changes. `Events\BlateRegistryChangedEvent` is dispatched when the registry changes, which is what
  the server listens to.

### Fixed

- A backslash before a brace (`\{`) is raw text, not the start of a block: template paths and
  literals are written through one `phpStringLiteral()` (`BlockExtends`, `BlockImport`,
  `BlockImportRaw`, `ParserOutputTrait`), where each escaped its own way and one of them escaped
  wrongly.
- A compiled template's destination directory is taken from the template's own path
  (`dirname()`), instead of a path built by another branch of the same method.
- The `pad` helper counts characters, not bytes (`mb_str_pad()`): a word with an accent in it used
  to come out shorter than it reads. The `first` and `last` helpers answer through `array_first()`
  and `array_last()`.
