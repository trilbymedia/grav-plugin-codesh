# v2.0.1
## 12/29/2025

1. [](#bugfix)
    * Fix a PSR-Cache compatibility issue

# v2.0.0
## 12/24/2025

1. [](#new)
    * Production ready!

# v1.3.0
## 12/23/2025

1. [](#new)
    * Added new `line_wrap` option (enabled by default)
1. [](#improved)
    * Dedent code when the whole shortcode is indented (ie in a markdown list)
   
# v1.2.0
## 12/23/2025

1. [](#improved)
    * Support regular `codesh` styling and features while inside a `codesh-group` shortcode

# v1.1.0
## 12/23/2025

1. [](#new)
    * Editor Pro shortcode support
    * New fixed CSS grammar while VSCode PR is pending
1. [](#bugfix)
    * Fixed an annoying issue with 1Password Browser extension injecting Prims.js to convert server side styling into a monochrome mess - uses `PrismDefenseTransformer`

# v1.0.3
## 12/17/2025

1. [](#bugfix)
    * Fixed an issue with codesh filter not instantiated early enough

# v1.0.2
## 12/16/2025

1. [](#new)
    * Added new `grav` grammar for frontmatter + shortcode + twig support in markdown
1. [](#improved)
    * Override `md` and `markdown` to use new `grav` grammar
    * Improve traditional markdown (triple backticks) support in Codesh

# v1.0.1
## 12/14/2025

1. [](#improved)
   * Fixed blueprints
   * Fixed copy button on code groups
   * Fixed empty badge when `show-lang` is disabled 

# v1.0.0
## 12/14/2025

1. [](#new)
    * Initial release of CodeSh plugin
    * Server-side syntax highlighting using [Phiki](https://phiki.dev) (PHP port of Shiki)
    * Support for 245+ languages via TextMate grammars
    * Support for 60+ VS Code themes out of the box
    * Automatic light/dark theme switching based on site theme mode
    * CSS variable-based dual-theme support for system preference detection
    * Custom `[codesh]` shortcode for inline code blocks
    * Automatic markdown fenced code block processing (configurable)
    * Line numbers with optional custom start line
    * Line highlighting with `highlight` or `hl` attribute
    * Line focus to dim non-focused lines with `focus` attribute
    * Title/filename display in header with `title` attribute
    * Language badge with `show-lang` attribute
    * Minimal mode to hide header with `header="false"`
    * Copy button with visual feedback
    * Per-block theme override with `theme` attribute
    * `[codesh-group]` shortcode for tabbed code examples
    * Tab synchronization across groups with `sync` attribute
    * LocalStorage persistence for tab preferences
    * Twig filter `codesh` for template-based highlighting with caching
    * Admin theme gallery at Admin > CodeSh Themes
    * Theme picker field with modal selector and visual previews
    * Theme import for VS Code compatible JSON themes
    * Theme deletion with confirmation dialog
    * Custom `helios-dark` and `helios-light` themes
