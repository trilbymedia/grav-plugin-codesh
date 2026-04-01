<?php

declare(strict_types=1);

namespace Grav\Plugin\Codesh;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * API controller for codesh plugin — serves theme and grammar data
 * to admin-next web component fields.
 */
class ApiController
{
    protected Grav $grav;

    public function __construct(Grav $grav, Config $config)
    {
        $this->grav = $grav;
    }

    /**
     * GET /codesh/themes — List all available code themes.
     */
    public function themes(ServerRequestInterface $request): ResponseInterface
    {
        $tm = $this->getThemeManager();

        return $this->json([
            'builtin' => $tm->listBuiltinThemes(),
            'custom' => $tm->listCustomThemes(),
        ]);
    }

    /**
     * GET /codesh/grammars — List all available grammars.
     */
    public function grammars(ServerRequestInterface $request): ResponseInterface
    {
        $gm = $this->getGrammarManager();
        $all = $gm->listAllGrammars();

        $custom = [];
        $builtin = [];
        foreach ($all as $grammar) {
            if (!empty($grammar['custom'])) {
                $custom[] = $grammar;
            } else {
                $builtin[] = $grammar;
            }
        }

        return $this->json([
            'builtin' => $builtin,
            'custom' => $custom,
        ]);
    }

    private function getThemeManager(): ThemeManager
    {
        return new ThemeManager($this->grav);
    }

    private function getGrammarManager(): GrammarManager
    {
        return new GrammarManager($this->grav);
    }

    /**
     * POST /codesh/themes/import — Import a custom theme JSON file.
     */
    public function importTheme(ServerRequestInterface $request): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? $files['theme_file'] ?? null;
        $clientFilename = null;

        // Fallback: PSR-7 may not parse multipart uploads — check $_FILES directly
        if (!$file && !empty($_FILES)) {
            $raw = $_FILES['file'] ?? $_FILES['theme_file'] ?? null;
            if ($raw && $raw['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($raw['tmp_name']);
                $clientFilename = $raw['name'];
            }
        }

        if (!isset($content)) {
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                return new Response(400, ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'No file uploaded or upload error.']));
            }
            $content = (string) $file->getStream();
            $clientFilename = $file->getClientFilename();
        }

        $tm = $this->getThemeManager();
        $json = json_decode($content, true);

        if (!$json) {
            return new Response(400, ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Invalid JSON file.']));
        }

        // importTheme expects a file path — write to temp
        $tmpPath = tempnam(sys_get_temp_dir(), 'codesh_theme_');
        file_put_contents($tmpPath, $content);
        $name = pathinfo($clientFilename ?? 'theme', PATHINFO_FILENAME);

        try {
            $slug = $tm->importTheme($tmpPath, $name);
        } finally {
            @unlink($tmpPath);
        }

        return new Response(201, ['Content-Type' => 'application/json'],
            json_encode(['data' => ['name' => $slug]]));
    }

    /**
     * POST /codesh/grammars/import — Import a custom grammar JSON file.
     */
    public function importGrammar(ServerRequestInterface $request): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? $files['grammar_file'] ?? null;
        $clientFilename = null;

        // Fallback: PSR-7 may not parse multipart uploads — check $_FILES directly
        if (!$file && !empty($_FILES)) {
            $raw = $_FILES['file'] ?? $_FILES['grammar_file'] ?? null;
            if ($raw && $raw['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($raw['tmp_name']);
                $clientFilename = $raw['name'];
            }
        }

        if (!isset($content)) {
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                return new Response(400, ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'No file uploaded or upload error.']));
            }
            $content = (string) $file->getStream();
            $clientFilename = $file->getClientFilename();
        }

        $gm = $this->getGrammarManager();
        $json = json_decode($content, true);

        if (!$json || !isset($json['scopeName'])) {
            return new Response(400, ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Invalid grammar file. Must be a valid TextMate grammar JSON with scopeName.']));
        }

        // importGrammar expects a $_FILES-style array
        $tmpPath = tempnam(sys_get_temp_dir(), 'codesh_grammar_');
        file_put_contents($tmpPath, $content);

        try {
            $slug = $gm->importGrammar([
                'tmp_name' => $tmpPath,
                'name' => $clientFilename ?? 'grammar.json',
                'error' => UPLOAD_ERR_OK,
            ]);
        } finally {
            @unlink($tmpPath);
        }

        return new Response(201, ['Content-Type' => 'application/json'],
            json_encode(['data' => ['slug' => $slug]]));
    }

    /**
     * DELETE /codesh/themes/{name} — Delete a custom theme.
     */
    public function deleteTheme(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->getRouteParam($request, 'name');
        $tm = $this->getThemeManager();

        if (!$tm->deleteTheme($name)) {
            return new Response(404, ['Content-Type' => 'application/json'],
                json_encode(['error' => "Theme '{$name}' not found or cannot be deleted."]));
        }

        return new Response(204);
    }

    /**
     * DELETE /codesh/grammars/{slug} — Delete a custom grammar.
     */
    public function deleteGrammar(ServerRequestInterface $request): ResponseInterface
    {
        $slug = $this->getRouteParam($request, 'slug');
        $gm = $this->getGrammarManager();

        if (!$gm->deleteGrammar($slug)) {
            return new Response(404, ['Content-Type' => 'application/json'],
                json_encode(['error' => "Grammar '{$slug}' not found or cannot be deleted."]));
        }

        return new Response(204);
    }

    private function getRouteParam(ServerRequestInterface $request, string $name): string
    {
        $params = $request->getAttribute('route_params', []);
        return $params[$name] ?? '';
    }

    private function json(array $data): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['data' => $data]),
        );
    }
}
