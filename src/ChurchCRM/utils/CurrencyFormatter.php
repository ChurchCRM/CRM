<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * Currency formatting helper.
 *
 * Reads symbol, position, and separator settings from SystemConfig when available
 * (epic #8459 — currency localisation). Falls back to US-dollar defaults when
 * the config keys have not yet been registered, so the class is safe to use on
 * any 7.x installation.
 *
 * Usage:
 *   CurrencyFormatter::format(1234.5)        // "$1,234.50"
 *   CurrencyFormatter::format(1234.5, 0)     // "$1,235"
 *   CurrencyFormatter::symbol()              // "$"
 *   CurrencyFormatter::position()            // "before" | "after"
 *   CurrencyFormatter::toArray()             // ['symbol' => ..., 'position' => ..., ...]
 */
class CurrencyFormatter
{
    /**
     * Safely read a SystemConfig value with a hard fallback for keys that may
     * not yet be registered (pre-epic-#8459 installs).
     */
    private static function getSetting(string $key, string $default): string
    {
        try {
            $val = SystemConfig::getValue($key);
            return ($val !== null && $val !== '') ? (string) $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /** Configured currency symbol (e.g. "$", "€", "£", "CHF"). */
    public static function symbol(): string
    {
        return self::getSetting('sCurrencySymbol', '$');
    }

    /** "before" (symbol precedes amount) or "after" (symbol follows amount). */
    public static function position(): string
    {
        $pos = self::getSetting('sCurrencyPosition', 'before');
        return in_array($pos, ['before', 'after'], true) ? $pos : 'before';
    }

    /**
     * Format a monetary amount and return the result HTML-escaped for direct `<?= ?>` output.
     * Use this in PHP templates; use format() in PHP code that further processes the string
     * (e.g. API payload building, PDF generation).
     *
     * Accepts null (e.g. from nullable Propel DECIMAL getters) and returns an empty string
     * so callers do not need to pre-cast to float and risk turning NULL into $0.00.
     */
    public static function formatHtml(?float $amount, int $decimals = 2): string
    {
        if ($amount === null) {
            return '';
        }
        return htmlspecialchars(self::format($amount, $decimals), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format a monetary amount using the configured symbol, position, and
     * thousands/decimal separators.
     *
     * A non-breaking space (\u{00A0}) separates the symbol from the number to
     * prevent line-wrapping on narrow screens.
     *
     * @param  float $amount   The raw numeric value.
     * @param  int   $decimals Number of decimal places (default 2).
     * @return string          The formatted currency string (e.g. "$1,234.50").
     */
    public static function format(float $amount, int $decimals = 2): string
    {
        $thousands = self::getSetting('sThousandsSeparator', ',');
        $decimal   = self::getSetting('sDecimalSeparator', '.');
        $symbol    = self::symbol();
        $position  = self::position();

        $formatted = number_format($amount, $decimals, $decimal, $thousands);

        return $position === 'after'
            ? $formatted . "\u{00A0}" . $symbol
            : $symbol . "\u{00A0}" . $formatted;
    }

    /**
     * Return currency configuration as a plain array, suitable for JSON encoding
     * into `window.CRM.currency` (see currency-localization skill).
     *
     * @return array{symbol: string, position: string, thousand: string, decimal: string}
     */
    public static function toArray(): array
    {
        return [
            'symbol'   => self::symbol(),
            'position' => self::position(),
            'thousand' => self::getSetting('sThousandsSeparator', ','),
            'decimal'  => self::getSetting('sDecimalSeparator', '.'),
        ];
    }
}
