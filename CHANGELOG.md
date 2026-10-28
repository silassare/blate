# Changelog

The repository carries no release tag, so this file starts at the 1.x development line. What came
before it is its history.

## Unreleased (1.x-dev)

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
