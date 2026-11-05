<?php

namespace Grav\Plugin\Codesh;

use Phiki\Exceptions\UnrecognisedGrammarException;
use Phiki\Output\Html\PendingHtmlOutput;
use Phiki\Phiki;

/**
 * Resolves the language a code block names to a grammar Phiki ships, and
 * highlights with it. A language Phiki doesn't know is not an error: the block
 * renders as plain text with the normal header and copy button, keeping the
 * language label it was written with.
 */
class Highlighter
{
    /** Common names people write in fences that Phiki knows by another name. */
    private const ALIASES = [
        'apacheconfig' => 'apache',
        'htaccess' => 'apache',
        'html.twig' => 'twig',
        'php-template' => 'php',
        'env' => 'dotenv',
        'mkd' => 'markdown',
        'cli' => 'shellscript',
        'terminal' => 'shellsession',
    ];

    public static function grammarFor(string $lang): string
    {
        $lang = strtolower(trim($lang));

        return self::ALIASES[$lang] ?? $lang;
    }

    /**
     * @param string|array $theme
     */
    public static function codeToHtml(Phiki $phiki, string $code, string $lang, $theme): PendingHtmlOutput
    {
        try {
            return $phiki->codeToHtml($code, self::grammarFor($lang), $theme);
        } catch (UnrecognisedGrammarException) {
            return $phiki->codeToHtml($code, 'txt', $theme);
        }
    }
}
