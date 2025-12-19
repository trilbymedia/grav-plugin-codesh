<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Utils;
use Grav\Plugin\Codesh\GrammarManager;
use Grav\Plugin\Codesh\ThemeManager;
use Phiki\Phiki;
use Phiki\Transformers\Decorations\PreDecoration;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\HttpFoundation\Response;

class CodeshPlugin extends Plugin
{
    protected ?Phiki $phiki = null;
    protected ?ThemeManager $themeManager = null;
    protected ?GrammarManager $grammarManager = null;

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100001],
                ['onPluginsInitialized', 0]
            ],
            // Editor Pro integration events
            'registerEditorProPlugin' => [
                ['registerEditorProPlugin', 0],
            ],
            'onEditorProShortcodeRegister' => [
                ['onEditorProShortcodeRegister', 0],
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
                'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 1000],  // High priority to run early
                'onTwigLoader' => ['onTwigLoader', 0],  // Add paths directly to Twig loader
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
                // High priority to intercept AJAX requests before admin routing
                'onPagesInitialized' => ['onAdminPagesInitialized', 100000],
            ]);
            return;
        }

        $this->enable([
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onOutputGenerated' => ['onOutputGenerated', 0],
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
     * Get Grammar Manager instance
     */
    public function getGrammarManager(): GrammarManager
    {
        if ($this->grammarManager === null) {
            $this->grammarManager = new GrammarManager($this->grav);
        }
        return $this->grammarManager;
    }


    /**
     * Add admin template paths for custom form field types
     */
    public function onAdminTwigTemplatePaths(Event $e): void
    {
        if (!$this->isAdmin()) {
            return;
        }

        $currentPaths = $e['paths'];
        array_unshift($currentPaths, __DIR__ . '/templates');
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
     * Register assets for custom field types (theme picker, grammar list)
     */
    public function onAssetsInitialized(): void
    {
        $assets = $this->grav['assets'];

        // Theme picker field
        $assets->addCss('plugin://codesh/admin/css/codeshtheme-field.css', ['priority' => 10]);
        $assets->addJs('plugin://codesh/admin/js/codeshtheme-field.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 120
        ]);

        // Grammar list field
        $assets->addCss('plugin://codesh/admin/css/codeshgrammarlist-field.css', ['priority' => 10]);
        $assets->addJs('plugin://codesh/admin/js/codeshgrammarlist-field.js', [
            'group' => 'bottom',
            'loading' => 'defer',
            'priority' => 120
        ]);
    }

    /**
     * Handle admin routes for theme editor and grammar API
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

                // Check if this is a grammar request (under codesh-themes/grammars/*)
                if (strpos($path, 'codesh-themes/grammars') !== false) {
                    $this->handleGrammarApiRoutes();
                } else {
                    $this->handleThemeEditorRoutes();
                }
                exit;
            }
        }
    }

    /**
     * Handle theme API routes (AJAX only)
     */
    protected function handleThemeEditorRoutes(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Parse action from URI
        $uri = $this->grav['uri'];
        $path = $uri->path();
        $action = 'list';
        $param = null;

        // Extract action from path like /grav-helios/admin/codesh-themes/list
        if (preg_match('/codesh-themes\/([^\/\?]+)(?:\/([^\/\?]+))?/', $path, $matches)) {
            $action = $matches[1] ?? 'list';
            $param = $matches[2] ?? null;
        }

        if ($method === 'GET' && $action === 'list') {
            $this->handleAjaxList();
        } elseif ($method === 'POST') {
            $this->handleAjaxRequest($action);
        } elseif ($method === 'DELETE') {
            $this->handleAjaxDelete($param ?? $action);
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

        // Note: No nonce validation for read-only list endpoint
        $themeManager = $this->getThemeManager();

        echo json_encode([
            'builtin' => $themeManager->listBuiltinThemes(),
            'custom' => $themeManager->listCustomThemes()
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
     * Handle grammar API routes (AJAX only)
     */
    protected function handleGrammarApiRoutes(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Parse action from URI
        $uri = $this->grav['uri'];
        $path = $uri->path();
        $action = 'list';
        $param = null;

        // Extract action from path like /grav-helios/admin/codesh-themes/grammars/list
        if (preg_match('/codesh-themes\/grammars\/([^\/\?]+)(?:\/([^\/\?]+))?/', $path, $matches)) {
            $action = $matches[1] ?? 'list';
            $param = $matches[2] ?? null;
        }

        // Handle AJAX requests (GET, POST, or DELETE)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            if ($method === 'GET' && $action === 'list') {
                $this->handleGrammarList();
                exit;
            }
            if ($method === 'POST' && $action === 'import') {
                $this->handleGrammarImport();
                exit;
            }
            if ($method === 'DELETE') {
                $this->handleGrammarDelete($param ?? $action);
                exit;
            }
        }
    }

    /**
     * Handle AJAX GET request for grammar list
     */
    protected function handleGrammarList(): void
    {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Note: No nonce validation for read-only list endpoint
        $grammarManager = $this->getGrammarManager();

        echo json_encode([
            'custom' => $grammarManager->listCustomGrammars(),
            'builtin' => $grammarManager->listBuiltinGrammars()
        ]);
    }

    /**
     * Handle AJAX GET request for single grammar
     */
    protected function handleGrammarGet(string $slug): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $grammarManager = $this->getGrammarManager();
        $grammar = $grammarManager->getGrammar($slug);

        if ($grammar) {
            echo json_encode(['success' => true, 'grammar' => $grammar]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Grammar not found']);
        }
    }

    /**
     * Handle AJAX POST request for grammar import
     */
    protected function handleGrammarImport(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Validate nonce
        $nonce = $_SERVER['HTTP_X_GRAV_NONCE'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        if (!isset($_FILES['grammar_file']) || $_FILES['grammar_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded or upload error']);
            return;
        }

        $file = $_FILES['grammar_file'];

        // Validate file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'json') {
            http_response_code(400);
            echo json_encode(['error' => 'Only JSON files are allowed']);
            return;
        }

        // Validate file size (max 1MB)
        if ($file['size'] > 1048576) {
            http_response_code(400);
            echo json_encode(['error' => 'File too large (max 1MB)']);
            return;
        }

        try {
            $grammarManager = $this->getGrammarManager();
            $slug = $grammarManager->importGrammar($file);

            echo json_encode([
                'success' => true,
                'message' => 'Grammar imported successfully',
                'slug' => $slug
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle AJAX DELETE request for grammar deletion
     */
    protected function handleGrammarDelete(string $slug): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Validate nonce
        $nonce = $_SERVER['HTTP_X_GRAV_NONCE'] ?? '';
        if (!Utils::verifyNonce($nonce, 'admin-form')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            return;
        }

        if (empty($slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Grammar slug is required']);
            return;
        }

        $grammarManager = $this->getGrammarManager();

        // Only allow deleting custom grammars
        if (!$grammarManager->customGrammarExists($slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Can only delete custom grammars']);
            return;
        }

        $success = $grammarManager->deleteGrammar($slug);

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => 'Grammar deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to delete grammar'
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
     * Register Editor Pro plugin assets
     */
    public function registerEditorProPlugin(Event $event): Event
    {
        $plugins = $event['plugins'];

        // Add Codesh Editor Pro integration CSS
        $plugins['css'][] = 'plugin://codesh/editor-pro/codesh-integration.css';

        $event['plugins'] = $plugins;
        return $event;
    }

    /**
     * Register Codesh shortcodes for Editor Pro
     */
    public function onEditorProShortcodeRegister(Event $event): Event
    {
        $shortcodes = $event['shortcodes'];

        // Codesh shortcodes
        $codeshShortcodes = [
            // Codesh - syntax highlighted code block
            [
                'name' => 'codesh',
                'title' => 'Code Block',
                'description' => 'Server-side syntax highlighted code block with 200+ languages',
                'type' => 'block',
                'plugin' => 'codesh',
                'category' => 'code',
                'group' => 'Codesh',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
                'attributes' => [
                    'lang' => [
                        'type' => 'text',
                        'title' => 'Language',
                        'default' => 'javascript',
                        'required' => true,
                        'placeholder' => 'e.g., javascript, php, python'
                    ],
                    'title' => [
                        'type' => 'text',
                        'title' => 'Title/Filename',
                        'default' => '',
                        'placeholder' => 'e.g., example.js'
                    ],
                    'line-numbers' => [
                        'type' => 'checkbox',
                        'title' => 'Show Line Numbers',
                        'default' => false
                    ],
                    'start' => [
                        'type' => 'number',
                        'title' => 'Starting Line',
                        'default' => 1,
                        'min' => 1
                    ],
                    'highlight' => [
                        'type' => 'text',
                        'title' => 'Highlight Lines',
                        'default' => '',
                        'placeholder' => 'e.g., 1,3-5,8'
                    ],
                    'focus' => [
                        'type' => 'text',
                        'title' => 'Focus Lines',
                        'default' => '',
                        'placeholder' => 'e.g., 2-4'
                    ],
                    'theme' => [
                        'type' => 'text',
                        'title' => 'Theme Override',
                        'default' => '',
                        'placeholder' => 'e.g., dracula, nord'
                    ],
                    'header' => [
                        'type' => 'checkbox',
                        'title' => 'Show Header',
                        'default' => true
                    ]
                ],
                'titleBarAttributes' => ['lang', 'title'],
                'hasContent' => true,
                'contentType' => 'code', // Treat content as raw code (uses CodeMirror editor)
                'language' => 'javascript', // Default language for CodeMirror
                'defaultContent' => 'console.log("Hello, World!");'
            ],
            // Codesh Group - tabbed code blocks
            [
                'name' => 'codesh-group',
                'title' => 'Code Group',
                'description' => 'Tabbed interface for multiple code examples with optional sync',
                'type' => 'block',
                'plugin' => 'codesh',
                'category' => 'code',
                'group' => 'Codesh',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>',
                'attributes' => [
                    'sync' => [
                        'type' => 'text',
                        'title' => 'Sync Key',
                        'default' => '',
                        'placeholder' => 'e.g., lang (syncs tabs across groups)'
                    ]
                ],
                'titleBarAttributes' => ['sync'],
                'hasContent' => true,
                'allowedChildren' => ['codesh'], // Nested codesh blocks allowed
                'defaultContent' => '[codesh lang="javascript" title="JavaScript"]\nconsole.log("Hello!");\n[/codesh]\n[codesh lang="python" title="Python"]\nprint("Hello!")\n[/codesh]'
            ]
        ];

        // Add all Codesh shortcodes to the registry
        foreach ($codeshShortcodes as $shortcode) {
            $shortcodes[] = $shortcode;
        }

        $event['shortcodes'] = $shortcodes;
        return $event;
    }

    /**
     * Add Twig filter (must be done before Twig extensions are frozen)
     */
    public function onTwigInitialized(): void
    {
        $twig = $this->grav['twig']->twig();
        $twig->addFilter(new \Twig\TwigFilter('codesh', [$this, 'codeshFilter'], ['is_safe' => ['html']]));
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
     * Twig filter to highlight code using codesh
     *
     * Usage in templates:
     *   {{ code_string|codesh('json') }}
     *   {{ code_string|codesh('php', {title: 'example.php', 'line-numbers': true}) }}
     *
     * @param string $content The code to highlight
     * @param string $lang The language (default: 'txt')
     * @param array $options Options: theme, line-numbers, start, highlight, focus, class, show-lang, title, header
     * @return string The highlighted HTML
     */
    public function codeshFilter(string $content, string $lang = 'txt', array $options = []): string
    {
        $mergedOptions = array_merge([
            'theme' => $options['theme'] ?? null,
            'line-numbers' => $options['line-numbers'] ?? false,
            'start' => $options['start'] ?? 1,
            'highlight' => $options['highlight'] ?? '',
            'focus' => $options['focus'] ?? '',
            'class' => $options['class'] ?? '',
            'show-lang' => $options['show-lang'] ?? true,
            'title' => $options['title'] ?? '',
            'header' => $options['header'] ?? true,
        ], $options);

        // Generate cache key based on content, language, options, and theme
        $themeConfig = $this->config->get('themes.helios.appearance.theme', 'system');
        $cacheKey = 'codesh_' . md5($content . $lang . serialize($mergedOptions) . $themeConfig);

        // Try to get from cache
        $cache = $this->grav['cache'];
        $cached = $cache->fetch($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        // Generate highlighted code
        $result = $this->highlightCodeFull($content, $lang, $mergedOptions);

        // Cache the result (1 hour TTL)
        $cache->save($cacheKey, $result, 3600);

        return $result;
    }

    /**
     * Highlight code using Phiki (shared implementation for filter and page processing)
     */
    protected function highlightCodeFull(string $code, string $lang, array $options): string
    {
        $config = $this->config->get('plugins.codesh');

        // Detect theme mode from Helios theme config
        $themeConfig = $this->config->get('themes.helios.appearance.theme', 'system');

        // Use custom helios themes by default (with diff backgrounds)
        $themeDark = $config['theme_dark'] ?? 'helios-dark';
        $themeLight = $config['theme_light'] ?? 'helios-light';

        // Get theme - explicit theme parameter overrides mode-based themes
        $explicitTheme = $options['theme'] ?? null;
        if ($explicitTheme) {
            $theme = $explicitTheme;
        } elseif ($themeConfig === 'system') {
            $theme = [
                'light' => $themeLight,
                'dark' => $themeDark,
            ];
        } else {
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

        // Clean up the content
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code = trim($code, "\n\r");

        if (empty(trim($code))) {
            return '';
        }

        try {
            $phiki = $this->getPhiki();
            $output = $phiki->codeToHtml($code, strtolower($lang), $theme);

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
                    $output = $output->decoration(
                        \Phiki\Transformers\Decorations\LineDecoration::forLine($line - 1)->class('highlight')
                    );
                }
            }

            // Add line decorations for focus
            if (!empty($focus)) {
                $focusLines = $this->parseLineSpec($focus);
                foreach ($focusLines as $line) {
                    $output = $output->decoration(
                        \Phiki\Transformers\Decorations\LineDecoration::forLine($line - 1)->class('focus')
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
            $result = '<div class="' . implode(' ', $classes) . '" data-language="' . htmlspecialchars($lang) . '">';

            // Add header with language/title and copy button
            if ($showHeader) {
                $result .= '<div class="codesh-header">';

                // Display title or language
                if (!empty($title)) {
                    $result .= '<span class="codesh-title">' . htmlspecialchars($title) . '</span>';
                } elseif ($showLang && !empty($lang)) {
                    $result .= '<span class="codesh-lang">' . htmlspecialchars(strtoupper($lang)) . '</span>';
                } else {
                    $result .= '<span class="codesh-lang"></span>';
                }

                // Copy button
                $result .= '<button class="codesh-copy" type="button" title="Copy code">';
                $result .= '<svg class="codesh-copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
                $result .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
                $result .= '</svg>';
                $result .= '<span class="codesh-copy-text">Copy</span>';
                $result .= '</button>';

                $result .= '</div>';
            }

            // Add the code
            $result .= '<div class="codesh-code">' . $html . '</div>';
            $result .= '</div>';

            return $result;

        } catch (\Exception $e) {
            // Fallback to plain text on error
            return '<div class="codesh-block codesh-error" data-error="' . htmlspecialchars($e->getMessage()) . '"><pre><code>' . htmlspecialchars($code) . '</code></pre></div>';
        }
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
                [$start, $end] = explode('-', $part, 2);
                $start = (int) trim($start);
                $end = (int) trim($end);
                if ($start > 0 && $end >= $start) {
                    for ($i = $start; $i <= $end; $i++) {
                        $lines[] = $i;
                    }
                }
            } else {
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

    /**
     * Get Phiki instance with custom themes and grammars registered
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

            // Register custom grammars from plugin's grammars directory
            $grammarsDir = __DIR__ . '/grammars';
            $this->grav['log']->debug('CodeSh (main): Looking for grammars in ' . $grammarsDir . ' (exists: ' . (is_dir($grammarsDir) ? 'yes' : 'no') . ')');
            if (is_dir($grammarsDir)) {
                $files = glob($grammarsDir . '/*.json');
                $this->grav['log']->debug('CodeSh (main): Found ' . count($files) . ' grammar files');
                foreach ($files as $grammarFile) {
                    $this->registerGrammarWithAliases($grammarFile);
                }
            } else {
                $this->grav['log']->warning('CodeSh (main): Grammars directory not found: ' . $grammarsDir);
            }

            // Register user custom grammars from data directory
            $userGrammarsDir = $this->grav['locator']->findResource('user://data/codesh/grammars', true);
            if ($userGrammarsDir && is_dir($userGrammarsDir)) {
                foreach (glob($userGrammarsDir . '/*.json') as $grammarFile) {
                    $this->registerGrammarWithAliases($grammarFile);
                }
            }
        }

        return $this->phiki;
    }

    /**
     * Register a grammar file with Phiki, including aliases from fileTypes
     */
    protected function registerGrammarWithAliases(string $grammarFile): void
    {
        $grammarSlug = basename($grammarFile, '.json');
        $this->grav['log']->debug('CodeSh: Registering grammar "' . $grammarSlug . '" from ' . $grammarFile);
        $this->phiki->grammar($grammarSlug, $grammarFile);

        // Read grammar data for aliases and overrides
        $content = file_get_contents($grammarFile);
        $data = json_decode($content, true);

        if (!$data) {
            return;
        }

        // Check for explicit overrides - these will replace built-in grammars
        // Can be specified as "overrides": ["markdown", "md"] in the grammar JSON
        if (isset($data['overrides']) && is_array($data['overrides'])) {
            foreach ($data['overrides'] as $override) {
                $this->grav['log']->debug('CodeSh: Overriding grammar "' . $override . '" with ' . $grammarSlug);
                $this->phiki->grammar($override, $grammarFile);
            }
        }

        // Also register aliases from fileTypes array
        if (isset($data['fileTypes']) && is_array($data['fileTypes'])) {
            // Protected extensions that won't be auto-aliased (use "overrides" to explicitly override these)
            $protected = ['md', 'markdown', 'txt', 'json', 'html', 'css', 'js'];

            foreach ($data['fileTypes'] as $alias) {
                // Skip if alias is same as slug or is protected
                if ($alias !== $grammarSlug && !in_array($alias, $protected)) {
                    $this->grav['log']->debug('CodeSh: Registering grammar alias "' . $alias . '" for ' . $grammarSlug);
                    $this->phiki->grammar($alias, $grammarFile);
                }
            }
        }
    }

    /**
     * Process markdown code blocks in the final HTML output
     */
    public function onOutputGenerated(): void
    {
        $config = $this->config->get('plugins.codesh');

        // Check if markdown processing is enabled
        if (!($config['process_markdown'] ?? true)) {
            $this->grav['log']->debug('CodeSh: process_markdown is disabled, skipping');
            return;
        }

        // Get the final HTML output
        $output = $this->grav->output;

        if (empty($output)) {
            $this->grav['log']->debug('CodeSh: Output is empty, skipping');
            return;
        }

        // Count unprocessed code blocks
        $hasUnprocessedBlocks = preg_match_all('/<pre><code[^>]*>/', $output, $matches);
        $this->grav['log']->debug('CodeSh: Found ' . $hasUnprocessedBlocks . ' potential code blocks in output');

        // Skip if no code blocks
        if ($hasUnprocessedBlocks === 0) {
            return;
        }

        $replacementCount = 0;

        // Process markdown code blocks: <pre><code class="language-xxx">...</code></pre>
        // Skip blocks that are already inside codesh-block (from shortcode processing)
        $output = preg_replace_callback(
            '/<pre><code class="language-([^"]+)">(.*?)<\/code><\/pre>/s',
            function ($matches) use ($config, &$replacementCount) {
                $fullMatch = $matches[0];
                $lang = $matches[1];
                $code = $matches[2];

                $this->grav['log']->debug('CodeSh: Processing code block with lang="' . $lang . '"');

                // Decode HTML entities back to original code
                $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $replacementCount++;
                return $this->highlightCode($code, $lang, $config);
            },
            $output
        );

        // Also process code blocks WITHOUT a language class: <pre><code>...</code></pre>
        $output = preg_replace_callback(
            '/<pre><code>(.*?)<\/code><\/pre>/s',
            function ($matches) use ($config, &$replacementCount) {
                $code = $matches[1];

                $this->grav['log']->debug('CodeSh: Processing code block without lang');

                // Decode HTML entities back to original code
                $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $replacementCount++;
                return $this->highlightCode($code, 'txt', $config);
            },
            $output
        );

        $this->grav['log']->debug('CodeSh: Replaced ' . $replacementCount . ' code blocks');

        // Update the output
        $this->grav->output = $output;
    }

    /**
     * Highlight code using Phiki
     */
    protected function highlightCode(string $code, string $lang, array $config): string
    {
        // Debug: Log the lang being requested
        $this->grav['log']->debug('CodeSh (main): highlightCode() called with lang="' . $lang . '", content_len=' . strlen($code));

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

            // Build classes
            $classes = ['codesh-block'];
            if (is_array($theme)) {
                $classes[] = 'codesh-dual-theme';
            }

            // Build the complete HTML output with header
            $result = '<div class="' . implode(' ', $classes) . '" data-language="' . htmlspecialchars($lang) . '">';

            // Add header with language and copy button
            $result .= '<div class="codesh-header">';
            if (!empty($lang)) {
                $result .= '<span class="codesh-lang">' . htmlspecialchars(strtoupper($lang)) . '</span>';
            } else {
                $result .= '<span class="codesh-lang"></span>';
            }

            // Copy button
            $result .= '<button class="codesh-copy" type="button" title="Copy code">';
            $result .= '<svg class="codesh-copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
            $result .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
            $result .= '</svg>';
            $result .= '<span class="codesh-copy-text">Copy</span>';
            $result .= '</button>';
            $result .= '</div>';

            // Add the code
            $result .= '<div class="codesh-code">' . $html . '</div>';
            $result .= '</div>';

            return $result;

        } catch (\Exception $e) {
            // Log the error
            $this->grav['log']->error('CodeSh (main): Error highlighting lang="' . $lang . '": ' . $e->getMessage());
            // Fallback to original on error
            return '<div class="codesh-block codesh-error no-header" data-error="' . htmlspecialchars($e->getMessage()) . '"><pre><code>' . htmlspecialchars($code) . '</code></pre></div>';
        }
    }
}
