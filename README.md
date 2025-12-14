# Codesh - Code Syntax Highlighter Plugin

Server-side syntax highlighting for Grav CMS using [Phiki](https://phiki.dev), a PHP port of Shiki that uses TextMate grammars and VS Code themes.

## Features

- **Server-side rendering** - No JavaScript required for syntax highlighting
- **70+ themes** - All VS Code themes including GitHub, Dracula, Nord, Monokai, and more
- **200+ languages** - Full TextMate grammar support for accurate highlighting
- **Line numbers** - Optional line number gutter with custom starting line
- **Line highlighting** - Highlight specific lines to draw attention
- **Line focus** - Dim non-focused lines to emphasize important code
- **Markdown compatibility** - Wrap standard fenced code blocks for editor support

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

```markdown
[codesh=php]
<?php
echo "Hello, World!";
[/codesh]
```

### With Language Attribute

```markdown
[codesh lang="javascript"]
const greeting = "Hello, World!";
console.log(greeting);
[/codesh]
```

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

### Custom Theme Per Block

Override the default theme for a specific code block:

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

## Configuration

In `user/config/plugins/codesh.yaml`:

```yaml
enabled: true
active: true
theme: github-dark      # Default theme
show_line_numbers: false # Default line numbers setting
```

## Available Themes (60)

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
| `theme` | Color theme | `theme="dracula"` |
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

- `.codesh-block` - Main container
- `.codesh-block pre` - Pre element
- `.codesh-block .line` - Each line of code
- `.codesh-block .line.highlight` - Highlighted lines
- `.codesh-block .line.focus` - Focused lines
- `.codesh-block.has-focus .line:not(.focus)` - Non-focused lines (dimmed)
- `.codesh-block .gutter` - Line number gutter

## License

MIT License - see [LICENSE](LICENSE) for details.
