<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;

/**
 * Currency formatting helper.
 *
 * Reads symbol, position, and separator settings from SystemConfig
 * (epic #8459 — currency localisation). US-dollar defaults come from the
 * ConfigItem registrations in SystemConfig::buildConfigs().
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
    /** Configured currency symbol (e.g. "$", "€", "£", "CHF"). */
    public static function symbol(): string
    {
        return SystemConfig::getValue('sCurrencySymbol');
    }

    /** "before" (symbol precedes amount) or "after" (symbol follows amount). */
    public static function position(): string
    {
        $pos = SystemConfig::getValue('sCurrencyPosition');
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
        $thousands = SystemConfig::getValue('sThousandsSeparator');
        $decimal   = SystemConfig::getValue('sDecimalSeparator');
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
            'thousand' => SystemConfig::getValue('sThousandsSeparator'),
            'decimal'  => SystemConfig::getValue('sDecimalSeparator'),
        ];
    }
}
