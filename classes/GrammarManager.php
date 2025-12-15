<?php

namespace Grav\Plugin\Codesh;

use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;

/**
 * Manages custom TextMate grammar files for Codesh
 */
class GrammarManager
{
    protected Grav $grav;
    protected string $customGrammarsPath;
    protected string $pluginGrammarsPath;
    protected string $phikiGrammarsPath;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->customGrammarsPath = $grav['locator']->findResource('user://data/codesh/grammars', true, true);
        $this->pluginGrammarsPath = __DIR__ . '/../grammars';
        $this->phikiGrammarsPath = __DIR__ . '/../vendor/phiki/phiki/resources/grammars';

        // Ensure custom grammars directory exists
        if (!is_dir($this->customGrammarsPath)) {
            Folder::create($this->customGrammarsPath);
        }
    }

    /**
     * Get paths to all grammar directories
     */
    public function getGrammarPaths(): array
    {
        return [
            'plugin' => $this->pluginGrammarsPath,
            'phiki' => $this->phikiGrammarsPath,
            'custom' => $this->customGrammarsPath
        ];
    }

    /**
     * List all custom grammars
     */
    public function listCustomGrammars(): array
    {
        $grammars = [];

        if (is_dir($this->customGrammarsPath)) {
            foreach (glob($this->customGrammarsPath . '/*.json') as $grammarFile) {
                $slug = basename($grammarFile, '.json');
                $data = $this->loadGrammarFile($grammarFile);

                if ($data) {
                    $grammars[] = [
                        'slug' => $slug,
                        'name' => $data['name'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                        'scopeName' => $data['scopeName'] ?? 'source.' . $slug,
                        'fileTypes' => $data['fileTypes'] ?? [],
                        'custom' => true,
                        'source' => 'custom'
                    ];
                }
            }
        }

        // Sort by name
        usort($grammars, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $grammars;
    }

    /**
     * List CodeSh plugin grammars (e.g., shortcode)
     */
    public function listPluginGrammars(): array
    {
        $grammars = [];

        if (is_dir($this->pluginGrammarsPath)) {
            foreach (glob($this->pluginGrammarsPath . '/*.json') as $grammarFile) {
                $slug = basename($grammarFile, '.json');
                $data = $this->loadGrammarFile($grammarFile);

                if ($data) {
                    $grammars[] = [
                        'slug' => $slug,
                        'name' => $data['name'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                        'scopeName' => $data['scopeName'] ?? 'source.' . $slug,
                        'fileTypes' => $data['fileTypes'] ?? [],
                        'custom' => false,
                        'source' => 'plugin'
                    ];
                }
            }
        }

        // Sort by name
        usort($grammars, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $grammars;
    }

    /**
     * List Phiki vendor grammars
     */
    public function listVendorGrammars(): array
    {
        $grammars = [];

        if (is_dir($this->phikiGrammarsPath)) {
            foreach (glob($this->phikiGrammarsPath . '/*.json') as $grammarFile) {
                $slug = basename($grammarFile, '.json');
                $data = $this->loadGrammarFile($grammarFile);

                if ($data) {
                    $grammars[] = [
                        'slug' => $slug,
                        'name' => $data['name'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                        'scopeName' => $data['scopeName'] ?? 'source.' . $slug,
                        'fileTypes' => $data['fileTypes'] ?? [],
                        'custom' => false,
                        'source' => 'vendor'
                    ];
                }
            }
        }

        // Sort by name
        usort($grammars, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $grammars;
    }

    /**
     * List all built-in grammars (plugin + Phiki vendor) - for backwards compatibility
     */
    public function listBuiltinGrammars(): array
    {
        return array_merge($this->listPluginGrammars(), $this->listVendorGrammars());
    }

    /**
     * List all grammars (built-in + custom)
     */
    public function listAllGrammars(): array
    {
        return array_merge($this->listBuiltinGrammars(), $this->listCustomGrammars());
    }

    /**
     * Get a grammar by slug
     */
    public function getGrammar(string $slug): ?array
    {
        // Check custom grammars first
        $customPath = $this->customGrammarsPath . '/' . $slug . '.json';
        if (file_exists($customPath)) {
            return $this->loadGrammarFile($customPath);
        }

        // Check plugin grammars
        $pluginPath = $this->pluginGrammarsPath . '/' . $slug . '.json';
        if (file_exists($pluginPath)) {
            return $this->loadGrammarFile($pluginPath);
        }

        // Check Phiki vendor grammars
        $phikiPath = $this->phikiGrammarsPath . '/' . $slug . '.json';
        if (file_exists($phikiPath)) {
            return $this->loadGrammarFile($phikiPath);
        }

        return null;
    }

    /**
     * Get grammar file path by slug
     */
    public function getGrammarPath(string $slug): ?string
    {
        // Check custom grammars first
        $customPath = $this->customGrammarsPath . '/' . $slug . '.json';
        if (file_exists($customPath)) {
            return $customPath;
        }

        // Check plugin grammars
        $pluginPath = $this->pluginGrammarsPath . '/' . $slug . '.json';
        if (file_exists($pluginPath)) {
            return $pluginPath;
        }

        // Check Phiki vendor grammars
        $phikiPath = $this->phikiGrammarsPath . '/' . $slug . '.json';
        if (file_exists($phikiPath)) {
            return $phikiPath;
        }

        return null;
    }

    /**
     * Check if a grammar exists
     */
    public function grammarExists(string $slug): bool
    {
        return $this->getGrammarPath($slug) !== null;
    }

    /**
     * Check if a custom grammar exists
     */
    public function customGrammarExists(string $slug): bool
    {
        return file_exists($this->customGrammarsPath . '/' . $slug . '.json');
    }

    /**
     * Save a custom grammar
     */
    public function saveGrammar(string $slug, array $data): bool
    {
        // Validate slug
        if (!preg_match('/^[a-z0-9-_]+$/i', $slug)) {
            throw new \InvalidArgumentException('Invalid grammar slug. Use only letters, numbers, hyphens, and underscores.');
        }

        // Ensure metadata
        if (!isset($data['_metadata'])) {
            $data['_metadata'] = [];
        }
        $data['_metadata']['modified'] = date('c');
        if (!isset($data['_metadata']['created'])) {
            $data['_metadata']['created'] = date('c');
        }
        $data['_metadata']['generator'] = 'CodeSh Grammar Manager';

        $path = $this->customGrammarsPath . '/' . $slug . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($path, $json) !== false;
    }

    /**
     * Import a grammar from uploaded file
     */
    public function importGrammar(array $uploadedFile): string
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed with error code: ' . $uploadedFile['error']);
        }

        $content = file_get_contents($uploadedFile['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON file: ' . json_last_error_msg());
        }

        // Validate required fields
        if (!isset($data['scopeName'])) {
            throw new \InvalidArgumentException('Invalid grammar file: missing scopeName');
        }

        if (!isset($data['patterns']) && !isset($data['repository'])) {
            throw new \InvalidArgumentException('Invalid grammar file: missing patterns or repository');
        }

        // Generate slug from name or scopeName
        $name = $data['name'] ?? $data['scopeName'];
        $slug = preg_replace('/[^a-z0-9-_]/i', '-', strtolower($name));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));

        // Handle name conflicts
        $finalSlug = $slug;
        $counter = 2;
        while ($this->customGrammarExists($finalSlug)) {
            $finalSlug = $slug . '-' . $counter;
            $counter++;
        }

        // Add import metadata
        $data['_metadata'] = [
            'created' => date('c'),
            'imported' => true,
            'originalFilename' => $uploadedFile['name'],
            'generator' => 'CodeSh Grammar Manager'
        ];

        $this->saveGrammar($finalSlug, $data);

        return $finalSlug;
    }

    /**
     * Delete a custom grammar
     */
    public function deleteGrammar(string $slug): bool
    {
        $path = $this->customGrammarsPath . '/' . $slug . '.json';

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Load a grammar file
     */
    protected function loadGrammarFile(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}
