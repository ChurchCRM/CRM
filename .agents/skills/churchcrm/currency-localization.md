---
title: "Currency Localization"
intent: "Render money values with a configurable symbol, position, and separators across PHP, JS, DataTables, Chart.js, CSS, and PDFs"
tags: ["i18n", "localization", "currency", "money", "finance", "systemconfig"]
prereqs: ["[[configuration-management]]", "[[i18n-localization]]", "[[frontend-development]]"]
complexity: "beginner"
---

# Currency Localization

ChurchCRM has historically hard-coded the US dollar sign `$` across the finance
module. This skill is the single source of truth for **displaying monetary
values in a church's own currency** — symbol, position (before/after amount),
and thousands/decimal separators — backed by four `SystemConfig` keys.

**Epic**: [#8459 — Currency Localization](https://github.com/ChurchCRM/CRM/issues/8459)

---

## Key Principle

**Never hard-code `$` next to a money value.** Always route through one of
the helpers documented here. Every new finance view, API payload, DataTable
column, chart, or PDF must read the configured symbol at render time.

---

## Config Keys (in `Financial Settings`)

All four keys live in `src/ChurchCRM/dto/SystemConfig.php` under the
`'Financial Settings'` category.

| Key | Type | Default | Purpose |
|-----|------|---------|---------|
| `sCurrencySymbol` | text | `$` | Symbol or code (e.g. `€`, `£`, `CHF`, `CAD $`, `R$`) |
| `sCurrencyPosition` | choice | `before` | `before` (`$100.00`) or `after` (`100,00 €`) |
| `sThousandsSeparator` | text | `,` | Thousands grouping character |
| `sDecimalSeparator` | text | `.` | Decimal character |

**Read values:**

```php
SystemConfig::getValue('sCurrencySymbol');       // "$"
SystemConfig::getValue('sCurrencyPosition');     // "before" | "after"
SystemConfig::getValue('sThousandsSeparator');   // ","
SystemConfig::getValue('sDecimalSeparator');     // "."
```

### Separator fields must bypass InputSanitizationMiddleware <!-- learned: 2026-07-18 -->

`InputUtils::sanitizeText()` is `strip_tags(trim($input))` — the **trim destroys a
space (U+0020) thousands separator** (valid in French/Swiss/Swedish locales) before
the route handler runs. Never map `sThousandsSeparator` / `sDecimalSeparator` in an
`InputSanitizationMiddleware` field map; the handler's `mb_substr($val, 0, 1)` cap
is the sanitizer for single-char separator fields.

```php
// ❌ WRONG — middleware trims " " to "" before the handler sees it
new InputSanitizationMiddleware(['sThousandsSeparator' => 'text'])

// ✅ CORRECT — omit separators from the map; cap in the handler
$thousands = mb_substr((string) ($body['sThousandsSeparator'] ?? ''), 0, 1);
```

Also: the middleware's only types are `'text'` and `'html'` — anything else silently
falls through to `sanitizeText()`, so `'choice'` is misleading; real whitelisting
happens via `in_array()` in the handler.

### Defaults live in code, not the DB <!-- learned: 2026-07-18 -->

The USD defaults come from the `ConfigItem` registrations in
`SystemConfig::buildConfigs()`. A missing `config_cfg` row simply resolves to
the code default, and `ConfigItem::setValue()` *deletes* the row when a value
is set back to the default — "default" is deliberately represented as "no row".

- **Never seed these keys with a `.sql` migration** — the app deletes such
  rows again and the migration adds noise for zero behavioral change.
- **No fallback wrapper needed** — call `SystemConfig::getValue()` directly.
  (`CurrencyFormatter` once had a try/catch `getSetting()` helper; it was
  removed because the keys ship in the same release as the class.)
- `getValue()` is untyped (`mixed`) because other keys have int/bool defaults;
  for these four keys the value is always a string — do **not** add `(string)`
  casts at call sites.

Free-form symbol input is allowed — multi-char symbols (`CHF`, `CAD $`) are
fine. Fix UI overflow issues reactively per view.

---

## PHP: `CurrencyFormatter`

Location: `src/ChurchCRM/Utils/CurrencyFormatter.php`

### API

```php
use ChurchCRM\Utils\CurrencyFormatter;

CurrencyFormatter::formatHtml(1234.5);    // "$1,234.50" — HTML-escaped, null-safe; for <?= ?> in templates
CurrencyFormatter::formatHtml("1234.50"); // "$1,234.50" — numeric strings OK (Propel DECIMAL/SUM returns string)
CurrencyFormatter::formatHtml(null);      // ""          — null in, empty string out (no bogus $0.00)
CurrencyFormatter::formatHtml("N/A");     // ""          — non-numeric string → '' + warning log (never $0.00)
CurrencyFormatter::format(1234.5);        // "$1,234.50" — raw string; for APIs, PDFs, further processing
CurrencyFormatter::format(1234.5, 0);     // "$1,235"
CurrencyFormatter::format(null);          // ""          — same float|string|null contract as formatHtml()
CurrencyFormatter::formatForPdf(1234.5);  // "$1,234.50" in ISO-8859-1 — amount-only FPDF cells ONLY
CurrencyFormatter::symbol();              // "$"
CurrencyFormatter::position();            // "before"
CurrencyFormatter::toArray();             // ['symbol' => ..., 'position' => ..., ...] for JSON
```

**Which method where:** <!-- learned: 2026-07-18 -->

| Context | Method |
|---------|--------|
| PHP template `<?= ?>` output | `formatHtml()` (escaped, accepts `float\|string\|null`) |
| API payload / PDF / string building | `format()` |
| JS (DataTables, Chart.js, DOM) | `window.CRM.currency.format()` |

Output uses a non-breaking space (`\u{00A0}`) between symbol and amount to
prevent line-wrapping on narrow screens.

### Use in views (replace hardcoded `$`)

```php
// ❌ WRONG — hardcoded $
<div class="fw-medium">$<?= number_format($amount, 2) ?></div>

// ✅ CORRECT — uses configured symbol/position/separators, HTML-escaped
<div class="fw-medium"><?= CurrencyFormatter::formatHtml($amount) ?></div>
```

Never concatenate the symbol yourself; the helper applies the configured
position and separators for you.

### Never pre-cast `(float)` before `formatHtml()` or `format()` <!-- learned: 2026-07-18 -->

`formatHtml()` takes `float|string|null` and validates internally. A caller-side
`(float)` cast silently launders `null`/garbage into `$0.00` on finance reports —
exactly what the signature is designed to prevent. Pass the raw value; use `?? 0`
only when a blank truly should display as zero (e.g. an unguarded SUM total).

```php
// ❌ WRONG — blind cast turns NULL/garbage into a legit-looking $0.00
<?= CurrencyFormatter::formatHtml((float) $deposit->getVirtualColumn('totalAmount')) ?>
<?= is_numeric($v) ? CurrencyFormatter::formatHtml((float) $v) : '' ?>  // redundant guard

// ✅ CORRECT — formatter handles string|null; '' for null/non-numeric (logged)
<?= CurrencyFormatter::formatHtml($pledge['pledge_amount']) ?>
<?= CurrencyFormatter::formatHtml($deposit->getVirtualColumn('totalAmount') ?? 0) ?>  // intentional $0.00
```

`format()` shares the same `float|string|null` contract (a private `normalize()`
does the validation once for both methods) — pass raw Propel/mysqli values to
either without casting. <!-- learned: 2026-07-19 -->

---

## JS: `window.CRM.currency`

Injected in `src/Include/Header.php` from `CurrencyFormatter::toArray()` using
`json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)`
so that symbols containing `<`, `>`, `&`, `'`, or `"` cannot break the inline
`<script>` block or enable XSS.

### API

```js
window.CRM.currency.symbol;           // "$"
window.CRM.currency.position;         // "before" | "after"
window.CRM.currency.thousand;         // ","
window.CRM.currency.decimal;          // "."
window.CRM.currency.format(1234.5)    // "$1,234.50"  (2 decimal places by default)
window.CRM.currency.format(1234.5, 0) // "$1,235"     (optional decimals parameter)
```

### DataTables column render

```js
// In a DataTable column that displays money:
{
  data: 'amount',
  render: (data) => window.CRM.currency.format(data),
  className: 'text-end'  // right-align money
}
```

When the API returns both raw and formatted (see "API contract" below),
prefer the formatted string for display and keep the raw number for sort/order:

```js
{
  data: 'amount_formatted',
  orderData: [colIdxOfRawAmount],
}
```

### Chart.js tooltip & axis callbacks

```js
options: {
  scales: {
    y: {
      ticks: {
        callback: (value) => window.CRM.currency.format(value)
      }
    }
  },
  plugins: {
    tooltip: {
      callbacks: {
        label: (ctx) => window.CRM.currency.format(ctx.parsed.y)
      }
    }
  }
}
```

Chart.js renders inside `<canvas>` — the CSS `::before` trick doesn't work
there. Always use `window.CRM.currency.format()`.

---

## CSS: `--currency-symbol` custom property

`src/Include/Header.php` sets **two** things on `<html>` from `SystemConfig`:

1. `--currency-symbol` CSS custom property — the raw symbol string.
2. `data-currency-position` HTML attribute — `"before"` or `"after"`.

This lets pure-CSS rendering pick up the symbol and its position without any
JS. Both are emitted at the same time as the `window.CRM.currency` injection.

### `.money` utility

Defined in `src/skin/scss/_utility-classes.scss`:

```scss
.money {
  white-space: nowrap;

  &::before {
    content: var(--currency-symbol) "\00a0";
  }
}

[data-currency-position="after"] .money {
  &::before { content: none; }
  &::after  { content: "\00a0" var(--currency-symbol); }
}
```

### Use when you control the markup

```html
<!-- The inner number MUST already be formatted with the configured
     thousands/decimal separators (e.g. via CurrencyFormatter::format()
     or window.CRM.currency.format()). CSS only swaps symbol & position —
     it does NOT reformat separators. -->
<span class="money">1.234,50</span>
<!-- Renders: 1.234,50 € (for "after" position with European separators) -->
```

Prefer `CurrencyFormatter::format()` for server-rendered views — use `.money`
only when the number is already separator-formatted (e.g., a pre-formatted
value from the API, or static display templates where you want pure-CSS
symbol substitution).

---

## API Contract: raw number + `*_formatted` string

**Every money field in a JSON payload must return two siblings:**

```json
{
  "amount": 1234.50,
  "amount_formatted": "$1,234.50",
  "total": 5000.00,
  "total_formatted": "$5,000.00"
}
```

This is backwards-compatible: existing consumers parsing numbers keep working,
and simple consumers (DataTables, dashboards) can display the formatted
string without formatting logic.

### PHP pattern

```php
use ChurchCRM\Slim\Utils\SlimUtils;

$amount = (float) $deposit->getAmount();

return SlimUtils::renderJSON($response, [
    'amount'           => $amount,
    'amount_formatted' => CurrencyFormatter::format($amount),
]);
```

### What **NOT** to do

```php
// ❌ WRONG — strips type info, unsortable client-side
return $response->withJson(['amount' => '$' . number_format($amount, 2)]);
```

---

## PDF Reports

Treat PDF currency migration as a **rewrite**, not a patch. TCPDF column
widths are fixed, so wider symbols (`CHF`, `CAD $`) can overflow.

- Read symbol/position/separators via `CurrencyFormatter::toArray()` at the
  top of the report.
- Measure column widths with `GetStringWidth()` after formatting a sample
  row, and bump the column if needed.
- Default to right-aligned money columns.

See the Phase 5 sub-issue of the epic for the full rewrite list (TaxReport,
AdvancedDeposit, EnvelopeReport, PledgeSummary, FamilyPledgeSummary,
PrintDeposit, FundRaiserStatement, FR bid sheets, etc.).

### FPDF Latin-1: convert exactly once <!-- learned: 2026-07-19 -->

FPDF core fonts render ISO-8859-1 only; UTF-8 (including the formatter's
`\u{00A0}` and `€`/`£`) must be transcoded before `Cell()`/`Write()` — but
converting the same string twice corrupts it. Pick ONE of these per string:

```php
// Amount-only cell → formatForPdf() does format + transcode in one call
$pdf->Cell(25, $h, CurrencyFormatter::formatForPdf($total), 0, 0, 'R');

// Amount concatenated with UTF-8 text (names/labels) → plain format(),
// then convert the WHOLE string once at the end
$pdf->Cell(176, $h, ChurchInfoReport::convertToLatin1(
    "$fundName Total:   " . CurrencyFormatter::format($total)), 0, 0, 'R');

// printRightJustified()/writeAt()/writeAtCell() convert internally →
// pass plain format() output, never pre-converted text
$pdf->printRightJustified($x, $y, CurrencyFormatter::format($total));
```

❌ Never `formatForPdf()` inside a string that gets `convertToLatin1()`-wrapped,
and never `convertToLatin1()` before a `printRightJustified*()`/`writeAt*()` helper.

---

## Multi-Char Symbol Gotchas

Free-form input allows `CHF`, `CAD $`, `R$`, `kr`, etc.

- **Tight table columns**: add `white-space: nowrap` + `min-width` or move
  the symbol into a column header.
- **Chart.js axis**: truncate with a custom tick callback when `symbol.length > 3`.
- **Form inputs with prepended symbol** (`<div class="input-group-text">`):
  consider a wider prepend for longer symbols.
- **Deposit slip editor**: amounts sit in narrow cells — test with `CHF` and
  `CAD $` before committing.

No validation is enforced in config; report bugs per view as they appear.

---

## Migration Checklist (per PR)

When touching any finance-adjacent file:

- [ ] Replaced every `$` that precedes a number with `CurrencyFormatter::format()` (PHP) or `window.CRM.currency.format()` (JS).
- [ ] If the file ships a JSON payload with money → added `*_formatted` sibling.
- [ ] DataTable columns rendering money use `render:` with the JS helper.
- [ ] Chart.js axis + tooltip callbacks use the JS helper.
- [ ] Screenshot the page in the PR with both `$` (default) **and** `€ after` (to prove localization works).
- [ ] Cypress smoke: admin change config → reload page → value reflects.

---

## Verification

1. Admin → **System Settings → Financial Settings**.
2. Set `sCurrencySymbol` = `€`, `sCurrencyPosition` = `after`,
   `sThousandsSeparator` = `.`, `sDecimalSeparator` = `,`.
3. Reload the migrated page. Values display as `1.234,56 €` (NBSP between).
4. Reset to defaults (`$`, `before`, `,`, `.`). Legacy behavior restored.

---

## Auto-update

When you discover a new site that renders money (a new page, a new API
response, a new chart, a new PDF), add it to the migration checklist in the
epic (#8459) and cite this skill from your PR description.

<!-- learned: 2026-04-18 -->
