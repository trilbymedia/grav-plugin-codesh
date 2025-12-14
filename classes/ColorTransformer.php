<?php

namespace Grav\Plugin\Codesh;

/**
 * Color transformation utilities for theme generation
 */
class ColorTransformer
{
    /**
     * Parse a hex color into RGB components
     */
    public function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Convert RGB to hex
     */
    public function rgbToHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02X%02X%02X',
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        );
    }

    /**
     * Convert hex to HSL
     */
    public function hexToHsl(string $hex): array
    {
        $rgb = $this->hexToRgb($hex);

        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        $h = 0;
        $s = 0;
        $l = ($max + $min) / 2;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r:
                    $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                    break;
                case $g:
                    $h = (($b - $r) / $d + 2) / 6;
                    break;
                case $b:
                    $h = (($r - $g) / $d + 4) / 6;
                    break;
            }
        }

        return [
            'h' => $h * 360,
            's' => $s * 100,
            'l' => $l * 100
        ];
    }

    /**
     * Convert HSL to hex
     */
    public function hslToHex(float $h, float $s, float $l): string
    {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1/3);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1/3);
        }

        return $this->rgbToHex(
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255)
        );
    }

    /**
     * Helper for HSL to RGB conversion
     */
    protected function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    /**
     * Lighten a color by percentage
     */
    public function lighten(string $hex, int $percent): string
    {
        $hsl = $this->hexToHsl($hex);
        $hsl['l'] = min(100, $hsl['l'] + $percent);
        return $this->hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    /**
     * Darken a color by percentage
     */
    public function darken(string $hex, int $percent): string
    {
        $hsl = $this->hexToHsl($hex);
        $hsl['l'] = max(0, $hsl['l'] - $percent);
        return $this->hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    /**
     * Adjust brightness (positive = lighten, negative = darken)
     */
    public function adjustBrightness(string $hex, int $percent): string
    {
        if ($percent >= 0) {
            return $this->lighten($hex, $percent);
        }
        return $this->darken($hex, abs($percent));
    }

    /**
     * Desaturate a color by percentage
     */
    public function desaturate(string $hex, int $percent): string
    {
        $hsl = $this->hexToHsl($hex);
        $hsl['s'] = max(0, $hsl['s'] - $percent);
        return $this->hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    /**
     * Saturate a color by percentage
     */
    public function saturate(string $hex, int $percent): string
    {
        $hsl = $this->hexToHsl($hex);
        $hsl['s'] = min(100, $hsl['s'] + $percent);
        return $this->hslToHex($hsl['h'], $hsl['s'], $hsl['l']);
    }

    /**
     * Add alpha channel to a color (returns hex with alpha)
     */
    public function alpha(string $hex, float $opacity): string
    {
        $hex = ltrim($hex, '#');

        // If already has alpha, strip it
        if (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6);
        }

        $alpha = str_pad(dechex((int) round($opacity * 255)), 2, '0', STR_PAD_LEFT);
        return '#' . $hex . $alpha;
    }

    /**
     * Mix two colors together
     */
    public function mix(string $hex1, string $hex2, int $weight = 50): string
    {
        $rgb1 = $this->hexToRgb($hex1);
        $rgb2 = $this->hexToRgb($hex2);

        $w = $weight / 100;

        $r = (int) round($rgb1['r'] * $w + $rgb2['r'] * (1 - $w));
        $g = (int) round($rgb1['g'] * $w + $rgb2['g'] * (1 - $w));
        $b = (int) round($rgb1['b'] * $w + $rgb2['b'] * (1 - $w));

        return $this->rgbToHex($r, $g, $b);
    }

    /**
     * Get the luminance of a color (0-1)
     */
    public function getLuminance(string $hex): float
    {
        $rgb = $this->hexToRgb($hex);

        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Get contrast ratio between two colors
     */
    public function getContrastRatio(string $hex1, string $hex2): float
    {
        $l1 = $this->getLuminance($hex1);
        $l2 = $this->getLuminance($hex2);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Check if a color is light
     */
    public function isLight(string $hex): bool
    {
        return $this->getLuminance($hex) > 0.5;
    }

    /**
     * Check if a color is dark
     */
    public function isDark(string $hex): bool
    {
        return $this->getLuminance($hex) <= 0.5;
    }

    /**
     * Get appropriate text color (black or white) for a background
     */
    public function getContrastingTextColor(string $backgroundColor): string
    {
        return $this->isLight($backgroundColor) ? '#000000' : '#FFFFFF';
    }

    /**
     * Validate a hex color
     */
    public function isValidHex(string $hex): bool
    {
        return (bool) preg_match('/^#?[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $hex);
    }

    /**
     * Normalize a hex color (ensure # prefix, uppercase)
     */
    public function normalizeHex(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . strtoupper($hex);
    }

    /**
     * Apply multiple transformations at once
     */
    public function transform(string $hex, array $transforms): string
    {
        $result = $hex;

        foreach ($transforms as $method => $value) {
            switch ($method) {
                case 'lighten':
                    $result = $this->lighten($result, $value);
                    break;
                case 'darken':
                    $result = $this->darken($result, $value);
                    break;
                case 'saturate':
                    $result = $this->saturate($result, $value);
                    break;
                case 'desaturate':
                    $result = $this->desaturate($result, $value);
                    break;
                case 'alpha':
                    $result = $this->alpha($result, $value);
                    break;
            }
        }

        return $result;
    }
}
