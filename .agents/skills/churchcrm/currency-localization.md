---
title: "Currency Localization"
intent: "Render money values with a configurable symbol, position, and separators across PHP, JS, DataTables, Chart.js, CSS, and PDFs"
tags: ["i18n", "localization", "currency", "money", "finance", "systemconfig"]
prereqs: ["configuration-management.md", "i18n-localization.md", "frontend-development.md"]
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

Free-form symbol input is allowed — multi-char symbols (`CHF`, `CAD $`) are
fine. Fix UI overflow issues reactively per view.

---

## PHP: `CurrencyFormatter`

Location: `src/ChurchCRM/Utils/CurrencyFormatter.php`

### API

```php
use ChurchCRM\Utils\CurrencyFormatter;

CurrencyFormatter::format(1234.5);        // "$1,234.50"
CurrencyFormatter::format(1234.5, 0);     // "$1,235"
CurrencyFormatter::symbol();              // "$"
CurrencyFormatter::position();            // "before"
CurrencyFormatter::toArray();             // ['symbol' => ..., 'position' => ..., ...] for JSON
```

Output uses a non-breaking space (`\u{00A0}`) between symbol and amount to
prevent line-wrapping on narrow screens.

### Use in views (replace hardcoded `$`)

```php
// ❌ WRONG — hardcoded $
<div class="fw-medium">$<?= number_format($amount, 2) ?></div>

// ✅ CORRECT — uses configured symbol/position/separators
<div class="fw-medium"><?= CurrencyFormatter::format($amount) ?></div>
```

Never concatenate the symbol yourself; the helper applies the configured
position and separators for you.

---

## JS: `window.CRM.currency`

Injected in `src/Include/Header.php` from `CurrencyFormatter::toArray()`.

### API

```js
window.CRM.currency.symbol;        // "$"
window.CRM.currency.position;      // "before" | "after"
window.CRM.currency.thousand;      // ","
window.CRM.currency.decimal;       // "."
window.CRM.currency.format(1234.5) // "$1,234.50"
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
        callback: (value) => window.CRM.currency.format(value, 0)
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

Set once on `<html>` in `src/Include/Header.php` from
`SystemConfig::getValue('sCurrencySymbol')` (JSON-encoded for safety).

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
<span class="money">1,234.50</span>
<!-- Renders: $ 1,234.50 (symbol from CSS var, no PHP/JS needed) -->
```

Prefer `CurrencyFormatter::format()` for server-rendered views — use `.money`
only when the number is already formatted (e.g., a pre-formatted value from
the API, or static display templates where you want pure-CSS substitution).

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
$amount = (float) $deposit->getAmount();

return $response->withJson([
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
