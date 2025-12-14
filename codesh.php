<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Phiki\Phiki;
use Phiki\Transformers\Decorations\PreDecoration;
use RocketTheme\Toolbox\Event\Event;

class CodeshPlugin extends Plugin
{
    protected ?Phiki $phiki = null;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100001],
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
        ]);
    }

    /**
     * Register shortcode handlers
     */
    public function onShortcodeHandlers(Event $e): void
    {
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/classes/shortcodes');
    }

    /**
     * Add CSS and JS assets
     */
    public function onTwigSiteVariables(): void
    {
        $this->grav['assets']->addCss('plugin://codesh/css/codesh.css');
        $this->grav['assets']->addJs('plugin://codesh/js/codesh.js', ['group' => 'bottom', 'defer' => true]);
    }

    /**
     * Get Phiki instance with custom themes registered
     */
    protected function getPhiki(): Phiki
    {
        if ($this->phiki === null) {
            $this->phiki = new Phiki();

            // Register custom themes from plugin's themes directory
            $themesDir = __DIR__ . '/themes';
            if (is_dir($themesDir)) {
                foreach (glob($themesDir . '/*.json') as $themeFile) {
                    $themeName = basename($themeFile, '.json');
                    $this->phiki->theme($themeName, $themeFile);
                }
            }
        }

        return $this->phiki;
    }

    /**
     * Process markdown code blocks after page content is processed
     */
    public function onPageContentProcessed(Event $e): void
    {
        $config = $this->config->get('plugins.codesh');

        // Check if markdown processing is enabled
        if (!($config['process_markdown'] ?? true)) {
            return;
        }

        $page = $e['page'];
        $content = $page->getRawContent();

        // Skip if no code blocks or already processed by codesh
        if (strpos($content, '<pre><code') === false || strpos($content, 'codesh-block') !== false) {
            return;
        }

        // Process markdown code blocks: <pre><code class="language-xxx">...</code></pre>
        $content = preg_replace_callback(
            '/<pre><code class="language-([^"]+)">(.*?)<\/code><\/pre>/s',
            function ($matches) use ($config) {
                $lang = $matches[1];
                $code = $matches[2];

                // Decode HTML entities back to original code
                $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $this->highlightCode($code, $lang, $config);
            },
            $content
        );

        // Also process code blocks WITHOUT a language class: <pre><code>...</code></pre>
        $content = preg_replace_callback(
            '/<pre><code>(.*?)<\/code><\/pre>/s',
            function ($matches) use ($config) {
                $code = $matches[1];

                // Decode HTML entities back to original code
                $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $this->highlightCode($code, 'txt', $config);
            },
            $content
        );

        $page->setRawContent($content);
    }

    /**
     * Highlight code using Phiki
     */
    protected function highlightCode(string $code, string $lang, array $config): string
    {
        // Detect theme mode from Helios theme config
        $themeConfig = $this->config->get('themes.helios.appearance.theme', 'system');

        // Use custom helios themes by default (with diff backgrounds)
        $themeDark = $config['theme_dark'] ?? 'helios-dark';
        $themeLight = $config['theme_light'] ?? 'helios-light';

        // Determine theme(s) to use
        if ($themeConfig === 'system') {
            // System mode - use dual themes with CSS variable switching
            $theme = [
                'light' => $themeLight,
                'dark' => $themeDark,
            ];
        } else {
            // Explicit light or dark mode - use single theme
            $theme = ($themeConfig === 'dark') ? $themeDark : $themeLight;
        }

        try {
            $phiki = $this->getPhiki();
            $output = $phiki->codeToHtml($code, strtolower($lang), $theme);

            // Add 'no-highlight' class to prevent Prism.js from reprocessing
            $output = $output->decoration(PreDecoration::make()->class('no-highlight'));

            $html = $output->toString();

            // Add dual-theme class when using CSS variable switching
            $dualClass = is_array($theme) ? ' codesh-dual-theme' : '';

            return '<div class="codesh-block no-header' . $dualClass . '" data-language="' . htmlspecialchars($lang) . '">'
                . '<div class="codesh-code">' . $html . '</div>'
                . '</div>';

        } catch (\Exception $e) {
            // Fallback to original on error
            return '<div class="codesh-block codesh-error no-header"><pre><code>' . htmlspecialchars($code) . '</code></pre></div>';
        }
    }
}
