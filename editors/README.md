# Blate - Editor Support

Syntax highlighting for [Blate](https://github.com/silassare/blate) template files (`.blate`).

## Structure

```
editors/
  vscode/
    package.json                  VS Code extension manifest
    language-configuration.json   bracket matching, comment toggle, indent rules
    syntaxes/
      blate.tmLanguage.json       TextMate grammar (also used by IntelliJ)
  sublime/
    Blate.sublime-syntax          Sublime Text 3/4 native YAML syntax
  vim/
    ftdetect/blate.vim            file-type auto-detection
    syntax/blate.vim              syntax rules
```

---

## VS Code

### Local install (development / personal use)

```sh
cp -r editors/vscode ~/.vscode/extensions/blate
```

Restart VS Code (or `Developer: Reload Window`). `.blate` files are detected
and highlighted automatically.

### Package as .vsix

```sh
npm install -g @vscode/vsce
cd editors/vscode
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

| Token                     | Scope                           |
| ------------------------- | ------------------------------- |
| `{# ... #}`               | `comment.block.blate`           |
| `{~ ... ~}`               | embedded PHP (`source.php`)     |
| `{@if`, `{@each` ...      | `keyword.control.blate`         |
| `{/if}`, `{/each}` ...    | `keyword.control.blate`         |
| `{:else}`, `{:case}` ...  | `keyword.control.blate`         |
| `{= expr}`                | `meta.print.raw.blate`          |
| `'...'` `"..."`           | `string.quoted.*.blate`         |
| Numbers                   | `constant.numeric.blate`        |
| `null` / `true` / `false` | `constant.language.blate`       |
| `$$`                      | `variable.language.blate`       |
| `$helper`                 | `variable.other.constant.blate` |
| `fn(...)`                 | `entity.name.function.blate`    |
| `\|` pipe filter          | `keyword.operator.pipe.blate`   |
| `??` `&&` `\|\|` `==` ... | `keyword.operator.blate`        |
| `.` accessor              | `punctuation.accessor.blate`    |
| `in` / `as`               | `keyword.operator.word.blate`   |
| Bare identifiers          | `variable.other.blate`          |
