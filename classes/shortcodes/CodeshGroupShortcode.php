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

        // Use regex to extract codesh blocks and their data
        $pattern = '/<div class="codesh-block([^"]*)"[^>]*data-language="([^"]*)"[^>]*>(.*?)<\/div>(?=\s*(?:<div class="codesh-block|$))/s';

        if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            // No codesh blocks found, return content as-is
            return $content;
        }

        $tabs = [];
        $panels = [];
        $groupId = 'cg-' . substr(md5(uniqid()), 0, 8);

        foreach ($matches as $index => $match) {
            $classes = $match[1];
            $lang = $match[2];
            $blockHtml = $match[0];

            // Extract title from the block if present
            $title = $lang; // Default to language
            if (preg_match('/<span class="codesh-title">([^<]+)<\/span>/', $blockHtml, $titleMatch)) {
                $title = $titleMatch[1];
            } elseif (preg_match('/<span class="codesh-lang">([^<]+)<\/span>/', $blockHtml, $langMatch)) {
                $title = $langMatch[1];
            }

            // Extract just the code part (remove the existing header)
            $codeHtml = $blockHtml;
            // Remove the header div if present
            $codeHtml = preg_replace('/<div class="codesh-header">.*?<\/div>/s', '', $codeHtml);

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
                'html' => $codeHtml,
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
            // Modify the panel HTML to add the panel wrapper and remove outer div classes
            $panelHtml = $panel['html'];
            // Replace the outer codesh-block div with our panel div
            $panelHtml = preg_replace(
                '/<div class="codesh-block[^"]*"/',
                '<div class="codesh-group-panel' . $activeClass . '" data-panel="' . $panel['id'] . '"',
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
