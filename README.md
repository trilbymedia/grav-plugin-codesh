# Codesh - Code Syntax Highlighter Plugin

Server-side syntax highlighting for Grav CMS using [Phiki](https://phiki.dev), a PHP port of Shiki that uses TextMate grammars and VS Code themes.

## Features

- **Server-side rendering** - No JavaScript required for syntax highlighting
- **Automatic theme switching** - Detects light/dark mode and switches themes automatically
- **70+ themes** - All VS Code themes including GitHub, Dracula, Nord, Monokai, and more
- **200+ languages** - Full TextMate grammar support for accurate highlighting
- **Markdown processing** - Automatically highlights fenced code blocks in markdown
- **Line numbers** - Optional line number gutter with custom starting line
- **Line highlighting** - Highlight specific lines to draw attention
- **Line focus** - Dim non-focused lines to emphasize important code
- **Title/filename display** - Show filename or custom title in the header
- **Minimal mode** - Hide header for clean, minimal appearance
- **Code groups** - Tabbed interface for multiple code examples with sync support

## Installation

1. Copy the `codesh` folder to `user/plugins/`
2. Run `composer install` in the plugin directory
3. Enable the plugin in Admin or via `user/config/plugins/codesh.yaml`

## Requirements

- PHP 8.1+
- Grav 1.7+
- shortcode-core plugin 5.0+

## Usage

### Basic Syntax

Use BBCode-style parameter for the language:

```markdown
[codesh=php]
<?php
echo "Hello, World!";
[/codesh]
```

Or use the `lang` attribute:

```markdown
[codesh lang="javascript"]
const greeting = "Hello, World!";
console.log(greeting);
[/codesh]
```

### Automatic Markdown Processing

Fenced code blocks in markdown are automatically highlighted:

```js
console.log("Hello, World!");
```

This feature can be disabled via the `process_markdown` config option.

### Wrapping Markdown Code Blocks

For compatibility with markdown editors, you can wrap standard fenced code blocks:

```markdown
[codesh]
```php
<?php
echo "Hello, World!";
```
[/codesh]
```

### Line Numbers

Enable line numbers with the `line-numbers` attribute:

```markdown
[codesh lang="python" line-numbers="true"]
def greet(name):
    return f"Hello, {name}!"

print(greet("World"))
[/codesh]
```

Start from a specific line number:

```markdown
[codesh lang="python" line-numbers="true" start="10"]
def greet(name):
    return f"Hello, {name}!"
[/codesh]
```

### Line Highlighting

Highlight specific lines using `highlight` or `hl`:

```markdown
[codesh lang="javascript" highlight="2,4-6"]
const a = 1;
const b = 2;  // highlighted
const c = 3;
const d = 4;  // highlighted
const e = 5;  // highlighted
const f = 6;  // highlighted
const g = 7;
[/codesh]
```

Syntax: Single lines (`1,3,5`), ranges (`2-4`), or combined (`1,3-5,8`).

### Line Focus

Focus on specific lines (dims non-focused lines):

```markdown
[codesh lang="javascript" focus="3-5"]
function example() {
    // setup code
    const important = true;  // focused
    doSomething(important);  // focused
    return important;        // focused
    // cleanup code
}
[/codesh]
```

### Title / Filename Display

Show a filename or custom title in the header:

```markdown
[codesh lang="php" title="src/Controller/UserController.php"]
<?php
namespace App\Controller;

class UserController extends AbstractController
{
    // ...
}
[/codesh]
```

### Hide Language Badge

Hide the language badge while keeping the header:

```markdown
[codesh lang="bash" show-lang="false"]
npm install
npm run build
[/codesh]
```

### Minimal Mode (No Header)

Hide the entire header for a super minimal look:

```markdown
[codesh lang="javascript" header="false"]
const minimal = true;
[/codesh]
```

### Custom Theme Per Block

Override the automatic theme for a specific code block:

```markdown
[codesh lang="rust" theme="dracula"]
fn main() {
    println!("Hello, Rustaceans!");
}
[/codesh]
```

### Custom CSS Class

Add custom classes for additional styling:

```markdown
[codesh lang="html" class="my-custom-class"]
<div>Hello</div>
[/codesh]
```

## Code Groups

Display multiple code examples in a tabbed interface. Use the `title` attribute on each `[codesh]` block to set the tab label.

### Basic Code Group

```markdown
[codesh-group]
[codesh lang="javascript" title="helloWorld.js"]
console.log("Hello World");
[/codesh]
[codesh lang="python" title="hello_world.py"]
print("Hello World")
[/codesh]
[codesh lang="java" title="HelloWorld.java"]
System.out.println("Hello World");
[/codesh]
[/codesh-group]
```

### Synced Code Groups

Use the `sync` attribute to synchronize tab selection across multiple code groups. When a tab is selected in one group, all groups with the same sync key will switch to the matching tab.

```markdown
[codesh-group sync="lang"]
[codesh lang="javascript" title="JavaScript"]
const name = "World";
[/codesh]
[codesh lang="python" title="Python"]
name = "World"
[/codesh]
[/codesh-group]

Some text in between...

[codesh-group sync="lang"]
[codesh lang="javascript" title="JavaScript"]
console.log(`Hello, ${name}!`);
[/codesh]
[codesh lang="python" title="Python"]
print(f"Hello, {name}!")
[/codesh]
[/codesh-group]
```

Tab selections are also persisted in localStorage, so they survive page reloads.

## Configuration

In `user/config/plugins/codesh.yaml`:

```yaml
enabled: true
active: true
theme_dark: github-dark     # Theme for dark mode
theme_light: github-light   # Theme for light mode
show_line_numbers: false    # Default line numbers setting
process_markdown: true      # Auto-highlight markdown code blocks
```

### Theme Selection

Codesh automatically detects your theme's light/dark mode setting:

- **System mode**: Uses CSS variable switching for instant theme changes
- **Light mode**: Uses the `theme_light` setting
- **Dark mode**: Uses the `theme_dark` setting

When you specify an explicit `theme` attribute on a code block, that theme is used regardless of mode.

## Theme Management

The plugin includes a full-featured theme management system in the Grav Admin panel.

### Theme Picker

The plugin settings page features a custom theme picker for selecting dark and light themes:

- **Visual Preview** - Each theme shows a live syntax-highlighted code preview
- **Search** - Filter themes by name
- **Filters** - Filter by All, Dark, Light, or Custom themes
- **One-Click Selection** - Click any theme card to select it

### Theme Gallery

Access the full theme gallery via **Admin > CodeSh Themes** in the sidebar. This provides a read-only view of all available themes with full-size previews.

### Importing Custom Themes

Import VS Code compatible themes directly from JSON files:

1. Click the **Import** button in the theme picker modal
2. Select a `.json` theme file (VS Code theme format)
3. The theme is automatically:
   - Validated for required structure
   - Normalized (short hex colors like `#fff` are expanded to `#ffffff`)
   - Type-detected (light/dark) if not specified
   - Saved to `user/data/codesh/themes/`

Imported themes appear with a **Custom** badge and can be filtered using the Custom filter.

### Deleting Custom Themes

Custom/imported themes can be deleted:

1. Click the red trash icon on any custom theme card
2. Confirm the deletion in the dialog

Note: Built-in themes cannot be deleted.

### Theme Storage

- **Built-in themes**: `user/plugins/codesh/themes/`
- **Custom themes**: `user/data/codesh/themes/`

## Available Themes (60+)

### Dark Themes
| Theme | Theme | Theme |
|-------|-------|-------|
| `andromeeda` | `aurora-x` | `ayu-dark` |
| `catppuccin-frappe` | `catppuccin-macchiato` | `catppuccin-mocha` |
| `dark-plus` | `dracula` | `dracula-soft` |
| `everforest-dark` | `github-dark` | `github-dark-default` |
| `github-dark-dimmed` | `github-dark-high-contrast` | `gruvbox-dark-hard` |
| `gruvbox-dark-medium` | `gruvbox-dark-soft` | `houston` |
| `kanagawa-dragon` | `kanagawa-wave` | `laserwave` |
| `material-theme` | `material-theme-darker` | `material-theme-ocean` |
| `material-theme-palenight` | `min-dark` | `monokai` |
| `night-owl` | `nord` | `one-dark-pro` |
| `plastic` | `poimandres` | `red` |
| `rose-pine` | `rose-pine-moon` | `slack-dark` |
| `solarized-dark` | `synthwave-84` | `tokyo-night` |
| `vesper` | `vitesse-black` | `vitesse-dark` |

### Light Themes
| Theme | Theme | Theme |
|-------|-------|-------|
| `catppuccin-latte` | `everforest-light` | `github-light` |
| `github-light-default` | `github-light-high-contrast` | `gruvbox-light-hard` |
| `gruvbox-light-medium` | `gruvbox-light-soft` | `kanagawa-lotus` |
| `light-plus` | `material-theme-lighter` | `min-light` |
| `one-light` | `rose-pine-dawn` | `slack-ochin` |
| `snazzy-light` | `solarized-light` | `vitesse-light` |

## Supported Languages (245)

Language aliases are supported (e.g., `js` for `javascript`, `py` for `python`, `sh` for `bash`).

### Programming Languages
`abap`, `actionscript-3`, `ada`, `apex`, `apl`, `applescript`, `ara`, `asm`, `awk`, `ballerina`, `bat`, `berry`, `bsl`, `c`, `cadence`, `cairo`, `clarity`, `clojure`, `cobol`, `coffee`, `common-lisp`, `coq`, `cpp`, `crystal`, `csharp`, `d`, `dart`, `dream-maker`, `elixir`, `elm`, `emacs-lisp`, `erlang`, `fennel`, `fish`, `fortran-fixed-form`, `fortran-free-form`, `fsharp`, `gdscript`, `genie`, `gleam`, `go`, `groovy`, `hack`, `haskell`, `haxe`, `hy`, `imba`, `java`, `javascript`, `jison`, `julia`, `kotlin`, `lean`, `llvm`, `logo`, `lua`, `luau`, `matlab`, `mojo`, `move`, `nextflow`, `nim`, `nix`, `nushell`, `objective-c`, `objective-cpp`, `ocaml`, `pascal`, `perl`, `php`, `pkl`, `plsql`, `polar`, `powershell`, `prolog`, `puppet`, `purescript`, `python`, `qml`, `r`, `racket`, `raku`, `razor`, `rel`, `riscv`, `ruby`, `rust`, `sas`, `scala`, `scheme`, `sdbl`, `shellscript`, `smalltalk`, `solidity`, `splunk`, `sql`, `stata`, `swift`, `system-verilog`, `systemd`, `talonscript`, `tcl`, `terraform`, `typescript`, `typst`, `v`, `vala`, `vb`, `verilog`, `vhdl`, `viml`, `vyper`, `wasm`, `wenyan`, `wgsl`, `wolfram`, `zenscript`, `zig`

### Web & Markup
`angular-expression`, `angular-html`, `angular-inline-style`, `angular-inline-template`, `angular-let-declaration`, `angular-template-blocks`, `angular-template`, `angular-ts`, `antlers`, `astro`, `blade`, `css`, `edge`, `erb`, `es-tag-css`, `es-tag-glsl`, `es-tag-html`, `es-tag-sql`, `es-tag-xml`, `glimmer-js`, `glimmer-ts`, `graphql`, `haml`, `handlebars`, `html`, `html-derivative`, `http`, `hurl`, `jinja`, `jinja-html`, `jsx`, `less`, `liquid`, `marko`, `mdc`, `mdx`, `postcss`, `pug`, `sass`, `scss`, `stylus`, `svelte`, `templ`, `ts-tags`, `tsx`, `twig`, `vue`, `vue-directives`, `vue-html`, `vue-interpolations`, `vue-sfc-style-variable-injection`, `vue-vine`, `wikitext`, `xml`, `xsl`

### Data & Config
`beancount`, `bibtex`, `bicep`, `cmake`, `codeowners`, `codeql`, `csv`, `cue`, `cypher`, `dax`, `desktop`, `diff`, `docker`, `dotenv`, `fluent`, `gdresource`, `gdshader`, `gherkin`, `git-commit`, `git-rebase`, `gnuplot`, `hcl`, `hjson`, `hxml`, `ini`, `json`, `json5`, `jsonc`, `jsonl`, `jsonnet`, `jssm`, `kdl`, `kusto`, `log`, `make`, `narrat`, `nginx`, `po`, `powerquery`, `prisma`, `proto`, `qmldir`, `qss`, `reg`, `regexp`, `rosmsg`, `rst`, `sparql`, `ssh-config`, `tasl`, `toml`, `tsv`, `turtle`, `typespec`, `wit`, `yaml`

### Documentation
`apache`, `asciidoc`, `latex`, `maml`, `markdown`, `markdown-vue`, `tex`, `txt`

### Shaders & Graphics
`glsl`, `hlsl`, `shaderlab`

## Shortcode Attributes Reference

| Attribute | Description | Example |
|-----------|-------------|---------|
| `lang` | Programming language | `lang="php"` |
| `theme` | Override color theme | `theme="dracula"` |
| `line-numbers` | Show line numbers | `line-numbers="true"` |
| `start` | Starting line number | `start="10"` |
| `highlight` / `hl` | Lines to highlight | `highlight="1,3-5"` |
| `focus` | Lines to focus | `focus="2-4"` |
| `title` | Filename or title in header | `title="config.yaml"` |
| `show-lang` | Show/hide language badge | `show-lang="false"` |
| `header` | Show/hide entire header bar | `header="false"` |
| `class` | Additional CSS class | `class="my-class"` |

## CSS Customization

The plugin provides CSS classes for styling:

### Container Classes
- `.codesh-block` - Main container
- `.codesh-block.no-header` - Container without header
- `.codesh-block.has-highlights` - Container with highlighted lines
- `.codesh-block.has-focus` - Container with focused lines
- `.codesh-dual-theme` - Container using CSS variable theme switching

### Header Classes
- `.codesh-header` - Header bar
- `.codesh-lang` - Language badge
- `.codesh-title` - Title/filename display
- `.codesh-copy` - Copy button
- `.codesh-copy.copied` - Copy button after successful copy

### Code Classes
- `.codesh-code` - Code wrapper
- `.codesh-block pre` - Pre element
- `.codesh-block .line` - Each line of code
- `.codesh-block .line.highlight` - Highlighted lines
- `.codesh-block .line.focus` - Focused lines
- `.codesh-block .line-number` - Line number elements

### Dark Mode

The plugin uses Tailwind-style dark mode with `.dark` ancestor:

```css
/* Light mode (default) */
.codesh-block { ... }

/* Dark mode */
.dark .codesh-block { ... }
```

## License

MIT License - see [LICENSE](LICENSE) for details.
