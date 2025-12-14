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

## Available Themes

Some popular themes include:

- `github-dark`, `github-light`
- `dracula`, `dracula-soft`
- `nord`
- `monokai`
- `one-dark-pro`, `one-light`
- `material-theme-*` variants
- `catppuccin-*` variants
- `solarized-dark`, `solarized-light`
- `tokyo-night`
- `rose-pine`, `rose-pine-dawn`, `rose-pine-moon`
- And 50+ more!

## Supported Languages

Common languages include: php, javascript, typescript, python, ruby, java, csharp, cpp, go, rust, swift, kotlin, html, css, scss, json, yaml, xml, sql, bash, and 200+ more.

Language aliases are supported (e.g., `js` for `javascript`, `py` for `python`, `sh` for `bash`).

## Shortcode Attributes Reference

| Attribute | Description | Example |
|-----------|-------------|---------|
| `lang` | Programming language | `lang="php"` |
| `theme` | Color theme | `theme="dracula"` |
| `line-numbers` | Show line numbers | `line-numbers="true"` |
| `start` | Starting line number | `start="10"` |
| `highlight` / `hl` | Lines to highlight | `highlight="1,3-5"` |
| `focus` | Lines to focus | `focus="2-4"` |
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
