<?php

/**
 * FormatHelper
 *
 * Collection of static utility methods for formatting and rendering
 * disk data values in the view templates.
 *
 * All methods are pure functions with no side effects.
 *
 * @package DSKscan\Helper
 */
class FormatHelper
{
    /**
     * Formats a byte count as a human-readable size string using localised unit labels.
     *
     * @param  int    $b     Number of bytes
     * @param  array  $t     Translation array (must contain unit_mo, unit_ko, unit_o keys)
     * @return string        Formatted string, e.g. "1.44 Mo", "512.00 KB", "128 B"
     */
    public static function bytes(int $b, array $t = []): string
    {
        $mo = $t['unit_mo'] ?? 'Mo';
        $ko = $t['unit_ko'] ?? 'Ko';
        $o  = $t['unit_o']  ?? 'o';

        if ($b >= 1024 * 1024) return number_format($b / 1024 / 1024, 2) . ' ' . $mo;
        if ($b >= 1024)        return number_format($b / 1024, 2) . ' ' . $ko;
        return $b . ' ' . $o;
    }

    /**
     * Formats an integer as an uppercase two-digit hexadecimal value prefixed with "#".
     *
     * @param  int    $v Integer value (0–255 typical)
     * @return string    Formatted string, e.g. "#C1", "#FF"
     */
    public static function hex(int $v): string
    {
        return '#' . strtoupper(str_pad(dechex($v), 2, '0', STR_PAD_LEFT));
    }

    /**
     * Determines the CSS class name for a sector block based on its status flags.
     * Classes map directly to colour rules defined in style.css.
     *
     * Priority order (highest to lowest):
     *   weak + erased + empty → "weak-erased-empty"
     *   weak + erased         → "weak-erased"
     *   weak + empty          → "weak-empty"
     *   weak                  → "weak"
     *   incomplete            → "incomplete"
     *   erased + empty        → "erased-empty"
     *   erased                → "erased-used"
     *   used                  → "normal-used"
     *   (default)             → "normal-empty"
     *
     * @param  array  $s Sector entry array (must contain isWeak, isErased, isUsed, isIncomplete)
     * @return string    CSS class name
     */
    public static function sectorCssClass(array $s): string
    {
        if ($s['isWeak'] && $s['isErased'] && !$s['isUsed']) return 'weak-erased-empty';
        if ($s['isWeak'] && $s['isErased'])                  return 'weak-erased';
        if ($s['isWeak'] && !$s['isUsed'])                   return 'weak-empty';
        if ($s['isWeak'])                                     return 'weak';
        if ($s['isIncomplete'])                               return 'incomplete';
        if ($s['isErased'] && !$s['isUsed'])                 return 'erased-empty';
        if ($s['isErased'])                                   return 'erased-used';
        if ($s['isUsed'])                                     return 'normal-used';
        return 'normal-empty';
    }

    /**
     * Builds a human-readable tooltip string for a sector block (used in the MAP tab).
     *
     * Format: "T{track} Sector #{R} — {size} {unit} [{status}] [{flags}]"
     *
     * @param  array  $s Sector entry array
     * @param  array  $t Translation array
     * @return string    Tooltip string
     */
    public static function sectorTooltip(array $s, array $t = []): string
    {
        $size   = 128 << $s['N'];
        $unit   = $t['unit_o'] ?? 'o';
        $status = $s['isUsed'] ? ($t['flag_used'] ?? 'USED') : ($t['unit_o'] ?? 'empty');
        $extra  = [];
        if ($s['isWeak'])       $extra[] = $t['flag_weak']       ?? 'WEAK';
        if ($s['isErased'])     $extra[] = $t['flag_erased']     ?? 'ERASED';
        if ($s['isIncomplete']) $extra[] = $t['flag_incomplete']  ?? 'INCOMPLETE';
        $flags  = $extra ? ' [' . implode('+', $extra) . ']' : '';
        return 'T' . $s['track'] . ' S#' . self::hex($s['R']) . ' — ' . $size . ' ' . $unit . ' [' . $status . ']' . $flags;
    }

    /**
     * Formats a FDC status register byte as an 8-bit binary string.
     *
     * @param  int    $v Status register byte value (0–255)
     * @return string    8-character binary string, e.g. "01000000"
     */
    public static function fdcBinary(int $v): string
    {
        return str_pad(decbin($v), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Renders an HTML badge element conditionally.
     * Returns a coloured badge if the condition is true, otherwise a neutral badge.
     *
     * @param  bool   $condition Whether to show the "yes" badge
     * @param  string $labelYes  Label text when condition is true
     * @param  string $classYes  CSS modifier class suffix (e.g. "ro", "weak", "erased")
     * @param  string $labelNo   Label text when condition is false (default "-")
     * @return string            HTML string for the badge element
     */
    public static function badge(bool $condition, string $labelYes, string $classYes, string $labelNo = '-'): string
    {
        if ($condition) {
            return '<span class="badge badge-' . $classYes . '">' . $labelYes . '</span>';
        }
        return '<span class="badge badge-no">' . $labelNo . '</span>';
    }
}
