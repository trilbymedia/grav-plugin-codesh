# v1.0.0
## 12/14/2024

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
