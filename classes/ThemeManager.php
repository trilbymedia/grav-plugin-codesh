<?php

namespace Grav\Plugin\Codesh;

use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
use Phiki\Phiki;

class ThemeManager
{
    protected Grav $grav;
    protected string $customThemesPath;
    protected string $builtinThemesPath;
    protected ?Phiki $phiki = null;

    // 10 core editable colors
    public const CORE_COLORS = [
        'background',
        'foreground',
        'comment',
        'keyword',
        'string',
        'number',
        'function',
        'type',
        'variable',
        'operator'
    ];

    // Default dark theme colors (One Dark inspired)
    public const DEFAULT_DARK_COLORS = [
        'background' => '#1F1F22',
        'foreground' => '#abb2bf',
        'comment' => '#7f848e',
        'keyword' => '#c678dd',
        'string' => '#98c379',
        'number' => '#d19a66',
        'function' => '#61afef',
        'type' => '#e5c07b',
        'variable' => '#e06c75',
        'operator' => '#56b6c2'
    ];

    // Default light theme colors
    public const DEFAULT_LIGHT_COLORS = [
        'background' => '#FAFAFA',
        'foreground' => '#383A42',
        'comment' => '#A0A1A7',
        'keyword' => '#A626A4',
        'string' => '#50A14F',
        'number' => '#986801',
        'function' => '#4078F2',
        'type' => '#C18401',
        'variable' => '#E45649',
        'operator' => '#0184BC'
    ];

    // Sample code for previews
    protected array $previewCode = [
        'php' => '<?php
namespace App\Controllers;

class UserController extends Controller
{
    private array $users = [];
    private const MAX_USERS = 100;

    public function getUser(int $id): ?User
    {
        // Find user by ID
        if ($id <= 0 || $id > self::MAX_USERS) {
            return null;
        }
        return $this->users[$id] ?? null;
    }

    public function createUser(string $name): User
    {
        $user = new User($name);
        $this->users[] = $user;
        return $user;
    }
}',
        'javascript' => '// User management module
import { validateEmail } from "./utils";

class UserService {
    constructor(apiUrl) {
        this.apiUrl = apiUrl;
        this.cache = new Map();
    }

    async fetchUser(userId) {
        const cached = this.cache.get(userId);
        if (cached) return cached;

        const response = await fetch(`${this.apiUrl}/users/${userId}`);
        const user = await response.json();

        this.cache.set(userId, user);
        return user;
    }
}

export default UserService;',
        'python' => '"""User management module."""
from dataclasses import dataclass
from typing import Optional, List

@dataclass
class User:
    id: int
    name: str
    email: str
    active: bool = True

class UserRepository:
    def __init__(self):
        self._users: List[User] = []

    def find_by_id(self, user_id: int) -> Optional[User]:
        """Find a user by their ID."""
        for user in self._users:
            if user.id == user_id:
                return user
        return None

    def add(self, user: User) -> None:
        self._users.append(user)',
        'css' => '/* Theme variables */
:root {
    --primary-color: #3B82F6;
    --secondary-color: #8B5CF6;
    --text-color: #1F2937;
    --bg-color: #FFFFFF;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.button {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    background: var(--primary-color);
    color: white;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.button:hover {
    background: var(--secondary-color);
    transform: translateY(-2px);
}',
        'html' => '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="/" class="logo">Dashboard</a>
            <ul class="menu">
                <li><a href="/users">Users</a></li>
                <li><a href="/settings">Settings</a></li>
            </ul>
        </nav>
    </header>
    <main id="app">
        <!-- Content loaded dynamically -->
    </main>
    <script src="/js/app.js" defer></script>
</body>
</html>'
    ];

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->customThemesPath = $grav['locator']->findResource('user://data/codesh/themes', true, true);
        $this->builtinThemesPath = __DIR__ . '/../themes';

        // Ensure custom themes directory exists
        if (!is_dir($this->customThemesPath)) {
            Folder::create($this->customThemesPath);
        }
    }

    /**
     * Get Phiki instance
     */
    protected function getPhiki(): Phiki
    {
        if ($this->phiki === null) {
            $this->phiki = new Phiki();

            // Register builtin custom themes
            if (is_dir($this->builtinThemesPath)) {
                foreach (glob($this->builtinThemesPath . '/*.json') as $themeFile) {
                    $themeName = basename($themeFile, '.json');
                    $this->phiki->theme($themeName, $themeFile);
                }
            }

            // Register user custom themes
            if (is_dir($this->customThemesPath)) {
                foreach (glob($this->customThemesPath . '/*.json') as $themeFile) {
                    $themeName = basename($themeFile, '.json');
                    $this->phiki->theme($themeName, $themeFile);
                }
            }
        }

        return $this->phiki;
    }

    /**
     * List all custom themes
     */
    public function listCustomThemes(): array
    {
        $themes = [];

        if (is_dir($this->customThemesPath)) {
            foreach (glob($this->customThemesPath . '/*.json') as $themeFile) {
                $name = basename($themeFile, '.json');
                $data = $this->loadThemeFile($themeFile);

                if ($data) {
                    $themes[] = [
                        'name' => $name,
                        'displayName' => $data['displayName'] ?? ucwords(str_replace(['-', '_'], ' ', $name)),
                        'type' => $data['type'] ?? 'dark',
                        'custom' => true,
                        'path' => $themeFile,
                        'created' => $data['_metadata']['created'] ?? null,
                        'copiedFrom' => $data['_metadata']['copiedFrom'] ?? null,
                        'colors' => $this->extractBasicColors($data)
                    ];
                }
            }
        }

        return $themes;
    }

    /**
     * List all built-in themes
     */
    public function listBuiltinThemes(): array
    {
        $themes = [];

        // Get Phiki's built-in themes
        $phikiThemesPath = __DIR__ . '/../vendor/phiki/phiki/resources/themes';

        if (is_dir($phikiThemesPath)) {
            foreach (glob($phikiThemesPath . '/*.json') as $themeFile) {
                $name = basename($themeFile, '.json');
                $data = $this->loadThemeFile($themeFile);

                if ($data) {
                    $themes[] = [
                        'name' => $name,
                        'displayName' => $data['displayName'] ?? ucwords(str_replace(['-', '_'], ' ', $name)),
                        'type' => $data['type'] ?? 'dark',
                        'custom' => false,
                        'path' => $themeFile,
                        'colors' => $this->extractBasicColors($data)
                    ];
                }
            }
        }

        // Add plugin's custom themes (helios-light, helios-dark)
        if (is_dir($this->builtinThemesPath)) {
            foreach (glob($this->builtinThemesPath . '/*.json') as $themeFile) {
                $name = basename($themeFile, '.json');
                $data = $this->loadThemeFile($themeFile);

                if ($data) {
                    $themes[] = [
                        'name' => $name,
                        'displayName' => $data['displayName'] ?? ucwords(str_replace(['-', '_'], ' ', $name)),
                        'type' => $data['type'] ?? 'dark',
                        'custom' => false,
                        'path' => $themeFile,
                        'colors' => $this->extractBasicColors($data)
                    ];
                }
            }
        }

        return $themes;
    }

    /**
     * Extract basic colors from theme data for preview
     */
    protected function extractBasicColors(array $data): array
    {
        $colors = $data['colors'] ?? [];
        $tokenColors = $data['tokenColors'] ?? [];

        // Get background and foreground from colors
        $background = $colors['editor.background'] ?? $colors['background'] ?? '#1f1f22';
        $foreground = $colors['editor.foreground'] ?? $colors['foreground'] ?? '#abb2bf';

        // Extract token colors
        $keyword = $foreground;
        $string = $foreground;
        $comment = $foreground;
        $function = $foreground;
        $type = $foreground;

        foreach ($tokenColors as $token) {
            $scopes = $token['scope'] ?? [];
            if (is_string($scopes)) {
                $scopes = [$scopes];
            }
            $color = $token['settings']['foreground'] ?? null;
            if (!$color) continue;

            foreach ($scopes as $scope) {
                if (strpos($scope, 'keyword') !== false && $keyword === $foreground) {
                    $keyword = $color;
                }
                if (strpos($scope, 'string') !== false && $string === $foreground) {
                    $string = $color;
                }
                if (strpos($scope, 'comment') !== false && $comment === $foreground) {
                    $comment = $color;
                }
                if ((strpos($scope, 'function') !== false || strpos($scope, 'entity.name.function') !== false) && $function === $foreground) {
                    $function = $color;
                }
                if ((strpos($scope, 'entity.name.type') !== false || strpos($scope, 'support.class') !== false) && $type === $foreground) {
                    $type = $color;
                }
            }
        }

        return [
            'background' => $background,
            'foreground' => $foreground,
            'keyword' => $keyword,
            'string' => $string,
            'comment' => $comment,
            'function' => $function,
            'type' => $type
        ];
    }

    /**
     * List all themes (built-in + custom)
     */
    public function listAllThemes(): array
    {
        return array_merge($this->listBuiltinThemes(), $this->listCustomThemes());
    }

    /**
     * Get a theme by name
     */
    public function getTheme(string $name): ?array
    {
        // Check custom themes first
        $customPath = $this->customThemesPath . '/' . $name . '.json';
        if (file_exists($customPath)) {
            return $this->loadThemeFile($customPath);
        }

        // Check plugin builtin themes
        $builtinPath = $this->builtinThemesPath . '/' . $name . '.json';
        if (file_exists($builtinPath)) {
            return $this->loadThemeFile($builtinPath);
        }

        // Check Phiki builtin themes
        $phikiPath = __DIR__ . '/../vendor/phiki/phiki/resources/themes/' . $name . '.json';
        if (file_exists($phikiPath)) {
            return $this->loadThemeFile($phikiPath);
        }

        return null;
    }

    /**
     * Check if a theme exists
     */
    public function themeExists(string $name): bool
    {
        return $this->getTheme($name) !== null;
    }

    /**
     * Check if a custom theme exists
     */
    public function customThemeExists(string $name): bool
    {
        return file_exists($this->customThemesPath . '/' . $name . '.json');
    }

    /**
     * Save a custom theme
     */
    public function saveTheme(string $name, array $data): bool
    {
        // Validate name
        if (!preg_match('/^[a-z0-9-_]+$/i', $name)) {
            throw new \InvalidArgumentException('Invalid theme name. Use only letters, numbers, hyphens, and underscores.');
        }

        // Ensure metadata
        if (!isset($data['_metadata'])) {
            $data['_metadata'] = [];
        }
        $data['_metadata']['modified'] = date('c');
        if (!isset($data['_metadata']['created'])) {
            $data['_metadata']['created'] = date('c');
        }
        $data['_metadata']['generator'] = 'CodeSh Theme Manager';

        // Update name in theme
        $data['name'] = $name;
        if (!isset($data['displayName'])) {
            $data['displayName'] = ucwords(str_replace(['-', '_'], ' ', $name));
        }

        $path = $this->customThemesPath . '/' . $name . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($path, $json) !== false;
    }

    /**
     * Delete a custom theme
     */
    public function deleteTheme(string $name): bool
    {
        $path = $this->customThemesPath . '/' . $name . '.json';

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Copy a theme as a new custom theme
     */
    public function copyTheme(string $sourceName, string $newName): bool
    {
        // Validate new name
        if (!preg_match('/^[a-z0-9-_]+$/i', $newName)) {
            throw new \InvalidArgumentException('Invalid theme name. Use only letters, numbers, hyphens, and underscores.');
        }

        // Handle name conflicts
        $finalName = $newName;
        $counter = 2;
        while ($this->customThemeExists($finalName)) {
            $finalName = $newName . '-' . $counter;
            $counter++;
        }

        // Load source theme
        $source = $this->getTheme($sourceName);
        if (!$source) {
            throw new \InvalidArgumentException('Source theme not found: ' . $sourceName);
        }

        // Update metadata
        $source['name'] = $finalName;
        $source['displayName'] = ucwords(str_replace(['-', '_'], ' ', $finalName));
        $source['_metadata'] = [
            'created' => date('c'),
            'copiedFrom' => $sourceName,
            'generator' => 'CodeSh Theme Manager'
        ];

        return $this->saveTheme($finalName, $source);
    }

    /**
     * Extract core editable colors from a full theme
     */
    public function extractCoreColors(array $theme): array
    {
        $colors = [];
        $type = $theme['type'] ?? 'dark';
        $defaults = $type === 'light' ? self::DEFAULT_LIGHT_COLORS : self::DEFAULT_DARK_COLORS;

        // Get background and foreground from colors section
        $colors['background'] = $theme['colors']['editor.background'] ?? $defaults['background'];
        $colors['foreground'] = $theme['colors']['editor.foreground'] ?? $defaults['foreground'];

        // Extract from tokenColors by scope
        $scopeMap = [
            'comment' => ['comment', 'punctuation.definition.comment'],
            'keyword' => ['keyword', 'keyword.control', 'storage', 'storage.type'],
            'string' => ['string', 'string.quoted'],
            'number' => ['constant.numeric', 'constant'],
            'function' => ['entity.name.function', 'support.function'],
            'type' => ['entity.name.type', 'entity.name.class', 'support.class'],
            'variable' => ['variable', 'variable.other'],
            'operator' => ['keyword.operator', 'punctuation']
        ];

        foreach ($scopeMap as $colorKey => $scopes) {
            $colors[$colorKey] = $this->findColorForScopes($theme['tokenColors'] ?? [], $scopes)
                ?? $defaults[$colorKey];
        }

        return $colors;
    }

    /**
     * Find color for given scopes in tokenColors
     */
    protected function findColorForScopes(array $tokenColors, array $targetScopes): ?string
    {
        foreach ($tokenColors as $rule) {
            $ruleScopes = (array)($rule['scope'] ?? []);
            foreach ($targetScopes as $target) {
                foreach ($ruleScopes as $scope) {
                    if ($scope === $target || strpos($scope, $target) === 0) {
                        return $rule['settings']['foreground'] ?? null;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Generate a full theme from core colors
     */
    public function generateFromCoreColors(array $coreColors, string $variant = 'dark'): array
    {
        $transformer = new ColorTransformer();

        // Merge with defaults
        $defaults = $variant === 'light' ? self::DEFAULT_LIGHT_COLORS : self::DEFAULT_DARK_COLORS;
        $colors = array_merge($defaults, array_filter($coreColors));

        return [
            'name' => 'custom-theme',
            'displayName' => 'Custom Theme',
            'type' => $variant,
            'colors' => $this->generateUIColors($colors, $variant, $transformer),
            'tokenColors' => $this->generateTokenColors($colors, $transformer),
            '_metadata' => [
                'created' => date('c'),
                'generator' => 'CodeSh Theme Manager',
                'coreColors' => $colors
            ]
        ];
    }

    /**
     * Generate UI colors section
     */
    protected function generateUIColors(array $colors, string $variant, ColorTransformer $transformer): array
    {
        $bg = $colors['background'];
        $fg = $colors['foreground'];
        $accent = $colors['function']; // Use function color as accent

        $isLight = $variant === 'light';
        $lightenAmount = $isLight ? -3 : 3;

        return [
            'editor.background' => $bg,
            'editor.foreground' => $fg,
            'editor.lineHighlightBackground' => $transformer->adjustBrightness($bg, $lightenAmount),
            'editor.selectionBackground' => $transformer->alpha($accent, 0.2),
            'editorCursor.foreground' => $accent,
            'editorLineNumber.foreground' => $transformer->alpha($fg, 0.4),
            'editorLineNumber.activeForeground' => $fg,
            'editorIndentGuide.background' => $transformer->alpha($fg, 0.1),
            'editorIndentGuide.activeBackground' => $transformer->alpha($fg, 0.3),
            'editorWhitespace.foreground' => $transformer->alpha($fg, 0.1),
            'editorRuler.foreground' => $transformer->alpha($fg, 0.1),

            // Sidebar
            'sideBar.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'sideBar.foreground' => $fg,
            'sideBarTitle.foreground' => $fg,
            'sideBarSectionHeader.background' => $bg,

            // Activity bar
            'activityBar.background' => $transformer->adjustBrightness($bg, $lightenAmount * 2),
            'activityBar.foreground' => $fg,
            'activityBarBadge.background' => $accent,
            'activityBarBadge.foreground' => $isLight ? '#FFFFFF' : $bg,

            // Status bar
            'statusBar.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'statusBar.foreground' => $transformer->alpha($fg, 0.8),
            'statusBar.noFolderBackground' => $transformer->adjustBrightness($bg, $lightenAmount),

            // Tabs
            'tab.activeBackground' => $bg,
            'tab.activeForeground' => $fg,
            'tab.inactiveBackground' => $transformer->adjustBrightness($bg, $lightenAmount),
            'tab.inactiveForeground' => $transformer->alpha($fg, 0.6),
            'tab.border' => $transformer->adjustBrightness($bg, $lightenAmount * 3),
            'editorGroupHeader.tabsBackground' => $transformer->adjustBrightness($bg, $lightenAmount),

            // Input
            'input.background' => $transformer->adjustBrightness($bg, $isLight ? 3 : -2),
            'input.foreground' => $fg,
            'input.border' => $transformer->alpha($fg, 0.1),
            'input.placeholderForeground' => $transformer->alpha($fg, 0.4),

            // Buttons
            'button.background' => $accent,
            'button.foreground' => $isLight ? '#FFFFFF' : $bg,
            'button.hoverBackground' => $transformer->adjustBrightness($accent, 5),

            // Focus & selection
            'focusBorder' => $accent,
            'list.activeSelectionBackground' => $transformer->alpha($accent, 0.15),
            'list.activeSelectionForeground' => $fg,
            'list.hoverBackground' => $transformer->alpha($fg, 0.05),
            'list.inactiveSelectionBackground' => $transformer->alpha($accent, 0.1),

            // Widgets
            'editorWidget.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'editorWidget.border' => $transformer->adjustBrightness($bg, $lightenAmount * 3),
            'editorHoverWidget.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'editorHoverWidget.border' => $transformer->adjustBrightness($bg, $lightenAmount * 3),
            'editorSuggestWidget.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'editorSuggestWidget.border' => $transformer->adjustBrightness($bg, $lightenAmount * 3),
            'editorSuggestWidget.selectedBackground' => $transformer->alpha($accent, 0.15),

            // Scrollbar
            'scrollbarSlider.background' => $transformer->alpha($fg, 0.2),
            'scrollbarSlider.hoverBackground' => $transformer->alpha($fg, 0.3),
            'scrollbarSlider.activeBackground' => $transformer->alpha($fg, 0.4),

            // Notifications
            'notification.background' => $transformer->adjustBrightness($bg, $lightenAmount * 2),

            // Errors/warnings
            'editorError.foreground' => $colors['variable'],
            'editorWarning.foreground' => $colors['number'],
            'editorInfo.foreground' => $colors['function'],

            // Title bar
            'titleBar.activeBackground' => $transformer->adjustBrightness($bg, $lightenAmount),
            'titleBar.activeForeground' => $fg,
            'titleBar.inactiveBackground' => $transformer->adjustBrightness($bg, $lightenAmount),
            'titleBar.inactiveForeground' => $transformer->alpha($fg, 0.6),

            // Dropdown
            'dropdown.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'dropdown.border' => $transformer->adjustBrightness($bg, $lightenAmount * 3),

            // Peek view
            'peekView.border' => $accent,
            'peekViewEditor.background' => $bg,
            'peekViewResult.background' => $transformer->adjustBrightness($bg, $lightenAmount),
            'peekViewTitle.background' => $bg,

            // Badge
            'badge.background' => $accent,
            'badge.foreground' => $isLight ? '#FFFFFF' : $bg,

            // Extension button
            'extensionButton.prominentBackground' => $colors['string'],
            'extensionButton.prominentHoverBackground' => $transformer->adjustBrightness($colors['string'], 5),
        ];
    }

    /**
     * Generate token colors section
     */
    protected function generateTokenColors(array $colors, ColorTransformer $transformer): array
    {
        return [
            // Comments
            [
                'scope' => ['comment', 'punctuation.definition.comment'],
                'settings' => [
                    'foreground' => $colors['comment'],
                    'fontStyle' => 'italic'
                ]
            ],
            [
                'scope' => ['comment.block.documentation', 'comment.block.documentation punctuation'],
                'settings' => [
                    'foreground' => $colors['comment'],
                    'fontStyle' => 'italic'
                ]
            ],

            // Keywords
            [
                'scope' => ['keyword', 'keyword.control', 'keyword.control.flow'],
                'settings' => ['foreground' => $colors['keyword']]
            ],
            [
                'scope' => ['storage', 'storage.type', 'storage.modifier'],
                'settings' => ['foreground' => $colors['keyword']]
            ],
            [
                'scope' => ['keyword.operator.new', 'keyword.operator.expression', 'keyword.operator.logical'],
                'settings' => ['foreground' => $colors['keyword']]
            ],

            // Strings
            [
                'scope' => ['string', 'string.quoted', 'string.quoted.single', 'string.quoted.double'],
                'settings' => ['foreground' => $colors['string']]
            ],
            [
                'scope' => ['string.template', 'string.interpolated'],
                'settings' => ['foreground' => $colors['string']]
            ],
            [
                'scope' => ['string.regexp'],
                'settings' => ['foreground' => $colors['operator']]
            ],
            [
                'scope' => ['punctuation.definition.string'],
                'settings' => ['foreground' => $colors['string']]
            ],

            // Numbers and constants
            [
                'scope' => ['constant.numeric', 'constant.numeric.integer', 'constant.numeric.float'],
                'settings' => ['foreground' => $colors['number']]
            ],
            [
                'scope' => ['constant.language', 'constant.language.boolean'],
                'settings' => ['foreground' => $colors['number']]
            ],
            [
                'scope' => ['constant.character', 'constant.character.escape'],
                'settings' => ['foreground' => $colors['number']]
            ],

            // Functions
            [
                'scope' => ['entity.name.function', 'entity.name.function.member'],
                'settings' => ['foreground' => $colors['function']]
            ],
            [
                'scope' => ['support.function', 'support.function.builtin'],
                'settings' => ['foreground' => $colors['function']]
            ],
            [
                'scope' => ['meta.function-call', 'meta.method-call entity.name.function'],
                'settings' => ['foreground' => $colors['function']]
            ],
            [
                'scope' => ['keyword.other.special-method'],
                'settings' => ['foreground' => $transformer->adjustBrightness($colors['function'], 10)]
            ],

            // Types and classes
            [
                'scope' => ['entity.name.type', 'entity.name.type.class'],
                'settings' => ['foreground' => $colors['type']]
            ],
            [
                'scope' => ['entity.name.class', 'entity.other.inherited-class'],
                'settings' => ['foreground' => $colors['type']]
            ],
            [
                'scope' => ['support.class', 'support.type'],
                'settings' => ['foreground' => $colors['type']]
            ],
            [
                'scope' => ['entity.name.type.interface', 'entity.name.type.enum'],
                'settings' => ['foreground' => $colors['type']]
            ],
            [
                'scope' => ['entity.name.namespace', 'entity.name.module'],
                'settings' => ['foreground' => $colors['type']]
            ],

            // Variables
            [
                'scope' => ['variable', 'variable.other', 'variable.other.readwrite'],
                'settings' => ['foreground' => $colors['variable']]
            ],
            [
                'scope' => ['variable.parameter', 'variable.parameter.function'],
                'settings' => [
                    'foreground' => $colors['foreground'],
                    'fontStyle' => 'italic'
                ]
            ],
            [
                'scope' => ['variable.other.constant', 'variable.other.enummember'],
                'settings' => ['foreground' => $colors['number']]
            ],
            [
                'scope' => ['variable.language', 'variable.language.this', 'variable.language.self'],
                'settings' => ['foreground' => $colors['variable']]
            ],

            // Operators and punctuation
            [
                'scope' => ['keyword.operator', 'keyword.operator.assignment', 'keyword.operator.arithmetic'],
                'settings' => ['foreground' => $colors['operator']]
            ],
            [
                'scope' => ['keyword.operator.comparison', 'keyword.operator.relational'],
                'settings' => ['foreground' => $colors['operator']]
            ],
            [
                'scope' => ['punctuation', 'punctuation.separator', 'punctuation.terminator'],
                'settings' => ['foreground' => $colors['foreground']]
            ],
            [
                'scope' => ['punctuation.section', 'punctuation.section.embedded'],
                'settings' => ['foreground' => $colors['variable']]
            ],
            [
                'scope' => ['meta.brace', 'punctuation.definition.block'],
                'settings' => ['foreground' => $colors['foreground']]
            ],

            // Properties and attributes
            [
                'scope' => ['entity.other.attribute-name'],
                'settings' => [
                    'foreground' => $colors['number'],
                    'fontStyle' => 'italic'
                ]
            ],
            [
                'scope' => ['support.type.property-name', 'entity.name.tag.css'],
                'settings' => ['foreground' => $colors['foreground']]
            ],
            [
                'scope' => ['support.type.property-name.json'],
                'settings' => ['foreground' => $colors['variable']]
            ],

            // Tags (HTML/XML)
            [
                'scope' => ['entity.name.tag', 'entity.name.tag.html'],
                'settings' => ['foreground' => $colors['variable']]
            ],
            [
                'scope' => ['punctuation.definition.tag'],
                'settings' => ['foreground' => $colors['foreground']]
            ],

            // Markup (Markdown)
            [
                'scope' => ['markup.heading', 'markup.heading entity.name'],
                'settings' => [
                    'foreground' => $colors['variable'],
                    'fontStyle' => 'bold'
                ]
            ],
            [
                'scope' => ['markup.bold'],
                'settings' => [
                    'foreground' => $colors['number'],
                    'fontStyle' => 'bold'
                ]
            ],
            [
                'scope' => ['markup.italic'],
                'settings' => [
                    'foreground' => $colors['keyword'],
                    'fontStyle' => 'italic'
                ]
            ],
            [
                'scope' => ['markup.inline.raw', 'markup.raw.inline'],
                'settings' => ['foreground' => $colors['string']]
            ],
            [
                'scope' => ['markup.underline.link'],
                'settings' => ['foreground' => $colors['function']]
            ],
            [
                'scope' => ['markup.quote'],
                'settings' => [
                    'foreground' => $colors['comment'],
                    'fontStyle' => 'italic'
                ]
            ],
            [
                'scope' => ['markup.list', 'punctuation.definition.list'],
                'settings' => ['foreground' => $colors['variable']]
            ],

            // Diff
            [
                'scope' => ['markup.inserted', 'markup.inserted.git_gutter', 'diff.inserted'],
                'settings' => ['foreground' => $colors['string']]
            ],
            [
                'scope' => ['markup.deleted', 'markup.deleted.git_gutter', 'diff.deleted'],
                'settings' => ['foreground' => $colors['variable']]
            ],
            [
                'scope' => ['markup.changed', 'markup.changed.git_gutter', 'diff.changed'],
                'settings' => ['foreground' => $colors['number']]
            ],
            [
                'scope' => ['meta.diff.range', 'meta.diff.header'],
                'settings' => [
                    'foreground' => $colors['function'],
                    'fontStyle' => 'bold'
                ]
            ],

            // CSS specific
            [
                'scope' => ['support.constant.property-value', 'support.constant.font-name'],
                'settings' => ['foreground' => $colors['string']]
            ],
            [
                'scope' => ['entity.other.attribute-name.class.css', 'entity.other.attribute-name.id.css'],
                'settings' => ['foreground' => $colors['type']]
            ],
            [
                'scope' => ['entity.other.attribute-name.pseudo-class', 'entity.other.attribute-name.pseudo-element'],
                'settings' => ['foreground' => $colors['function']]
            ],
            [
                'scope' => ['support.constant.color', 'constant.other.color'],
                'settings' => ['foreground' => $colors['number']]
            ],

            // Invalid/deprecated
            [
                'scope' => ['invalid', 'invalid.illegal'],
                'settings' => ['foreground' => $colors['variable']]
            ],
            [
                'scope' => ['invalid.deprecated'],
                'settings' => [
                    'foreground' => $colors['number'],
                    'fontStyle' => 'strikethrough'
                ]
            ],
        ];
    }

    /**
     * Normalize a hex color to #RRGGBB or #RRGGBBAA format
     * Accepts: #RGB, #RGBA, #RRGGBB, #RRGGBBAA
     */
    protected function normalizeColor(string $color): ?string
    {
        $color = trim($color);
        if (empty($color)) {
            return null;
        }

        // Match various hex formats
        if (preg_match('/^#([0-9A-Fa-f]{3})$/i', $color, $m)) {
            // #RGB -> #RRGGBB
            $r = $m[1][0];
            $g = $m[1][1];
            $b = $m[1][2];
            return '#' . $r . $r . $g . $g . $b . $b;
        }

        if (preg_match('/^#([0-9A-Fa-f]{4})$/i', $color, $m)) {
            // #RGBA -> #RRGGBBAA
            $r = $m[1][0];
            $g = $m[1][1];
            $b = $m[1][2];
            $a = $m[1][3];
            return '#' . $r . $r . $g . $g . $b . $b . $a . $a;
        }

        if (preg_match('/^#[0-9A-Fa-f]{6,8}$/i', $color)) {
            // Already valid #RRGGBB or #RRGGBBAA
            return $color;
        }

        return null; // Invalid format
    }

    /**
     * Validate a theme JSON structure (lenient for VS Code themes)
     */
    public function validateThemeJson(array $data): array
    {
        $errors = [];

        // Name is required
        if (!isset($data['name']) || empty($data['name'])) {
            $errors[] = 'Missing required field: name';
        }

        // Type is optional - we'll try to detect it if missing
        // Valid types: light, dark, hc (high contrast), hcLight
        if (isset($data['type']) && !in_array(strtolower($data['type']), ['light', 'dark', 'hc', 'hclight', 'hc-light'])) {
            $errors[] = 'Type must be "light", "dark", or "hc"';
        }

        if (!isset($data['colors']) || !is_array($data['colors'])) {
            $errors[] = 'Missing required field: colors (must be an object)';
        }
        // Note: We don't validate individual colors here - they'll be normalized during import

        if (!isset($data['tokenColors']) || !is_array($data['tokenColors'])) {
            $errors[] = 'Missing required field: tokenColors (must be an array)';
        }
        // Note: We don't validate individual token colors here - they'll be normalized during import

        return $errors;
    }

    /**
     * Normalize all colors in a theme data array
     */
    protected function normalizeThemeColors(array &$data): void
    {
        // Normalize colors object
        if (isset($data['colors']) && is_array($data['colors'])) {
            foreach ($data['colors'] as $key => $value) {
                if (!is_string($value)) continue;
                $normalized = $this->normalizeColor($value);
                if ($normalized !== null) {
                    $data['colors'][$key] = $normalized;
                }
            }
        }

        // Normalize tokenColors
        if (isset($data['tokenColors']) && is_array($data['tokenColors'])) {
            foreach ($data['tokenColors'] as $i => &$token) {
                if (!isset($token['settings']) || !is_array($token['settings'])) {
                    continue;
                }
                if (isset($token['settings']['foreground']) && is_string($token['settings']['foreground'])) {
                    $normalized = $this->normalizeColor($token['settings']['foreground']);
                    if ($normalized !== null) {
                        $token['settings']['foreground'] = $normalized;
                    } elseif (empty(trim($token['settings']['foreground']))) {
                        // Remove empty foreground values
                        unset($token['settings']['foreground']);
                    }
                }
                if (isset($token['settings']['background']) && is_string($token['settings']['background'])) {
                    $normalized = $this->normalizeColor($token['settings']['background']);
                    if ($normalized !== null) {
                        $token['settings']['background'] = $normalized;
                    } elseif (empty(trim($token['settings']['background']))) {
                        unset($token['settings']['background']);
                    }
                }
            }
            unset($token);
        }
    }

    /**
     * Detect theme type from name or colors if not specified
     */
    protected function detectThemeType(array $data): string
    {
        // If type is already set, normalize it
        if (isset($data['type'])) {
            $type = strtolower($data['type']);
            if (in_array($type, ['dark', 'hc'])) {
                return 'dark';
            }
            if (in_array($type, ['light', 'hclight', 'hc-light'])) {
                return 'light';
            }
        }

        // Try to detect from name
        $name = strtolower($data['name'] ?? '');
        if (strpos($name, 'light') !== false) {
            return 'light';
        }
        if (strpos($name, 'dark') !== false) {
            return 'dark';
        }

        // Try to detect from background color
        if (isset($data['colors']['editor.background'])) {
            $bg = $data['colors']['editor.background'];
            $normalized = $this->normalizeColor($bg);
            if ($normalized) {
                // Extract RGB values
                $r = hexdec(substr($normalized, 1, 2));
                $g = hexdec(substr($normalized, 3, 2));
                $b = hexdec(substr($normalized, 5, 2));
                // Calculate luminance
                $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                return $luminance > 0.5 ? 'light' : 'dark';
            }
        }

        // Default to dark
        return 'dark';
    }

    /**
     * Import a theme from a JSON file
     */
    public function importTheme(string $jsonPath, ?string $newName = null): string
    {
        $content = file_get_contents($jsonPath);
        if ($content === false) {
            throw new \RuntimeException('Failed to read theme file');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        // Validate (basic structure check)
        $errors = $this->validateThemeJson($data);
        if (!empty($errors)) {
            throw new \RuntimeException('Theme validation failed: ' . implode('; ', $errors));
        }

        // Normalize all colors (#RGB -> #RRGGBB, etc.)
        $this->normalizeThemeColors($data);

        // Detect/set theme type if not specified
        $data['type'] = $this->detectThemeType($data);

        // Determine name
        $name = $newName ?? ($data['name'] ?? basename($jsonPath, '.json'));
        $name = preg_replace('/[^a-z0-9-_]/i', '-', strtolower($name));

        // Handle conflicts
        $finalName = $name;
        $counter = 2;
        while ($this->customThemeExists($finalName)) {
            $finalName = $name . '-' . $counter;
            $counter++;
        }

        // Update name in data to match final name
        $data['name'] = $finalName;

        // Add metadata
        $data['_metadata'] = [
            'created' => date('c'),
            'imported' => true,
            'originalFile' => basename($jsonPath),
            'generator' => 'CodeSh Theme Manager'
        ];

        $this->saveTheme($finalName, $data);

        return $finalName;
    }

    /**
     * Export a theme as JSON
     */
    public function exportTheme(string $name): string
    {
        $theme = $this->getTheme($name);
        if (!$theme) {
            throw new \InvalidArgumentException('Theme not found: ' . $name);
        }

        // Remove internal metadata for export
        unset($theme['_metadata']);

        return json_encode($theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a preview of highlighted code
     */
    public function generatePreview(string $themeName, string $language = 'php'): string
    {
        $code = $this->previewCode[$language] ?? $this->previewCode['php'];

        try {
            $phiki = $this->getPhiki();
            $output = $phiki->codeToHtml($code, $language, $themeName);
            return $output->toString();
        } catch (\Exception $e) {
            return '<pre><code>' . htmlspecialchars($code) . '</code></pre>';
        }
    }

    /**
     * Generate preview from custom theme data (not yet saved)
     */
    public function generatePreviewFromData(array $themeData, string $language = 'php'): string
    {
        $code = $this->previewCode[$language] ?? $this->previewCode['php'];

        try {
            // Create a temp file for the theme
            $tempPath = sys_get_temp_dir() . '/codesh_preview_' . uniqid() . '.json';
            file_put_contents($tempPath, json_encode($themeData, JSON_PRETTY_PRINT));

            $phiki = new Phiki();
            $phiki->theme('preview', $tempPath);

            $output = $phiki->codeToHtml($code, $language, 'preview');
            $html = $output->toString();

            // Cleanup
            unlink($tempPath);

            return $html;
        } catch (\Exception $e) {
            return '<pre><code>' . htmlspecialchars($code) . '</code></pre>';
        }
    }

    /**
     * Get preview code for a language
     */
    public function getPreviewCode(string $language): string
    {
        return $this->previewCode[$language] ?? $this->previewCode['php'];
    }

    /**
     * Load and parse a theme JSON file
     */
    protected function loadThemeFile(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get the custom themes directory path
     */
    public function getCustomThemesPath(): string
    {
        return $this->customThemesPath;
    }
}
