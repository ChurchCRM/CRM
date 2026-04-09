---
title: "Responsive Design Guidelines"
intent: "Canonical guidance for mobile, tablet, and laptop/desktop layouts across ChurchCRM"
tags: ["frontend","responsive","bootstrap","tabler","mobile","tablet"]
prereqs: ["frontend-development.md","bootstrap-5-migration.md","tabler-components.md"]
complexity: "intermediate"
---

# Skill: Responsive Design Guidelines

## Context

ChurchCRM ships to pastors and volunteers on every kind of device — phones in the
pew, tablets on the welcome desk, laptops in the office. All new pages (and any
page we touch) must work cleanly at **three canonical form factors**. This skill
is the single source of truth for the breakpoints, grid patterns, navigation
behavior, and touch-target rules each form factor requires.

Stack: **Bootstrap 5.3.8 + Tabler 1.4.0** (see `frontend-development.md`).

## The Three Canonical Form Factors <!-- learned: 2026-04-09 -->

Bootstrap 5 defines six breakpoints; we group them into three form factors based
on how the chrome (sidebar, navbar) and content grid actually behave in this
codebase:

| Form factor | Min width | Max width | BS breakpoints | Primary devices |
|---|---|---|---|---|
| **Mobile** | 0 | 767.98px | `xs`, `sm` | Phones (portrait & landscape) |
| **Tablet** | 768px | 1199.98px | `md`, `lg` | Tablets, small laptops, split-screen windows |
| **Laptop/Desktop** | 1200px | ∞ | `xl`, `xxl` | Laptops, desktop monitors, kiosks |

> **Why the split is at 768 and 1200 and not 576/992:** The vertical Tabler
> sidebar in `src/Include/Header.php:176` is `navbar-vertical navbar-expand-xl`,
> which means the **permanent left sidebar only appears at ≥1200px**. Below that
> the nav collapses to a hamburger. The 768px boundary is where column layouts
> start splitting from stacked (mobile-style) into side-by-side (tablet-style).
> Aligning our form-factor names to the actual chrome behavior removes
> ambiguity.

### Form factor 1: Mobile (< 768px)

**Chrome:**
- Hamburger nav (collapsed `#sidebar-menu`)
- Sticky top header
- Safe-area insets active (`viewport-fit=cover`)

**Content grid rules:**
- Stat cards: `col-6` (two-up, never stack to one-up — looks empty on 375px)
- Main columns: `col-12` (stack everything)
- Form fields: `col-12`
- Tables: **must** be wrapped — `.table-responsive` if rows have no action dropdowns, **otherwise** `<div style="overflow: visible;">` (see [`table-action-menu.md`](./table-action-menu.md) — `.table-responsive` clips dropdowns)
- Touch targets: minimum **44×44px** (Apple HIG)
- Page headers: use icon-only buttons (`font-size: 0` trick, see `_tabler-bridge.scss`)
- Labels on multi-step forms: hide under 400px (`d-none d-sm-inline`)
- No fixed `width: *px` on inputs — override to `100%` if inherited
- Inline styles with `width` must be overridable: use `!important` in a mobile media query if needed

### Form factor 2: Tablet (768px – 1199.98px)

**Chrome:**
- Hamburger nav still collapsed (sidebar does NOT appear here)
- Sticky top header
- More horizontal room for card grids

**Content grid rules:**
- Stat cards: 3 or 4 columns (`col-md-4` or `col-md-3`)
- Main/sidebar split: **keep stacked** or use `col-md-6 + col-md-6` for
  balanced two-column layouts. Do NOT use the 8/4 split at md — it cramps the
  narrow column.
- Form fields: `col-md-6` for paired fields (name/email, date range)
- Tables: wrapper still required — `.table-responsive` for rows without
  action dropdowns, `<div style="overflow: visible;">` for rows with action
  dropdowns (see `table-action-menu.md`)
- Card header tabs: keep visible but consider shorter labels
  (`<span class="d-none d-xl-inline">Latest Families</span><span class="d-xl-none">New</span>`)

### Form factor 3: Laptop / Desktop (≥ 1200px)

**Chrome:**
- Full vertical Tabler sidebar on the left (`navbar-expand-xl`)
- Content area is narrower than viewport — account for the ~15rem sidebar
- Topbar becomes the page header bar

**Content grid rules:**
- Stat cards: 4, 5, or 6 columns (`col-lg-3`, `col-lg` auto-equal, `col-lg-2`)
- Main + sidebar cards: `col-lg-8` + `col-lg-4` (the canonical 2/3–1/3 split)
- Dense card tabs become full labels (`d-none d-xl-inline`)
- Multi-column forms OK: `col-lg-4` or `col-lg-3`

> **Note:** We use `col-lg-*` at 992px (not 1200px) for the 8/4 split because
> the 992–1199 band still benefits from side-by-side content even though the
> sidebar hasn't appeared yet. `col-lg-*` = "start being wide at 992px".

## Canonical Patterns

### Stat cards row <!-- learned: 2026-04-09 -->

The project standard is **`col-6 col-lg-3`** for 4 stat cards, or **`col-6 col-lg`**
(auto-equal) for 5+ stat cards.

> **Heads-up on the breakpoint vs the form-factor model:** Bootstrap's `lg`
> breakpoint activates at **992px**, which sits *inside* the Tablet form
> factor (768–1199.98px) defined above — it is not the same as the 1200px
> Laptop boundary. So `col-6 col-lg-3` actually goes:
> - **2-up** on Mobile and narrow Tablet (`< 992px`)
> - **4-up** from wide Tablet through Laptop/Desktop (`≥ 992px`)
>
> This is intentional — by 992px there's enough width for 4 stat cards to
> breathe, so we let them flow before the 1200px sidebar transition. If you
> truly need to gate 4-up on the 1200px sidebar threshold, use `col-xl-3`
> instead. Do **not** describe `col-lg-*` as "laptop and up" in narrative
> text — it switches at 992px (wide tablet), not 1200px.

**Do not use `col-sm-6`** — it stacks to one-up below 576px, which PR #8524
explicitly rejected because 2-up looks better on 375px phones.

```html
<!-- ✅ CORRECT — 4 stat cards: 2-up below lg (992px), 4-up at lg and above -->
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

<!-- ✅ CORRECT — 5 stat cards, auto-equal columns on laptop -->
<div class="row mb-3">
    <div class="col-6 col-lg"><!-- card --></div>
    <div class="col-6 col-lg"><!-- card --></div>
    <div class="col-6 col-lg"><!-- card --></div>
    <div class="col-6 col-lg"><!-- card --></div>
    <div class="col-6 col-lg"><!-- card --></div>
</div>

<!-- ✅ CORRECT — 6 stat cards, 2/3/6 progression -->
<div class="row mb-3">
    <div class="col-6 col-md-4 col-lg-2"><!-- card --></div>
    <!-- five more -->
</div>

<!-- ❌ WRONG — legacy pattern, stacks to 1-up on small phones -->
<div class="col-sm-6 col-lg-3"><!-- ... --></div>
```

### Main content + sidebar layout

Two-column card grids (main content + a narrower sidebar) use `col-lg-8 + col-lg-4`.
On mobile and tablet they stack automatically (both become `col-12`).

```html
<div class="row">
    <div class="col-lg-8">
        <!-- People card, Recent Deposits, Tax Checklist — primary content -->
    </div>
    <div class="col-lg-4">
        <!-- Birthdays, System Info, Deposit Statistics — sidebar cards -->
    </div>
</div>
```

### Tables must be wrapped — but NOT with `.table-responsive` if the rows have action dropdowns <!-- learned: 2026-04-09 -->

> **⚠️ Critical conflict with [`table-action-menu.md`](./table-action-menu.md):**
> `.table-responsive` sets `overflow-x: auto`, which (per CSS spec) forces
> `overflow-y: auto` as well — and that **clips absolutely-positioned row
> dropdowns on their last rows**. For tables that have per-row action menus
> (the `ti-dots-vertical` dropdown pattern), you **must** use
> `<div style="overflow: visible;">` as the wrapper instead. See the Overflow
> section of `table-action-menu.md` for the full root cause.

**Decision flow for every table in a card:**

```
Does the table have per-row action dropdowns?
├── YES → Use <div style="overflow: visible;"> (dropdowns can escape)
│         Accept horizontal overflow on phones — it's the lesser evil
│         compared to broken action menus.
└── NO  → Use .table-responsive (proper mobile horizontal scroll)
```

```html
<!-- ✅ CORRECT — table with row action dropdowns -->
<div class="card-body" style="overflow: visible;">
    <div style="overflow: visible;">
        <table class="table table-vcenter table-hover card-table">
            <!-- rows with ti-dots-vertical dropdown in last <td> -->
        </table>
    </div>
</div>

<!-- ✅ CORRECT — table with NO row actions (e.g. simple read-only list) -->
<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table mb-0">
            <!-- ... -->
        </table>
    </div>
</div>

<!-- ❌ WRONG — raw table in card body overflows on phones -->
<div class="card-body">
    <table class="table" id="groupsTable"></table>
</div>

<!-- ❌ WRONG — clips dropdowns on last row (very easy to miss!) -->
<div class="table-responsive">
    <table>
        <tr><td>...<div class="dropdown"><!-- CLIPPED --></div></td></tr>
    </table>
</div>
```

DataTables-generated tables (`$('#groupsTable').DataTable(...)`) should also
sit inside the same wrapper rules (`overflow: visible` for action-menu rows,
`.table-responsive` otherwise). The global config in
[`src/Include/Header.php:136`](../../../src/Include/Header.php#L136) sets
`window.CRM.plugin.dataTable.responsive = true`, so the responsive plugin is
enabled by default — but `.table-responsive` (or the `overflow: visible`
escape hatch) is still required to protect the *card layout* from
extra-wide content the responsive plugin can't collapse.

### Tab labels: show/hide by form factor

For card header tabs where labels are long, show short labels on narrow screens
and long labels on desktop:

```html
<a class="nav-link" ...>
    <i class="ti ti-home-plus me-1"></i>
    <span class="d-none d-xl-inline"><?= gettext('Latest Families') ?></span>
    <span class="d-xl-none"><?= gettext('New') ?></span>
</a>
```

### Touch targets

- Buttons and icon links: minimum **44×44px** on mobile. Tabler's `.btn` default
  is ~38px — bump to `.btn-lg` or add `min-height: 44px` for mobile-only
  contexts (check-in, kiosk, auth pages).
- Social icons in footer: 44×44px (see `src/skin/scss/_authentication-pages.scss`
  after PR #8555).
- Form inputs: 44px tall is the BS5 default for `.form-control` — OK as-is.

### Safe-area insets (notched devices)

All logged-out templates include `viewport-fit=cover`. Inside their CSS, pad with
the safe-area variables so content clears the notch/home indicator:

```scss
.auth-footer {
    padding-bottom: calc(1rem + env(safe-area-inset-bottom));
    padding-left: calc(1rem + env(safe-area-inset-left));
    padding-right: calc(1rem + env(safe-area-inset-right));
}
```

### Page headers — icon-only on mobile

Page header button lists get too crowded on mobile. Hide the label text and show
only the icon below `sm` (575.98px). This pattern is implemented globally in
`src/skin/scss/_tabler-bridge.scss` for `.page-header .btn-list .btn`:

```scss
@media (max-width: 575.98px) {
    .page-header .btn-list .btn {
        font-size: 0;
    }
    .page-header .btn-list .btn i {
        font-size: 1rem;
    }
}
```

## Form Factor Testing Checklist

Before shipping any page change, verify at these widths:

- **375×812** (iPhone X portrait) — tightest mobile
- **414×896** (iPhone 11 Pro Max) — larger mobile
- **768×1024** (iPad portrait) — tablet lower bound, nav still hamburger
- **1024×768** (iPad landscape) — tablet upper band, nav still hamburger
- **1200×800** (small laptop) — nav sidebar just appeared
- **1440×900** (standard laptop) — typical working size
- **1920×1080** (desktop) — wide monitor

Cypress mobile tests live in `cypress/e2e/ui/mobile/` and default to 375×812
(see `cypress/e2e/ui/mobile/mobile-ux.spec.js` from PR #8555). Add a new spec
there when introducing a new page or fixing a responsive bug.

## Anti-patterns to avoid

| Anti-pattern | Fix |
|---|---|
| `col-sm-6 col-lg-3` stat cards | Use `col-6 col-lg-3` (don't stack on tiny phones) |
| `col-lg-8 col-md-8` main content | Use `col-lg-8` only; stack on md |
| Inline `width: 300px` on inputs | Use `col-md-*` wrappers + `w-100` |
| Bare `<table class="table">` in card (no row dropdowns) | Wrap in `.table-responsive` |
| Bare `<table class="table">` in card (WITH row dropdowns) | Wrap in `<div style="overflow: visible;">` — `.table-responsive` clips dropdowns (see `table-action-menu.md`) |
| `navbar-expand-lg` on vertical navbar | The canonical Tabler sidebar is `navbar-expand-xl` |
| Hardcoded icon `font-size: 12px` on touch targets | Default (≥16px) or bigger for mobile |
| Long labels crammed into card tabs | Use `d-none d-xl-inline` + `d-xl-none` short label pair |
| Using `vh` for min heights of interactive content | Mobile browser chrome moves — use `min-height` with a pixel value |

## Admin pages are laptop-optimized by design <!-- learned: 2026-04-09 -->

Admin pages (`src/admin/**`, `SystemSettings.php`, Plugin Management,
OptionManager, PropertyList, user management) are intentionally built for
laptop/desktop use. They contain dense configuration tables, multi-column
settings grids, and bulk operations that are **not expected to be usable on a
phone**. When auditing responsive behaviour, treat admin pages as laptop-only
and do not penalize them for lacking mobile stacking.

Rule of thumb: if a page sits under the **Admin** menu item, it is laptop-first.
All other top-level nav pages and their child pages must work on mobile (<768),
tablet (768–1199) and laptop (≥1200).

## Main nav page audit reference <!-- learned: 2026-04-09 -->

The main sidebar destinations (from `src/ChurchCRM/Config/Menu/Menu.php`) and
whether they follow the canonical patterns:

| Nav item | File | Grid pattern | Status |
|---|---|---|---|
| Dashboard | `src/v2/templates/root/dashboard.php` | 5 stat cards `col-6 col-lg`, 8/4 split | ✅ |
| People → Dashboard | `src/people/views/dashboard.php` | 4 stat `col-6 col-lg-3`, 6/6 split | ✅ |
| Groups → Dashboard | `src/groups/views/dashboard.php` | 4 stat `col-6 col-lg-3`, DataTable with row dropdowns in `overflow:visible` wrapper | ✅ |
| Sunday School → Dashboard | `src/groups/views/sundayschool/dashboard.php` | 6 stat `col-6 col-md-4 col-lg-2` | ✅ (md step added 2026-04-09) |
| Finance → Dashboard | `src/finance/views/dashboard.php` | 4 stat `col-6 col-lg-3`, 8/4 split, `table-responsive` | ✅ |
| Admin → Dashboard | `src/admin/views/dashboard.php` | 8/4 split, `col-md-6 col-lg-4` quick-start grid | ✅ (laptop-optimized) |

## Next-level page audit (mobile/tablet-relevant) <!-- learned: 2026-04-09 -->

Second-tier pages most likely to be used on a phone or tablet (field use,
check-in kiosks, quick lookups). Admin pages intentionally excluded — see above.

| Page | File | Issue found | Fix |
|---|---|---|---|
| Person List | `src/v2/templates/people/person-list.php` | Filter grid jumped `col-12` → `col-lg-*`, collapsed on tablets | `col-12 col-sm-6 col-lg-{3,4}` 2026-04-09 |
| Family List | `src/v2/templates/people/family-list.php` | `#families` table has row dropdowns — already used `overflow:visible` wrapper | ✅ |
| Events Dashboard | `src/event/views/list-events.php` | Hardcoded `<th style="width: NNNpx">` prevented column reflow | Removed inline widths 2026-04-09 (kept `overflow:visible` wrapper for row dropdowns) |
| Event Check-in | `src/event/views/checkin.php` | Attendance table has row dropdowns — already used `overflow:visible` wrapper | ✅ |
| Photo Directory | `src/v2/templates/people/photo-gallery.php` | Already uses `col-6 col-md-4 col-lg-3` grid | ✅ |
| Family Map | `src/people/views/map-view.php` | Full-viewport map — intentionally desktop-ish but works on tablet | ✅ |
| Person View | `src/people/views/person-view.php` | Uses `col-lg-8`/`col-lg-4` split, stacks cleanly | ✅ |
| Calendar | `src/event/views/calendars.php` | FullCalendar handles its own responsive layout | ✅ |

## Events / Groups / Sunday School audit <!-- learned: 2026-04-09 -->

Third pass focused on pages used during services/check-in (phones and tablets
at the back of the room):

| Page | File | Issue found | Fix |
|---|---|---|---|
| Event Editor | `src/event/views/editor.php` | Form built as `<table>` with 9× `style="width:180px"` on `<td>` labels — broke mobile entirely | Converted to Bootstrap `row`/`col-md-3`+`col-md-9` form pattern 2026-04-09 |
| Event Types List | `src/event/views/types-list.php` | Has row action dropdowns — `overflow:visible` wrapper is correct | ✅ (no change; must stay `overflow:visible` for dropdowns) |
| Event Type Edit | `src/event/views/types-edit.php` | `<th style="width: 200px;">` on Actions column | Replaced with `w-1` utility class 2026-04-09 |
| Group View | `src/groups/views/group-view.php` | `#membersTable` populated by GroupView.js with row dropdowns — `overflow:visible` wrapper is correct | ✅ (no change; must stay `overflow:visible` for dropdowns) |
| Sunday School Class View | `src/groups/views/sundayschool/class-view.php` | Student roster has row action dropdowns — `overflow:visible` wrapper is correct | ✅ (no change; must stay `overflow:visible` for dropdowns) |
| Event Repeat Editor | `src/event/views/repeat-editor.php` | Small fixed-width inline selects (`w:auto`, `w:100px`) inside `flex-wrap` rows | ✅ (wraps cleanly, intentionally small) |
| Event Types New | `src/event/views/types-new.php` | Narrow time-picker widths (70/100/150px) | ✅ (inline time-pickers, acceptable sizing) |

## Form layout anti-pattern: `<table>` for form rows <!-- learned: 2026-04-09 -->

Several legacy forms (e.g. the pre-fix `src/event/views/editor.php`) use a
`<table class="table">` with `<td style="width:180px">` for the label column
and `<td colspan="3">` for the input. This **cannot be made responsive** — the
label column stays 180px on a 360px phone, leaving 180px for the input.

**Fix**: replace with the Bootstrap horizontal form pattern:

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

On mobile (<768) this stacks label-above-input. On tablet/laptop the label
sits right-aligned at 25% and the input fills 75%. Use `offset-md-3` for
action rows that need to align with the input column.

When adding a new top-level or second-level nav destination, add it to the
appropriate table as part of the same PR.
