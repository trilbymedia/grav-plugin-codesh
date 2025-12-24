<?php

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class CodeshGroupShortcode extends Shortcode
{
    public function init(): void
    {
        $this->shortcode->getHandlers()->add('codesh-group', function (ShortcodeInterface $sc) {
            return $this->process($sc);
        });
    }

    protected function process(ShortcodeInterface $sc): string
    {
        $content = $sc->getContent() ?? '';
        $syncKey = $sc->getParameter('sync', '');

        // The inner [codesh] shortcodes have already been processed
        // We need to find all .codesh-block elements and convert them to tabs

        // Use DOMDocument for proper HTML parsing
        $dom = new \DOMDocument();
        // Suppress warnings for HTML5 elements and load with proper encoding
        @$dom->loadHTML('<?xml encoding="UTF-8"><div id="codesh-wrapper">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);
        $blocks = $xpath->query("//div[contains(@class, 'codesh-block')]");

        if ($blocks->length === 0) {
            // No codesh blocks found, return content as-is
            return $content;
        }

        $tabs = [];
        $panels = [];
        $groupId = 'cg-' . substr(md5(uniqid()), 0, 8);

        foreach ($blocks as $index => $block) {
            $lang = $block->getAttribute('data-language') ?: 'txt';

            // Extract title from the block if present
            $title = strtoupper($lang); // Default to language
            $titleEl = $xpath->query(".//span[contains(@class, 'codesh-title')]", $block)->item(0);
            $langEl = $xpath->query(".//span[contains(@class, 'codesh-lang')]", $block)->item(0);

            if ($titleEl) {
                $title = $titleEl->textContent;
            } elseif ($langEl && !empty(trim($langEl->textContent))) {
                $title = $langEl->textContent;
            }

            // Remove the header from the block
            $header = $xpath->query(".//div[contains(@class, 'codesh-header')]", $block)->item(0);
            if ($header) {
                $header->parentNode->removeChild($header);
            }

            // Get the modified block HTML
            $blockHtml = $dom->saveHTML($block);

            $isActive = $index === 0;
            $tabId = $groupId . '-' . $index;

            $tabs[] = [
                'id' => $tabId,
                'title' => $title,
                'lang' => $lang,
                'active' => $isActive,
            ];

            $panels[] = [
                'id' => $tabId,
                'html' => $blockHtml,
                'active' => $isActive,
            ];
        }

        // Build the output HTML
        $syncAttr = $syncKey ? ' data-sync="' . htmlspecialchars($syncKey) . '"' : '';
        $output = '<div class="codesh-group" data-group-id="' . $groupId . '"' . $syncAttr . '>';

        // Tab header
        $output .= '<div class="codesh-group-header">';
        $output .= '<div class="codesh-group-tabs">';
        foreach ($tabs as $tab) {
            $activeClass = $tab['active'] ? ' active' : '';
            $output .= '<button class="codesh-group-tab' . $activeClass . '" ';
            $output .= 'data-tab="' . $tab['id'] . '" ';
            $output .= 'data-lang="' . htmlspecialchars($tab['lang']) . '">';
            $output .= htmlspecialchars($tab['title']);
            $output .= '</button>';
        }
        $output .= '</div>';

        // Copy button
        $output .= '<button class="codesh-copy" type="button" title="Copy code">';
        $output .= '<svg class="codesh-copy-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">';
        $output .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
        $output .= '</svg>';
        $output .= '<span class="codesh-copy-text">Copy</span>';
        $output .= '</button>';

        $output .= '</div>';

        // Tab panels
        $output .= '<div class="codesh-group-panels">';
        foreach ($panels as $panel) {
            $activeClass = $panel['active'] ? ' active' : '';
            // Modify the panel HTML to add the panel wrapper while preserving codesh-block class
            $panelHtml = $panel['html'];
            // Add codesh-group-panel class to the outer div while keeping codesh-block for styling
            $panelHtml = preg_replace(
                '/<div class="codesh-block([^"]*)"/',
                '<div class="codesh-group-panel codesh-block$1' . $activeClass . '" data-panel="' . $panel['id'] . '"',
                $panelHtml,
                1
            );
            $output .= $panelHtml;
        }
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }
}
