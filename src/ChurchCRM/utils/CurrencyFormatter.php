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
     */
    public static function formatHtml(float|string|null $amount, int $decimals = 2): string
    {
        return htmlspecialchars(self::format($amount, $decimals), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format a monetary amount using the configured symbol, position, and
     * thousands/decimal separators.
     *
     * Accepts null and numeric strings (Propel returns DECIMAL columns as string|null) so
     * callers do not need to pre-cast — a blind (float) cast would turn NULL and garbage
     * into $0.00. Null, empty, and non-numeric values render as an empty string; the
     * non-numeric case is logged.
     *
     * A non-breaking space (\u{00A0}) separates the symbol from the number to
     * prevent line-wrapping on narrow screens.
     *
     * @param  float|string|null $amount   The raw numeric value.
     * @param  int               $decimals Number of decimal places (default 2).
     * @return string            The formatted currency string (e.g. "$1,234.50"), or '' for null/empty/non-numeric input.
     */
    public static function format(float|string|null $amount, int $decimals = 2): string
    {
        $value = self::normalize($amount);
        if ($value === null) {
            return '';
        }

        $thousands = SystemConfig::getValue('sThousandsSeparator');
        $decimal   = SystemConfig::getValue('sDecimalSeparator');
        $symbol    = self::symbol();
        $position  = self::position();

        $formatted = number_format($value, $decimals, $decimal, $thousands);

        return $position === 'after'
            ? $formatted . "\u{00A0}" . $symbol
            : $symbol . "\u{00A0}" . $formatted;
    }

    /**
     * Format a monetary amount for FPDF output. FPDF core fonts only render
     * ISO-8859-1, so the result is transcoded from UTF-8 to Latin-1.
     *
     * Use only for amount-only PDF cells. When the amount is concatenated with
     * other UTF-8 text (names, labels), use format() and convert the whole
     * string once via ChurchInfoReport::convertToLatin1() — converting twice
     * corrupts the bytes.
     */
    public static function formatForPdf(float|string|null $amount, int $decimals = 2): string
    {
        return self::toLatin1(self::format($amount, $decimals));
    }

    /** UTF-8 → ISO-8859-1 for FPDF; transliterates or drops unmappable characters. */
    private static function toLatin1(string $str): string
    {
        if (function_exists('iconv')) {
            $result = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $str);
            if ($result !== false) {
                return $result;
            }
        }
        $result = mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        return is_string($result) ? $result : $str;
    }

    /** Validate a raw amount; null means "render nothing" (null/empty/non-numeric input). */
    private static function normalize(float|string|null $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        if (is_string($amount) && !is_numeric($amount)) {
            LoggerUtils::getAppLogger()->warning('Non-numeric amount passed to CurrencyFormatter', ['amount' => $amount]);
            return null;
        }
        return (float) $amount;
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
