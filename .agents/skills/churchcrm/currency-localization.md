---
title: "Currency Localization"
intent: "Render money values with a configurable symbol, position, and separators across PHP, JS, DataTables, Chart.js, CSS, and PDFs"
tags: ["i18n", "localization", "currency", "money", "finance", "systemconfig"]
prereqs: ["[[configuration-management]]", "[[i18n-localization]]", "[[frontend-development]]"]
complexity: "beginner"
---

# Currency Localization

Monetary values display in the church's own currency: symbol, position (before/after the amount) and thousands/decimal separators, backed by four `SystemConfig` keys.

## Key Principle

Route every money value through one of the helpers below. Every finance view, API payload, DataTable column, chart and PDF reads the configured symbol at render time.

---

## Config Keys

The four keys are registered in `src/ChurchCRM/dto/SystemConfig.php` under `'Financial Settings'`.

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `sCurrencySymbol` | text | `$` | Symbol or code (`€`, `£`, `CHF`, `CAD $`, `R$`) |
| `sCurrencyPosition` | choice | `before` | `before` (`$100.00`) or `after` (`100,00 €`) |
| `sThousandsSeparator` | text | `,` | Thousands grouping character |
| `sDecimalSeparator` | text | `.` | Decimal character |

```php
SystemConfig::getValue('sCurrencySymbol');
```

Defaults live in the `ConfigItem` registrations in `SystemConfig::buildConfigs()`. A missing `config_cfg` row resolves to the code default, and `ConfigItem::setValue()` deletes the row when a value returns to its default. Seed no rows for these keys in `.sql` files. Call `SystemConfig::getValue()` directly; for these four keys it returns a string, so call sites need no `(string)` cast.

Symbol input is free-form; multi-character symbols are valid.

### Separator fields in settings handlers

`InputUtils::sanitizeText()` is `strip_tags(trim($input))`, and `trim` removes a space separator (valid in French, Swiss and Swedish locales). Leave `sThousandsSeparator` and `sDecimalSeparator` out of any `InputSanitizationMiddleware` field map and cap them in the handler:

```php
$thousands = mb_substr((string) ($body['sThousandsSeparator'] ?? ''), 0, 1);
```

The middleware's field types are `'text'` and `'html'`. Whitelist choice values with `in_array()` in the handler.

---

## PHP: `CurrencyFormatter`

`src/ChurchCRM/Utils/CurrencyFormatter.php`

```php
use ChurchCRM\Utils\CurrencyFormatter;

CurrencyFormatter::formatHtml(1234.5);    // "$1,234.50" HTML-escaped; for <?= ?> in templates
CurrencyFormatter::formatHtml("1234.50"); // numeric strings work (Propel DECIMAL/SUM returns strings)
CurrencyFormatter::formatHtml(null);      // ""  null in, empty string out
CurrencyFormatter::formatHtml("N/A");     // ""  non-numeric string returns '' and logs a warning
CurrencyFormatter::format(1234.5);        // "$1,234.50" raw string; for APIs, PDFs, string building
CurrencyFormatter::format(1234.5, 0);     // "$1,235"
CurrencyFormatter::formatForPdf(1234.5);  // ISO-8859-1; amount-only FPDF cells
CurrencyFormatter::symbol();              // "$"
CurrencyFormatter::position();            // "before"
CurrencyFormatter::toArray();             // symbol, position, separators for JSON
```

| Context | Method |
|---------|--------|
| PHP template `<?= ?>` output | `formatHtml()` |
| API payload / PDF / string building | `format()` |
| JS (DataTables, Chart.js, DOM) | `window.CRM.currency.format()` |

Output uses a non-breaking space (`\u{00A0}`) between symbol and amount so narrow screens do not wrap.

```php
<div class="fw-medium"><?= CurrencyFormatter::formatHtml($amount) ?></div>
```

### Pass raw values

`formatHtml()` and `format()` take `float|string|null` and validate internally, so null and garbage render as an empty string rather than a believable `$0.00`. Pass the raw Propel/mysqli value. Use `?? 0` only where a blank should display as zero (an unguarded SUM total):

```php
<?= CurrencyFormatter::formatHtml($pledge['pledge_amount']) ?>
<?= CurrencyFormatter::formatHtml($deposit->getVirtualColumn('totalAmount') ?? 0) ?>
```

---

## JS: `window.CRM.currency`

`src/Include/Header.php` injects it from `CurrencyFormatter::toArray()` with `json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)`, so symbols containing `<`, `>`, `&`, `'` or `"` stay inert inside the inline script.

```js
window.CRM.currency.symbol;           // "$"
window.CRM.currency.position;         // "before" | "after"
window.CRM.currency.thousand;         // ","
window.CRM.currency.decimal;          // "."
window.CRM.currency.format(1234.5)    // "$1,234.50"
window.CRM.currency.format(1234.5, 0) // "$1,235"
```

### DataTables column

```js
{
  data: 'amount',
  render: (data) => window.CRM.currency.format(data),
  className: 'text-end'
}
```

When the API returns raw and formatted values, display the formatted string and sort by the raw column:

```js
{ data: 'amount_formatted', orderData: [colIdxOfRawAmount] }
```

### Chart.js

Chart.js draws into `<canvas>`, so ticks and tooltips call the JS helper:

```js
options: {
  scales: { y: { ticks: { callback: (value) => window.CRM.currency.format(value) } } },
  plugins: { tooltip: { callbacks: { label: (ctx) => window.CRM.currency.format(ctx.parsed.y) } } }
}
```

---

## CSS: `.money`

`src/Include/Header.php` sets `--currency-symbol` (the raw symbol) and `data-currency-position` (`before` | `after`) on `<html>`. The `.money` utility in `src/skin/scss/_utility-classes.scss` renders them around a number:

```html
<span class="money">1.234,50</span>
<!-- "after" position with European separators: 1.234,50 € -->
```

The number inside already carries the configured separators (from `CurrencyFormatter::format()` or `window.CRM.currency.format()`); CSS swaps only symbol and position. Server-rendered views use `CurrencyFormatter::formatHtml()`; `.money` fits pre-formatted values from an API or static templates.

---

## API Contract: raw number + `*_formatted` string

Every money field in a JSON payload has two siblings:

```json
{ "amount": 1234.50, "amount_formatted": "$1,234.50" }
```

```php
use ChurchCRM\Slim\Utils\SlimUtils;

$amount = (float) $deposit->getAmount();

return SlimUtils::renderJSON($response, [
    'amount'           => $amount,
    'amount_formatted' => CurrencyFormatter::format($amount),
]);
```

The raw number keeps sorting and arithmetic type-safe for clients.

---

## PDF Reports

TCPDF column widths are fixed, so wider symbols (`CHF`, `CAD $`) can overflow.

- Read symbol, position and separators via `CurrencyFormatter::toArray()` at the top of the report
- Measure column widths with `GetStringWidth()` on a formatted sample row and widen the column when needed
- Right-align money columns

### FPDF Latin-1: convert exactly once

FPDF core fonts render ISO-8859-1, so UTF-8 output (the formatter's `\u{00A0}`, `€`, `£`) is transcoded once per string:

```php
// Amount-only cell: formatForPdf() formats and transcodes
$pdf->Cell(25, $h, CurrencyFormatter::formatForPdf($total), 0, 0, 'R');

// Amount inside UTF-8 text: plain format(), then convert the whole string once
$pdf->Cell(176, $h, ChurchInfoReport::convertToLatin1(
    "$fundName Total:   " . CurrencyFormatter::format($total)), 0, 0, 'R');

// printRightJustified()/writeAt()/writeAtCell() convert internally: pass plain format() output
$pdf->printRightJustified($x, $y, CurrencyFormatter::format($total));
```

---

## Multi-Char Symbols

- Tight table columns: `white-space: nowrap` + `min-width`, or move the symbol into the column header
- Chart.js axis: truncate with a custom tick callback when `symbol.length > 3`
- Input groups with a prepended symbol (`input-group-text`): widen the prepend
- Deposit slip editor: amounts sit in narrow cells, so test with `CHF` and `CAD $`

---

## Checklist (finance-adjacent PRs)

- [ ] Every number-preceding `$` is replaced by `CurrencyFormatter::formatHtml()` / `format()` (PHP) or `window.CRM.currency.format()` (JS)
- [ ] JSON payloads with money carry a `*_formatted` sibling
- [ ] DataTable money columns use `render:` with the JS helper
- [ ] Chart.js axis and tooltip callbacks use the JS helper
- [ ] PR screenshots show the page with `$` (default) and with `€` after
- [ ] Cypress smoke: change config, reload, value reflects

## Verification

1. Admin → System Settings → Financial Settings
2. Set `sCurrencySymbol` = `€`, `sCurrencyPosition` = `after`, `sThousandsSeparator` = `.`, `sDecimalSeparator` = `,`
3. Reload the page: values display as `1.234,56 €` with a non-breaking space
4. Reset to `$`, `before`, `,`, `.`
