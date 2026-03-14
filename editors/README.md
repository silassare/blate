# Blate - Editor Support

Syntax highlighting and Language Server Protocol (LSP) support for
[Blate](https://github.com/silassare/blate) template files (`.blate`).

## Structure

```
editors/
  lsp/
    server.php                    PHP LSP server entry point
  vscode/
    package.json                  VS Code extension manifest
    tsconfig.json                 TypeScript compiler config
    language-configuration.json   bracket matching, comment toggle, indent rules
    src/
      extension.ts                LanguageClient activation for VS Code
    syntaxes/
      blate.tmLanguage.json       TextMate grammar (also used by IntelliJ)
  sublime/
    Blate.sublime-syntax          Sublime Text 3/4 native YAML syntax
  vim/
    ftdetect/blate.vim            file-type auto-detection
    syntax/blate.vim              syntax rules
```

---

## Language Server (LSP)

The PHP-based language server provides four IDE features for all LSP-capable
editors:

| Feature         | Description                                                                      |
| --------------- | -------------------------------------------------------------------------------- |
| **Diagnostics** | Parse errors appear as red squiggles with exact line/column                      |
| **Completions** | Block names, helper names, and in-scope template variables                       |
| **Hover**       | Docblock for built-in helpers (`escapeHtml`, `json`, `attrs`, ...)               |
| **Rename**      | Renames all occurrences of a variable within the document                        |
| **Quick Fix**   | Unqualified helper calls get a one-click fix to prepend `$` (helper-only lookup) |

### Starting the server

```sh
# Using the wrapper script
php bin/blate-lsp

# Or directly
php editors/lsp/server.php
```

The server reads JSON-RPC 2.0 messages from stdin and writes to stdout.
Compiled diagnostics cache files are written to `sys_get_temp_dir()/blate-lsp-cache/`.

### Neovim (nvim-lspconfig)

```lua
local lspconfig = require('lspconfig')
local configs   = require('lspconfig.configs')

if not configs.blate then
    configs.blate = {
        default_config = {
            cmd          = { 'php', '/path/to/blate/editors/lsp/server.php' },
            filetypes    = { 'blate' },
            root_dir     = lspconfig.util.root_pattern('composer.json', '.git'),
            single_file_support = true,
        },
    }
end

lspconfig.blate.setup {}
```

### Helix

Add to `~/.config/helix/languages.toml`:

```toml
[[language]]
name = "blate"
scope = "text.html.blate"
file-types = ["blate"]
language-servers = ["blate-lsp"]

[language-server.blate-lsp]
command = "php"
args    = ["/path/to/blate/editors/lsp/server.php"]
```

### Emacs (eglot)

```elisp
(add-to-list 'eglot-server-programs
             '(blate-mode . ("php" "/path/to/blate/editors/lsp/server.php")))
```

### Sublime Text (LSP package)

Install the **LSP** package, then add to `LSP.sublime-settings`:

```json
{
	"clients": {
		"blate-lsp": {
			"enabled": true,
			"command": ["php", "/path/to/blate/editors/lsp/server.php"],
			"selector": "text.html.blate"
		}
	}
}
```

---

## VS Code

### Build the extension (required once)

The LanguageClient activation code is written in TypeScript and must be compiled:

```sh
cd editors/vscode
npm install
npm run compile
```

### Local install (development / personal use)

```sh
cp -r editors/vscode ~/.vscode/extensions/blate
```

Restart VS Code (or `Developer: Reload Window`). `.blate` files are detected
and highlighted automatically.

### Configuration

| Setting               | Default | Description                                |
| --------------------- | ------- | ------------------------------------------ |
| `blate.phpExecutable` | `"php"` | PHP binary used to run the language server |

### Package as .vsix

```sh
npm install -g @vscode/vsce
cd editors/vscode
npm install && npm run compile
vsce package            # produces blate-1.0.0.vsix
code --install-extension blate-1.0.0.vsix
```

### Publish to the VS Code Marketplace

1. Create a publisher account at <https://marketplace.visualstudio.com/manage>.
2. Generate a Personal Access Token (PAT) with _Marketplace - Manage_ scope.
3. Run:

```sh
cd editors/vscode
vsce login silassare    # enter PAT when prompted
vsce publish
```

---

## IntelliJ / PhpStorm / WebStorm

The `editors/vscode/` directory is a valid TextMate bundle. JetBrains IDEs accept
it directly via the built-in **TextMate Bundles** support (available since 2023.2):

1. `Settings > Editor > TextMate Bundles > +`
2. Select the `editors/vscode/` directory.
3. Click **OK** and restart the IDE.

`.blate` files are recognised and highlighted with no further configuration.

For LSP support in JetBrains IDEs, install the **LSP Support** plugin and
point it at `php editors/lsp/server.php` for `.blate` files.

---

## Sublime Text 3 / 4

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

Sublime Text picks up the file immediately - no restart required.

**Publish to Package Control:**

1. Fork [wbond/package_control_channel](https://github.com/wbond/package_control_channel).
2. Add an entry under `repository/b.json` pointing to this repository.
3. Open a pull request.

---

## Vim / Neovim

### Manual install

```sh
# Vim
cp editors/vim/syntax/blate.vim   ~/.vim/syntax/
cp editors/vim/ftdetect/blate.vim ~/.vim/ftdetect/

# Neovim
cp editors/vim/syntax/blate.vim   ~/.config/nvim/syntax/
cp editors/vim/ftdetect/blate.vim ~/.config/nvim/ftdetect/
```

### Via a plugin manager (recommended)

**vim-plug:**

```vim
Plug 'silassare/blate', { 'rtp': 'editors/vim' }
```

**lazy.nvim:**

```lua
{
  'silassare/blate',
  -- tell lazy.nvim where the Vim runtime directory is
  init = function()
    vim.opt.runtimepath:append(vim.fn.stdpath('data') .. '/lazy/blate/editors/vim')
  end,
}
```

**packer.nvim:**

```lua
use { 'silassare/blate', rtp = 'editors/vim' }
```

PHP syntax inside `{~ ... ~}` blocks is highlighted automatically when
`$VIMRUNTIME/syntax/php.vim` is present (standard Vim/Neovim distribution).

---

## What is highlighted

| Token                                | Scope                           |
| ------------------------------------ | ------------------------------- |
| `{# ... #}`                          | `comment.block.blate`           |
| `{~ ... ~}`                          | embedded PHP (`source.php`)     |
| `{@if`, `{@each` ...                 | `keyword.control.blate`         |
| `{/if}`, `{/each}` ...               | `keyword.control.blate`         |
| `{:else}`, `{:case}` ...             | `keyword.control.blate`         |
| `{= expr}`                           | `meta.print.raw.blate`          |
| `'...'` `"..."`                      | `string.quoted.*.blate`         |
| Numbers                              | `constant.numeric.blate`        |
| `null` / `true` / `false` (any case) | `constant.language.blate`       |
| `$$`                                 | `variable.language.blate`       |
| `$helper`                            | `variable.other.constant.blate` |
| `fn(...)`                            | `entity.name.function.blate`    |
| `\|` pipe filter                     | `keyword.operator.pipe.blate`   |
| `??` `&&` `\|\|` `==` ...            | `keyword.operator.blate`        |
| `.` accessor                         | `punctuation.accessor.blate`    |
| `in` / `as`                          | `keyword.operator.word.blate`   |
| Bare identifiers                     | `variable.other.blate`          |
