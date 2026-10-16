---
title: "Responsive Design Guidelines"
intent: "Canonical guidance for mobile, tablet, and laptop/desktop layouts across ChurchCRM"
tags: ["frontend","responsive","bootstrap","tabler","mobile","tablet"]
prereqs: ["[[frontend-development]]","[[tabler-components]]"]
complexity: "intermediate"
---

# Skill: Responsive Design Guidelines

## Context

Pastors and volunteers use ChurchCRM on phones, tablets and laptops. Every new or touched page works at three form factors. Stack: Bootstrap 5 + Tabler 1.6 (versions in `package.json`; markup rules in `tabler-components.md`). The root font size is 16px (`src/skin/churchcrm.scss`), so `rem` values in custom CSS are multiples of 16px.

## The Three Form Factors

| Form factor | Min width | Max width | BS breakpoints | Devices |
|---|---|---|---|---|
| **Mobile** | 0 | 767.98px | `xs`, `sm` | Phones |
| **Tablet** | 768px | 1199.98px | `md`, `lg` | Tablets, small laptops, split-screen windows |
| **Laptop/Desktop** | 1200px | no limit | `xl`, `xxl` | Laptops, monitors, kiosks |

The Tabler sidebar in `src/Include/Header.php` is `navbar-vertical navbar-expand-xl`: the permanent left sidebar appears from 1200px and collapses to a hamburger below. Column layouts switch from stacked to side-by-side at 768px.

### Mobile (< 768px)

- Hamburger nav (collapsed `#sidebar-menu`), sticky top header, safe-area insets (`viewport-fit=cover`)
- Stat cards `col-6` (two-up); main columns and form fields `col-12`
- Tables: wrapper per "Table wrappers" below
- Touch targets at least 44x44px
- Page-header buttons are icon-only (global rule in `src/skin/scss/_tabler-bridge.scss`)
- Multi-step form labels hide under 400px (`d-none d-sm-inline`)
- Inputs take `width: 100%`; inline `width` styles stay overridable by a mobile media query

### Tablet (768px - 1199.98px)

- Nav stays a hamburger
- Stat cards `col-md-4` or `col-md-3`
- Main/sidebar content stacks, or uses balanced `col-md-6 + col-md-6`; the 8/4 split starts at `lg`
- Paired form fields `col-md-6`
- Tables: wrapper per "Table wrappers" below
- Card header tabs use short labels: `<span class="d-none d-xl-inline">Latest Families</span><span class="d-xl-none">New</span>`

### Laptop / Desktop (>= 1200px)

- Full vertical sidebar (about 15rem), so the content area is narrower than the viewport
- Stat cards `col-lg-3`, `col-lg` (auto-equal) or `col-lg-2`
- Main + sidebar cards `col-lg-8` + `col-lg-4`
- Full tab labels; multi-column forms `col-lg-4` or `col-lg-3`

`col-lg-*` starts at 992px, inside the Tablet range, so the 8/4 split and 4-up stat cards begin at wide tablet. Use `col-xl-*` to start at the 1200px sidebar threshold.

## Canonical Patterns

### Stat cards row

Use `col-6 col-lg-3` for 4 cards, `col-6 col-lg` for 5 or more, and `col-6 col-md-4 col-lg-2` for 6. Cards stay two-up on phones, so `col-sm-*` is not used.

```html
<div class="row mb-3">
    <div class="col-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $count ?></div>
                        <div class="text-muted"><?= gettext('People') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- three more col-6 col-lg-3 cards -->
</div>
```

### Main content + sidebar

```html
<div class="row">
    <div class="col-lg-8"><!-- primary cards --></div>
    <div class="col-lg-4"><!-- sidebar cards --></div>
</div>
```

Both columns stack to `col-12` below `lg`.

### Table wrappers

`.table-responsive` sets `overflow-x: auto`, which computes `overflow-y: auto` and clips row dropdowns. `table-action-menu.md` → "Overflow / Dropdown Clipping" owns the rule for tables with action menus; it wins on any disagreement.

```
Rows have action dropdowns?
├── yes → <div style="overflow-x: clip; overflow-y: visible;">
└── no  → <div class="table-responsive">
```

```html
<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table mb-0">...</table>
    </div>
</div>
```

DataTables tables follow the same wrapper rules. `window.CRM.plugin.dataTable` enables the responsive plugin by default (`webpack/crm-bootstrap.js`); the wrapper still protects the card layout from content the plugin cannot collapse.

### Touch targets

- Buttons and icon links reach 44x44px on mobile. Tabler's `.btn` is about 38px, so use `.btn-lg` or `min-height: 44px` in touch contexts (check-in, kiosk, auth pages)
- Footer social icons are 44x44px (`src/skin/scss/_authentication-pages.scss`)
- `.form-control` height is fine as is

### Safe-area insets

Logged-out templates include `viewport-fit=cover`. Pad with the safe-area variables:

```scss
.auth-footer {
    padding-bottom: calc(1rem + env(safe-area-inset-bottom));
    padding-left: calc(1rem + env(safe-area-inset-left));
    padding-right: calc(1rem + env(safe-area-inset-right));
}
```

### Forms

Lay out form rows with the Bootstrap horizontal pattern. A `<table>` with fixed-width label cells cannot reflow on a phone.

```html
<div class="row mb-3">
  <label for="FieldName" class="col-md-3 col-form-label text-md-end fw-semibold">
    <span class="text-danger">*</span><?= gettext('Field') ?>
  </label>
  <div class="col-md-9">
    <input type="text" name="FieldName" id="FieldName" class="form-control" required>
  </div>
</div>
```

Labels stack above inputs below 768px. Use `offset-md-3` for action rows that align with the input column. Table column widths use utility classes (`w-1`), not inline `width`.

## Testing Checklist

Check every page change in a browser at:

- 375x812 (phone, tightest)
- 414x896 (large phone)
- 768x1024 (tablet portrait, hamburger nav)
- 1024x768 (tablet landscape, hamburger nav)
- 1200x800 (sidebar appears)
- 1440x900 (typical laptop)
- 1920x1080 (wide desktop)

Cypress mobile specs live in `cypress/e2e/ui/mobile/` and default to 375x812. Add a spec there for a new page or a responsive fix.

## Layout Reference

| Instead of | Use |
|---|---|
| `col-sm-6 col-lg-3` stat cards | `col-6 col-lg-3` |
| `col-lg-8 col-md-8` main content | `col-lg-8`, stacked at `md` |
| Inline `width: 300px` on inputs | `col-md-*` wrapper + `w-100` |
| Bare `<table class="table">` in a card | `.table-responsive`, or the clip wrapper when rows have dropdowns |
| `navbar-expand-lg` on the vertical navbar | `navbar-expand-xl` |
| Icon `font-size: 12px` on touch targets | 16px or larger |
| Long card tab labels | `d-none d-xl-inline` + `d-xl-none` short label pair |
| `vh` for min-height of interactive content | `min-height` in px (mobile browser chrome moves) |

## Admin Pages

Admin pages (anything under the Admin menu: `src/admin/**`, `SystemSettings.php`, Plugin Management, OptionManager, PropertyList, user management) are built for laptop/desktop: dense configuration tables and bulk operations. All other nav pages and their children work at mobile, tablet and laptop widths.
