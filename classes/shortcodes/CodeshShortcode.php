<?php

namespace Grav\Plugin\Shortcodes;

use Phiki\Phiki;
use Phiki\Transformers\Decorations\LineDecoration;
use Phiki\Transformers\Decorations\PreDecoration;
use Thunder\Shortcode\EventHandler\FilterRawEventHandler;
use Thunder\Shortcode\Events;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class CodeshShortcode extends Shortcode
{
    public function init(): void
    {
        $handler = function (ShortcodeInterface $sc) {
            return $this->process($sc);
        };

        // Register with RAW handlers so we process BEFORE markdown
        $this->shortcode->getRawHandlers()->add('codesh', $handler);

        // Also register with regular handlers as fallback
        $this->shortcode->getHandlers()->add('codesh', $handler);

        // Prevent nested shortcode processing inside our content
        $this->shortcode->getEvents()->addListener(
            Events::FILTER_SHORTCODES,
            new FilterRawEventHandler(['codesh'])
        );
    }

    /**
     * Process the shortcode
     */
    protected function process(ShortcodeInterface $sc): string
    {
        return $this->highlight(
            $sc->getContent() ?? '',
            $sc->getParameter('lang', $sc->getBbCode() ?? 'txt'),
            [
                'theme' => $sc->getParameter('theme'),
                'line-numbers' => $sc->getParameter('line-numbers', $sc->getParameter('linenumbers')),
                'start' => $sc->getParameter('start', $sc->getParameter('start-line')),
                'highlight' => $sc->getParameter('highlight', $sc->getParameter('hl', '')),
                'focus' => $sc->getParameter('focus', ''),
                'class' => $sc->getParameter('class', ''),
                'show-lang' => $sc->getParameter('show-lang', $sc->getParameter('showlang')),
                'title' => $sc->getParameter('title', ''),
                'header' => $sc->getParameter('header'),
            ]
        );
    }

    /**
     * Highlight code with Phiki
     *
     * @param string $content The code to highlight
     * @param string $lang The language
     * @param array $options Options (theme, line-numbers, start, highlight, focus, class, show-lang, title, header)
     * @return string The highlighted HTML
     */
    public function highlight(string $content, string $lang = 'txt', array $options = []): string
    {
        $config = $this->config->get('plugins.codesh');

        // Detect theme mode from Helios theme config
        $themeConfig = $this->config->get('themes.helios.appearance.theme', 'system');

        $themeDark = $config['theme_dark'] ?? 'github-dark';
        $themeLight = $config['theme_light'] ?? 'github-light';

        // Get theme - explicit theme parameter overrides mode-based themes
        $explicitTheme = $options['theme'] ?? null;
        if ($explicitTheme) {
            // Explicit theme - use single theme
            $theme = $explicitTheme;
        } elseif ($themeConfig === 'system') {
            // System mode - use dual themes with CSS variable switching
            $theme = [
                'light' => $themeLight,
                'dark' => $themeDark,
            ];
        } else {
            // Explicit light or dark mode - use single theme
            $theme = ($themeConfig === 'dark') ? $themeDark : $themeLight;
        }

        $lineNumbers = $this->toBool($options['line-numbers'] ?? $config['show_line_numbers'] ?? false);
        $startLine = (int) ($options['start'] ?? 1);
        $highlight = $options['highlight'] ?? '';
        $focus = $options['focus'] ?? '';
        $class = $options['class'] ?? '';
        $showLang = $this->toBool($options['show-lang'] ?? true);
        $title = $options['title'] ?? '';
        $showHeader = $this->toBool($options['header'] ?? true);

        // Get code content - handle markdown fenced code blocks
        $content = $this->extractCodeFromMarkdown($content, $lang);

        // Clean up the content
        $content = $this->cleanContent($content);

        if (empty(trim($content))) {
            return '';
        }

        try {
            // Create Phiki instance and highlight
            // Phiki accepts string grammar names and handles aliases internally
            $phiki = new Phiki();
            $output = $phiki->codeToHtml($content, strtolower($lang), $theme);

            // Add 'no-highlight' class to prevent Prism.js from reprocessing
            $output = $output->decoration(
                PreDecoration::make()->class('no-highlight')
            );

            // Add line numbers if enabled
            if ($lineNumbers) {
                $output = $output->withGutter();
                if ($startLine !== 1) {
                    $output = $output->startingLine($startLine);
                }
            }

            // Add line decorations for highlighting
            if (!empty($highlight)) {
                $highlightLines = $this->parseLineSpec($highlight);
                foreach ($highlightLines as $line) {
                    // Lines are zero-indexed in Phiki
                    $output = $output->decoration(
                        LineDecoration::forLine($line - 1)->class('highlight')
                    );
                }
            }

            // Add line decorations for focus
            if (!empty($focus)) {
                $focusLines = $this->parseLineSpec($focus);
                foreach ($focusLines as $line) {
                    $output = $output->decoration(
                        LineDecoration::forLine($line - 1)->class('focus')
                    );
                }
            }

            $html = $output->toString();

            // Wrap in container with optional class
            $classes = ['codesh-block'];
            if (is_array($theme)) {
                $classes[] = 'codesh-dual-theme';
            }
            if (!empty($class)) {
                $classes[] = htmlspecialchars($class);
            }
            if (!empty($highlight)) {
                $classes[] = 'has-highlights';
            }
            if (!empty($focus)) {
                $classes[] = 'has-focus';
            }
            if (!$showHeader) {
                $classes[] = 'no-header';
            }

            // Build the complete HTML output
            $output = '<div class="' . implode(' ', $classes) . '" data-language="' . htmlspecialchars($lang) . '">';

            // Add header with language/title and copy button
            if ($showHeader) {
                $output .= '<div class="codesh-header">';

                // Display title or language
                if (!empty($title)) {
                    $output .= '<span class="codesh-title">' . htmlspecialchars($title) . '</span>';
                } elseif ($showLang && !empty($lang)) {
                    $output .= '<span class="codesh-lang">' . htmlspecialchars(strtoupper($lang)) . '</span>';
                } else {
                    $output .= '<span class="codesh-lang"></span>';
                }

                // Copy button
                $output .= '<button class="codesh-copy" type="button" title="Copy code">';
                $output .= '<svg class="codesh-copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                $output .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                $output .= '</svg>';
                $output .= '<span class="codesh-copy-text">Copy</span>';
                $output .= '</button>';

                $output .= '</div>';
            }

            // Add the code
            $output .= '<div class="codesh-code">' . $html . '</div>';
            $output .= '</div>';

            return $output;

        } catch (\Exception $e) {
            // Fallback to plain text on error
            return '<div class="codesh-block codesh-error" data-error="' . htmlspecialchars($e->getMessage()) . '"><pre><code>' . htmlspecialchars($content) . '</code></pre></div>';
        }
    }

    /**
     * Extract code from markdown fenced code blocks
     */
    protected function extractCodeFromMarkdown(string $content, string &$lang): string
    {
        // Check for markdown fenced code block (``` or ~~~)
        if (preg_match('/^```([^\s\n]*)\s*\n(.*?)\n```$/s', trim($content), $matches)) {
            if (!empty($matches[1])) {
                $lang = $matches[1];
            }
            return $matches[2];
        }

        if (preg_match('/^~~~([^\s\n]*)\s*\n(.*?)\n~~~$/s', trim($content), $matches)) {
            if (!empty($matches[1])) {
                $lang = $matches[1];
            }
            return $matches[2];
        }

        return $content;
    }

    /**
     * Clean up content - remove common indentation, trim, etc.
     */
    protected function cleanContent(string $content): string
    {
        // Decode any HTML entities that may have been encoded
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove leading/trailing blank lines
        $content = trim($content, "\n\r");

        return $content;
    }

    /**
     * Parse line specification like "1,3-5,7" into array of line numbers
     */
    protected function parseLineSpec(string $spec): array
    {
        $lines = [];
        $parts = explode(',', $spec);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (str_contains($part, '-')) {
                // Range like "3-5"
                [$start, $end] = explode('-', $part, 2);
                $start = (int) trim($start);
                $end = (int) trim($end);
                if ($start > 0 && $end >= $start) {
                    for ($i = $start; $i <= $end; $i++) {
                        $lines[] = $i;
                    }
                }
            } else {
                // Single line
                $lineNum = (int) $part;
                if ($lineNum > 0) {
                    $lines[] = $lineNum;
                }
            }
        }

        return array_unique($lines);
    }

    /**
     * Convert various values to boolean
     */
    protected function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

}
