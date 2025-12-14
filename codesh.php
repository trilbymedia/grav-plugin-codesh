<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Plugin\Codesh\ThemeManager;
use Phiki\Phiki;
use Phiki\Transformers\Decorations\PreDecoration;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\HttpFoundation\Response;

class CodeshPlugin extends Plugin
{
    protected ?Phiki $phiki = null;
    protected ?ThemeManager $themeManager = null;

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
            $this->enable([
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 1000],  // High priority to run early
                'onTwigLoader' => ['onTwigLoader', 0],  // Add paths directly to Twig loader
                'onTwigSiteVariables' => ['onAdminTwigSiteVariables', 0],
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
                // Priority 0 runs after admin's priority 1000 (higher = earlier in Symfony)
                'onPagesInitialized' => ['onAdminPagesInitialized', 0],
            ]);
            return;
        }

        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
        ]);
    }

    /**
     * Get Theme Manager instance
     */
    public function getThemeManager(): ThemeManager
    {
        if ($this->themeManager === null) {
            $this->themeManager = new ThemeManager($this->grav);
        }
        return $this->themeManager;
    }

    /**
     * Add admin menu item for theme editor
     */
    public function onAdminMenu(): void
    {
        $this->grav['twig']->plugins_hooked_nav['PLUGIN_CODESH.THEMES'] = [
            'route' => 'codesh-themes',
            'icon' => 'fa-palette',
            'authorize' => 'admin.plugins',
            'priority' => 10
        ];
    }

    /**
     * Add admin template paths
     *
     * This adds paths for:
     * 1. Custom form field types (templates/forms/fields/*)
     * 2. Admin page templates (admin/templates/*)
     */
    public function onAdminTwigTemplatePaths(Event $e): void
    {
        if (!$this->isAdmin()) {
            return;
        }

        // Get reference to paths array - Note: Event passes by reference from admin plugin
        // We need to manipulate the array that was passed into the Event
        $pluginTemplates = __DIR__ . '/templates';
        $adminTemplates = __DIR__ . '/admin/templates';

        // Add our template directories
        // Using direct array manipulation ensures the reference is maintained
        $currentPaths = $e['paths'];
        $newPaths = [$pluginTemplates, $adminTemplates];
        foreach (array_reverse($newPaths) as $path) {
            array_unshift($currentPaths, $path);
        }
        $e['paths'] = $currentPaths;
    }

    /**
     * Add paths directly to Twig loader for custom form field templates
     */
    public function onTwigLoader(): void
    {
        if (!$this->isAdmin()) {
            return;
        }

        /** @var \Grav\Common\Twig\Twig $twig */
        $twig = $this->grav['twig'];

        // Prepend paths for form field templates
        $twig->prependPath(__DIR__ . '/templates');
    }

    /**
     * Admin Twig site variables
     */
    public function onAdminTwigSiteVariables(): void
    {
        // Add assets for theme editor page
        if (isset($this->grav['admin']) && $this->grav['admin']->location === 'codesh-themes') {
            $assets = $this->grav['assets'];
            $assets->addCss('plugin://codesh/admin/css/theme-editor.css', ['priority' => 10]);
            $assets->addJs('plugin://codesh/admin/js/theme-editor.js', ['group' => 'bottom', 'priority' => 10]);
        }
    }

    /**
     * Register assets for the codeshtheme custom field type
     */
    public function onAssetsInitialized(): void
    {
        $assets = $this->grav['assets'];
        $assets->addCss('plugin://codesh/admin/css/codeshtheme-field.css', ['priority' => 10]);
        $assets->addJs('plugin://codesh/admin/js/codeshtheme-field.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 120
        ]);
    }

    /**
     * Handle admin routes for theme editor
     */
    public function onAdminPagesInitialized(): void
    {
        // For AJAX requests, check URI path directly and handle early
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $uri = $this->grav['uri'];
            $path = $uri->path();

            if (strpos($path, 'codesh-themes') !== false) {
                // Clean output buffers and handle AJAX
                while (ob_get_level()) {
                    ob_end_clean();
                }
                $this->handleThemeEditorRoutes();
                exit;
            }
        }

        // Check if admin is available for non-AJAX requests
        if (!isset($this->grav['admin'])) {
            return;
        }

        $admin = $this->grav['admin'];

        // Check for theme editor routes using admin's location
        if ($admin->location === 'codesh-themes') {
            $this->handleThemeEditorRoutes();
        }
    }

    /**
     * Route handler for theme editor
     */
    protected function handleThemeEditorRoutes(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);

        // Parse action from URI (works for both AJAX and regular requests)
        $uri = $this->grav['uri'];
        $path = $uri->path();
        $action = 'index';
        $param = null;

        // Extract action from path like /grav-helios/admin/codesh-themes/list
        if (preg_match('/codesh-themes\/([^\/\?]+)(?:\/([^\/\?]+))?/', $path, $matches)) {
            $action = $matches[1] ?? 'index';
            $param = $matches[2] ?? null;
        }

        // Handle AJAX requests (GET, POST, or DELETE)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            if ($method === 'GET' && $action === 'list') {
                $this->handleAjaxList();
                exit;
            }
            if ($method === 'POST') {
                $this->handleAjaxRequest($action);
                exit;
            }
            if ($method === 'DELETE') {
                $this->handleAjaxDelete($param ?? $action);
                exit;
            }
        }

        // Handle export (GET with download)
        if ($action === 'export' && $param) {
            $this->handleExport($param);
            exit;
        }

        // Handle delete (via POST with action parameter)
        if ($method === 'POST' && $action === 'delete') {
            $this->handleDelete();
            exit;
        }
    }

    /**
     * Handle AJAX requests
     */
    protected function handleAjaxRequest(string $action): void
    {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Validate nonce
        $nonce = $_POST['admin-nonce'] ?? $_SERVER['HTTP_X_ADMIN_NONCE'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        $themeManager = $this->getThemeManager();

        switch ($action) {
            case 'preview':
                $this->handlePreview($themeManager);
                break;

            case 'save':
                $this->handleSave($themeManager);
                break;

            case 'copy':
                $this->handleCopy($themeManager);
                break;

            case 'import':
                $this->handleImport($themeManager);
                break;

            case 'list':
                $this->handleList($themeManager);
                break;

            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }

    /**
     * Handle preview generation
     */
    protected function handlePreview(ThemeManager $themeManager): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['colors'])) {
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $colors = $input['colors'];
        $language = $input['language'] ?? 'php';
        $variant = $input['variant'] ?? 'dark';

        // Validate colors
        foreach ($colors as $key => $value) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/i', $value)) {
                echo json_encode(['error' => 'Invalid color format: ' . $key]);
                return;
            }
        }

        // Generate theme from colors
        $theme = $themeManager->generateFromCoreColors($colors, $variant);

        // Generate preview
        $html = $themeManager->generatePreviewFromData($theme, $language);

        echo json_encode(['html' => $html]);
    }

    /**
     * Handle theme save
     */
    protected function handleSave(ThemeManager $themeManager): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['name']) || !isset($input['colors'])) {
            echo json_encode(['error' => 'Invalid request']);
            return;
        }

        $name = $input['name'];
        $colors = $input['colors'];
        $variant = $input['variant'] ?? 'dark';
        $displayName = $input['displayName'] ?? null;

        // Validate name
        if (!preg_match('/^[a-z0-9-_]+$/i', $name)) {
            echo json_encode(['error' => 'Invalid theme name']);
            return;
        }

        try {
            // Generate full theme from colors
            $theme = $themeManager->generateFromCoreColors($colors, $variant);

            // Set name and display name
            $theme['name'] = $name;
            if ($displayName) {
                $theme['displayName'] = $displayName;
            }

            // Save theme
            $success = $themeManager->saveTheme($name, $theme);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Theme saved successfully' : 'Failed to save theme'
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle theme copy
     */
    protected function handleCopy(ThemeManager $themeManager): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $source = $input['source'] ?? '';
        $newName = $input['name'] ?? '';

        if (empty($source) || empty($newName)) {
            echo json_encode(['error' => 'Source and new name are required']);
            return;
        }

        try {
            $success = $themeManager->copyTheme($source, $newName);
            $admin = $this->grav['admin'];

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Theme copied successfully' : 'Failed to copy theme',
                'redirect' => $admin->adminUrl('codesh-themes?theme=' . $newName)
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle theme import
     */
    protected function handleImport(ThemeManager $themeManager): void
    {
        if (!isset($_FILES['theme_file']) || $_FILES['theme_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'No file uploaded or upload error']);
            return;
        }

        $file = $_FILES['theme_file'];

        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            echo json_encode(['error' => 'Only JSON files are allowed']);
            return;
        }

        // Validate file size (max 1MB)
        if ($file['size'] > 1048576) {
            echo json_encode(['error' => 'File too large (max 1MB)']);
            return;
        }

        try {
            $themeName = $themeManager->importTheme($file['tmp_name']);
            $admin = $this->grav['admin'];

            echo json_encode([
                'success' => true,
                'message' => 'Theme imported successfully',
                'theme' => $themeName,
                'redirect' => $admin->adminUrl('codesh-themes?theme=' . $themeName)
            ]);
        } catch (\Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle theme list (POST AJAX)
     */
    protected function handleList(ThemeManager $themeManager): void
    {
        echo json_encode([
            'builtin' => $themeManager->listBuiltinThemes(),
            'custom' => $themeManager->listCustomThemes()
        ]);
    }

    /**
     * Handle AJAX GET request for theme list
     */
    protected function handleAjaxList(): void
    {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Validate nonce from header
        $nonce = $_SERVER['HTTP_X_GRAV_NONCE'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        $themeManager = $this->getThemeManager();

        echo json_encode([
            'builtin' => $themeManager->listBuiltinThemes(),
            'custom' => $themeManager->listCustomThemes()
        ]);
    }

    /**
     * Handle theme export
     */
    protected function handleExport(string $name): void
    {
        $themeManager = $this->getThemeManager();

        try {
            $json = $themeManager->exportTheme($name);

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $name . '.json"');
            header('Content-Length: ' . strlen($json));

            echo $json;
        } catch (\Exception $e) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle theme delete (POST method)
     */
    protected function handleDelete(): void
    {
        header('Content-Type: application/json');

        // Validate nonce
        $nonce = $_POST['admin-nonce'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        $name = $_POST['name'] ?? '';

        if (empty($name)) {
            echo json_encode(['error' => 'Theme name is required']);
            return;
        }

        $themeManager = $this->getThemeManager();

        // Only allow deleting custom themes
        if (!$themeManager->customThemeExists($name)) {
            echo json_encode(['error' => 'Can only delete custom themes']);
            return;
        }

        $success = $themeManager->deleteTheme($name);

        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Theme deleted successfully' : 'Failed to delete theme'
        ]);
    }

    /**
     * Handle AJAX DELETE request for theme deletion
     */
    protected function handleAjaxDelete(string $name): void
    {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Validate nonce from header
        $nonce = $_SERVER['HTTP_X_GRAV_NONCE'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Theme name is required']);
            return;
        }

        $themeManager = $this->getThemeManager();

        // Only allow deleting custom themes
        if (!$themeManager->customThemeExists($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Can only delete custom themes']);
            return;
        }

        $success = $themeManager->deleteTheme($name);

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Theme deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to delete theme'
            ]);
        }
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

            // Register user custom themes from data directory
            $userThemesDir = $this->grav['locator']->findResource('user://data/codesh/themes', true);
            if ($userThemesDir && is_dir($userThemesDir)) {
                foreach (glob($userThemesDir . '/*.json') as $themeFile) {
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
