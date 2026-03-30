---
title: "Bootstrap 4 to Bootstrap 5 Migration Reference"
intent: "Complete migration guide for converting Bootstrap 4.6.2 to Bootstrap 5.3 in a large PHP application"
tags: ["frontend", "bootstrap", "migration", "css", "javascript"]
prereqs: ["frontend-development.md"]
complexity: "advanced"
---

# Skill: Bootstrap 4 → Bootstrap 5 Complete Migration Reference <!-- learned: 2026-03-21 -->

## Overview

This is the **exhaustive** migration reference for upgrading from Bootstrap 4.6.2 to Bootstrap 5.3.x. Every breaking change, class rename, attribute change, and API difference is documented with before/after examples.

### Dependency Changes

| Dependency | Bootstrap 4 | Bootstrap 5 |
|-----------|-------------|-------------|
| jQuery | **Required** | **Optional** (auto-detected) |
| Popper.js | v1.x | v2.x |
| Sass compiler | Libsass | Dart Sass |
| IE support | IE 10/11 supported | **Dropped** |

---

## 1. Data Attribute Changes (`data-*` → `data-bs-*`)

**ALL** Bootstrap data attributes are now namespaced with `data-bs-` to avoid collisions with other libraries. This is the single largest find-and-replace operation in the migration.

### Complete Data Attribute Rename List

| Bootstrap 4 | Bootstrap 5 | Used By |
|-------------|-------------|---------|
| `data-toggle` | `data-bs-toggle` | Modal, Dropdown, Tab, Collapse, Tooltip, Popover, Button, Offcanvas |
| `data-dismiss` | `data-bs-dismiss` | Modal, Alert, Toast, Offcanvas |
| `data-target` | `data-bs-target` | Modal, Collapse, Scrollspy, Carousel, Offcanvas |
| `data-parent` | `data-bs-parent` | Collapse, Accordion |
| `data-ride` | `data-bs-ride` | Carousel |
| `data-slide` | `data-bs-slide` | Carousel |
| `data-slide-to` | `data-bs-slide-to` | Carousel |
| `data-offset` | `data-bs-offset` | Dropdown, Tooltip, Popover (Scrollspy: **deprecated**, use `data-bs-root-margin`) |
| `data-spy` | `data-bs-spy` | Scrollspy |
| `data-content` | `data-bs-content` | Popover |
| `data-placement` | `data-bs-placement` | Tooltip, Popover |
| `data-trigger` | `data-bs-trigger` | Tooltip, Popover |
| `data-delay` | `data-bs-delay` | Tooltip, Popover |
| `data-animation` | `data-bs-animation` | Tooltip, Popover |
| `data-backdrop` | `data-bs-backdrop` | Modal, Offcanvas |
| `data-keyboard` | `data-bs-keyboard` | Modal, Offcanvas |
| `data-focus` | `data-bs-focus` | Modal |
| `data-scroll` | `data-bs-scroll` | Offcanvas |
| `data-html` | `data-bs-html` | Tooltip, Popover |
| `data-container` | `data-bs-container` | Tooltip, Popover |
| `data-boundary` | `data-bs-boundary` | Dropdown, Tooltip, Popover |
| `data-template` | `data-bs-template` | Tooltip, Popover |
| `data-title` | `data-bs-title` | Tooltip, Popover |
| `data-custom-class` | `data-bs-custom-class` | Tooltip, Popover |
| `data-selector` | `data-bs-selector` | Tooltip, Popover |
| `data-sanitize` | `data-bs-sanitize` | Tooltip, Popover |
| `data-interval` | `data-bs-interval` | Carousel |
| `data-pause` | `data-bs-pause` | Carousel |
| `data-wrap` | `data-bs-wrap` | Carousel |
| `data-touch` | `data-bs-touch` | Carousel |
| `data-display` | `data-bs-display` | Dropdown |
| `data-reference` | `data-bs-reference` | Dropdown |
| `data-popper-config` | `data-bs-popper-config` | Dropdown, Tooltip, Popover |
| *(none)* | `data-bs-auto-close` | Dropdown (**new in BS5**) |
| *(none)* | `data-bs-root-margin` | Scrollspy (**new in BS5**, replaces offset) |
| *(none)* | `data-bs-smooth-scroll` | Scrollspy (**new in BS5**) |
| *(none)* | `data-bs-theme` | Any element (**new in BS5.3**, dark mode) |
| *(none)* | `data-bs-config` | Any plugin (**new in BS5.2**, JSON config) |

### Before/After Examples

```html
<!-- BS4: Modal trigger -->
<button type="button" data-toggle="modal" data-target="#myModal">Open</button>

<!-- BS5: Modal trigger -->
<button type="button" data-bs-toggle="modal" data-bs-target="#myModal">Open</button>
```

```html
<!-- BS4: Collapse -->
<button data-toggle="collapse" data-target="#collapseOne" data-parent="#accordion">Toggle</button>

<!-- BS5: Collapse -->
<button data-bs-toggle="collapse" data-bs-target="#collapseOne" data-bs-parent="#accordion">Toggle</button>
```

```html
<!-- BS4: Dropdown -->
<button data-toggle="dropdown">Menu</button>

<!-- BS5: Dropdown -->
<button data-bs-toggle="dropdown">Menu</button>
```

```html
<!-- BS4: Tooltip -->
<span data-toggle="tooltip" data-placement="top" data-html="true" title="<b>Tooltip</b>">Hover</span>

<!-- BS5: Tooltip -->
<span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" data-bs-title="<b>Tooltip</b>">Hover</span>
```

```html
<!-- BS4: Dismiss alert -->
<button type="button" class="close" data-dismiss="alert">&times;</button>

<!-- BS5: Dismiss alert -->
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
```

```html
<!-- BS4: Carousel -->
<div id="carousel" class="carousel slide" data-ride="carousel">
  <a href="#carousel" data-slide="prev">Prev</a>
  <a href="#carousel" data-slide="next">Next</a>
  <li data-target="#carousel" data-slide-to="0"></li>
</div>

<!-- BS5: Carousel -->
<div id="carousel" class="carousel slide" data-bs-ride="carousel">
  <button data-bs-target="#carousel" data-bs-slide="prev">Prev</button>
  <button data-bs-target="#carousel" data-bs-slide="next">Next</button>
  <button data-bs-target="#carousel" data-bs-slide-to="0"></button>
</div>
```

```html
<!-- BS4: Scrollspy -->
<body data-spy="scroll" data-target="#navbar" data-offset="80">

<!-- BS5: Scrollspy (Intersection Observer based) -->
<body data-bs-spy="scroll" data-bs-target="#navbar" data-bs-root-margin="0px 0px -25%">
```

### New in BS5: data-bs-config (v5.2.0+)

Pass a full JSON configuration object via a single attribute:

```html
<!-- Individual attributes -->
<div data-bs-toggle="tooltip" data-bs-placement="top" data-bs-delay="500" data-bs-title="Hello">

<!-- Or combined JSON config -->
<div data-bs-toggle="tooltip" data-bs-config='{"placement":"top","delay":500,"title":"Hello"}'>
```

**Resolution order** (later wins): `data-bs-config` < individual `data-bs-*` attributes < JS constructor options.

### Search-and-Replace Commands for PHP/HTML Files

```bash
# Core toggles (most common)
grep -rl 'data-toggle=' src/ | xargs sed -i 's/data-toggle=/data-bs-toggle=/g'
grep -rl 'data-dismiss=' src/ | xargs sed -i 's/data-dismiss=/data-bs-dismiss=/g'
grep -rl 'data-target=' src/ | xargs sed -i 's/data-target=/data-bs-target=/g'
grep -rl 'data-parent=' src/ | xargs sed -i 's/data-parent=/data-bs-parent=/g'

# Carousel
grep -rl 'data-ride=' src/ | xargs sed -i 's/data-ride=/data-bs-ride=/g'
grep -rl 'data-slide-to=' src/ | xargs sed -i 's/data-slide-to=/data-bs-slide-to=/g'
grep -rl 'data-slide=' src/ | xargs sed -i 's/data-slide=/data-bs-slide=/g'

# Tooltip/Popover
grep -rl 'data-placement=' src/ | xargs sed -i 's/data-placement=/data-bs-placement=/g'
grep -rl 'data-content=' src/ | xargs sed -i 's/data-content=/data-bs-content=/g'
grep -rl 'data-trigger=' src/ | xargs sed -i 's/data-trigger=/data-bs-trigger=/g'
grep -rl 'data-delay=' src/ | xargs sed -i 's/data-delay=/data-bs-delay=/g'
grep -rl 'data-animation=' src/ | xargs sed -i 's/data-animation=/data-bs-animation=/g'
grep -rl 'data-html=' src/ | xargs sed -i 's/data-html=/data-bs-html=/g'
grep -rl 'data-container=' src/ | xargs sed -i 's/data-container=/data-bs-container=/g'
grep -rl 'data-template=' src/ | xargs sed -i 's/data-template=/data-bs-template=/g'
grep -rl 'data-boundary=' src/ | xargs sed -i 's/data-boundary=/data-bs-boundary=/g'

# Modal/Offcanvas
grep -rl 'data-backdrop=' src/ | xargs sed -i 's/data-backdrop=/data-bs-backdrop=/g'
grep -rl 'data-keyboard=' src/ | xargs sed -i 's/data-keyboard=/data-bs-keyboard=/g'
grep -rl 'data-focus=' src/ | xargs sed -i 's/data-focus=/data-bs-focus=/g'
grep -rl 'data-scroll=' src/ | xargs sed -i 's/data-scroll=/data-bs-scroll=/g'

# Scrollspy
grep -rl 'data-spy=' src/ | xargs sed -i 's/data-spy=/data-bs-spy=/g'
grep -rl 'data-offset=' src/ | xargs sed -i 's/data-offset=/data-bs-offset=/g'

# CAUTION: data-offset on scrollspy should become data-bs-root-margin (manual review needed)
```

> **WARNING**: Run `data-slide=` replacement AFTER `data-slide-to=` to avoid double-replacing.

---

## 2. jQuery Compatibility

### BS5 jQuery Auto-Detection

Bootstrap 5 **does NOT require jQuery** but **automatically detects it**. If `jQuery` (or `$`) exists on `window` and there is no `data-bs-no-jquery` attribute on `<body>`, Bootstrap will:

1. Register all plugins in jQuery's plugin system (`$().modal()`, `$().tooltip()`, etc.)
2. Emit events through jQuery's event system (so `$(el).on('show.bs.modal')` works)

### Opting Out of jQuery Integration

```html
<!-- Disable jQuery plugin registration even if jQuery is present -->
<body data-bs-no-jquery>
```

### jQuery Plugin API (Still Works in BS5)

```javascript
// BS4 style — STILL WORKS in BS5 if jQuery is loaded
$('#myModal').modal('show');
$('#myModal').modal('hide');
$('[data-bs-toggle="tooltip"]').tooltip();
$('#myTab a').on('shown.bs.tab', function() { /* ... */ });

// noConflict for namespace collisions
const bootstrapButton = $.fn.button.noConflict();
$.fn.bootstrapBtn = bootstrapButton;
```

### Native JS API (Preferred in BS5)

```javascript
// BS5 preferred — no jQuery needed
const modal = new bootstrap.Modal(document.getElementById('myModal'));
modal.show();
modal.hide();

// Or with CSS selector (BS5 convenience)
const modal = new bootstrap.Modal('#myModal');

// Events via native DOM
document.getElementById('myModal').addEventListener('show.bs.modal', (event) => {
  // event.relatedTarget = the element that triggered the modal
});
```

### Migration Strategy for jQuery-Heavy Applications

For projects that **keep jQuery** (like ChurchCRM with AdminLTE):

1. **Phase 1**: Update all `data-*` attributes to `data-bs-*` in HTML/PHP templates
2. **Phase 2**: jQuery event listeners (`$(el).on('show.bs.modal')`) continue to work
3. **Phase 3**: Gradually replace `$('#modal').modal('show')` with `new bootstrap.Modal('#modal').show()`
4. **Phase 4**: Eventually remove jQuery dependency if desired

### Event Compatibility Summary

| Pattern | BS4 | BS5 with jQuery | BS5 without jQuery |
|---------|-----|-----------------|-------------------|
| `$(el).on('show.bs.modal', fn)` | Works | Works | N/A |
| `el.addEventListener('show.bs.modal', fn)` | Works | Works | Works |
| `$(el).modal('show')` | Works | Works | N/A |
| `new bootstrap.Modal(el).show()` | N/A | Works | Works |
| `bootstrap.Modal.getInstance(el)` | N/A | Works | Works |

---

## 3. CSS Class Renames — Complete Reference

### 3.1 Spacing: Directional → Logical (RTL Support)

Bootstrap 5 uses logical properties (`start`/`end`) instead of physical (`left`/`right`) for RTL support.

#### Margin

| Bootstrap 4 | Bootstrap 5 | CSS Property |
|-------------|-------------|-------------|
| `.ml-0` through `.ml-5` | `.ms-0` through `.ms-5` | `margin-left` (LTR) / `margin-right` (RTL) |
| `.mr-0` through `.mr-5` | `.me-0` through `.me-5` | `margin-right` (LTR) / `margin-left` (RTL) |
| `.ml-auto` | `.ms-auto` | `margin-left: auto` |
| `.mr-auto` | `.me-auto` | `margin-right: auto` |
| `.ml-n1` through `.ml-n5` | `.ms-n1` through `.ms-n5` | Negative margin-left |
| `.mr-n1` through `.mr-n5` | `.me-n1` through `.me-n5` | Negative margin-right |

**Unchanged**: `.mt-*`, `.mb-*`, `.mx-*`, `.my-*`, `.m-*` remain the same.

#### Padding

| Bootstrap 4 | Bootstrap 5 | CSS Property |
|-------------|-------------|-------------|
| `.pl-0` through `.pl-5` | `.ps-0` through `.ps-5` | `padding-left` |
| `.pr-0` through `.pr-5` | `.pe-0` through `.pe-5` | `padding-right` |

**Unchanged**: `.pt-*`, `.pb-*`, `.px-*`, `.py-*`, `.p-*` remain the same.

```html
<!-- BS4 -->
<div class="ml-3 mr-2 pl-4 pr-1">Content</div>

<!-- BS5 -->
<div class="ms-3 me-2 ps-4 pe-1">Content</div>
```

Responsive variants follow the same pattern:

```html
<!-- BS4 -->
<div class="ml-md-3 mr-lg-2">Content</div>

<!-- BS5 -->
<div class="ms-md-3 me-lg-2">Content</div>
```

### 3.2 Float

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.float-left` | `.float-start` |
| `.float-end` | `.float-end` |
| `.float-sm-left` | `.float-sm-start` |
| `.float-md-right` | `.float-md-end` |
| *(etc. for all breakpoints)* | *(etc. for all breakpoints)* |

```html
<!-- BS4 -->
<div class="float-left">Left</div>
<div class="float-end">Right</div>

<!-- BS5 -->
<div class="float-start">Start (left in LTR)</div>
<div class="float-end">End (right in LTR)</div>
```

### 3.3 Text Alignment

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.text-left` | `.text-start` |
| `.text-right` | `.text-end` |
| `.text-sm-left` | `.text-sm-start` |
| `.text-md-right` | `.text-md-end` |
| `.text-justify` | **REMOVED** (no replacement) |

**Unchanged**: `.text-center` remains the same.

```html
<!-- BS4 -->
<div class="text-left text-md-right">Content</div>

<!-- BS5 -->
<div class="text-start text-md-end">Content</div>
```

### 3.4 Typography / Font Utilities

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.font-weight-bold` | `.fw-bold` |
| `.font-weight-bolder` | `.fw-bolder` |
| `.font-weight-normal` | `.fw-normal` |
| `.font-weight-light` | `.fw-light` |
| `.font-weight-lighter` | `.fw-lighter` |
| `.font-italic` | `.fst-italic` |
| `.text-monospace` | `.font-monospace` |
| `.text-hide` | **REMOVED** (no replacement) |
| `.text-muted` | `.text-body-secondary` (deprecated in 5.3) |

**New in BS5:**
- `.fw-medium` (v5.3)
- `.fw-semibold`
- `.fst-normal` (reset italic)
- `.fs-1` through `.fs-6` (font-size utilities)

```html
<!-- BS4 -->
<span class="font-weight-bold font-italic text-monospace">Text</span>
<span class="text-muted">Secondary text</span>

<!-- BS5 -->
<span class="fw-bold fst-italic font-monospace">Text</span>
<span class="text-body-secondary">Secondary text</span>
```

### 3.5 Border Utilities

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.border-left` | `.border-start` |
| `.border-right` | `.border-end` |
| `.border-left-0` | `.border-start-0` |
| `.border-right-0` | `.border-end-0` |
| `.rounded-left` | `.rounded-start` |
| `.rounded-right` | `.rounded-end` |
| `.rounded-sm` | **REMOVED** (use `.rounded-1`) |
| `.rounded-lg` | **REMOVED** (use `.rounded-3`) |

**New in BS5:**
- `.rounded-0` through `.rounded-5` (sizing scale)
- `.border-width` utilities
- `.border-opacity-*` utilities

```html
<!-- BS4 -->
<div class="border-left rounded-right rounded-lg">Content</div>

<!-- BS5 -->
<div class="border-start rounded-end rounded-3">Content</div>
```

### 3.6 Badges

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.badge-primary` | `.bg-primary` (+ `.text-white` if needed, or `.text-bg-primary` in v5.2+) |
| `.badge-secondary` | `.bg-secondary` |
| `.badge-success` | `.bg-success` |
| `.badge-danger` | `.bg-danger` |
| `.badge-warning` | `.bg-warning` (+ `.text-dark` if needed) |
| `.badge-info` | `.bg-info` |
| `.badge-light` | `.bg-light` (+ `.text-dark`) |
| `.badge-dark` | `.bg-dark` |
| `.badge-pill` | `.rounded-pill` |

```html
<!-- BS4 -->
<span class="badge badge-primary">Primary</span>
<span class="badge badge-pill badge-success">12</span>

<!-- BS5 (option A: manual color pairing) -->
<span class="badge bg-primary">Primary</span>
<span class="badge rounded-pill bg-success">12</span>

<!-- BS5 (option B: text-bg helper, v5.2+, auto-pairs text color) -->
<span class="badge text-bg-primary">Primary</span>
<span class="badge rounded-pill text-bg-success">12</span>
```

### 3.7 Close Button

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.close` | `.btn-close` |
| Inner `<span>&times;</span>` | **Removed** (SVG background-image) |
| `.close .text-white` | `.btn-close-white` (deprecated in v5.3; use `data-bs-theme="dark"`) |

```html
<!-- BS4 -->
<button type="button" class="close" data-dismiss="modal" aria-label="Close">
  <span aria-hidden="true">&times;</span>
</button>

<!-- BS5 -->
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
```

### 3.8 Forms — Major Overhaul

#### Form Group → Spacing Utilities

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.form-group` | **REMOVED** — use `.mb-3` (or other spacing) |
| `.form-row` | **REMOVED** — use `.row` + gutter classes `.g-*` |
| `.form-inline` | **REMOVED** — use `.d-flex`, `.d-inline-flex`, or grid |
| *(no label class)* | `.form-label` **(required on all labels)** |

```html
<!-- BS4 -->
<div class="form-group">
  <label for="email">Email</label>
  <input type="email" class="form-control" id="email">
  <small class="form-text text-muted">Help text</small>
</div>

<!-- BS5 -->
<div class="mb-3">
  <label for="email" class="form-label">Email</label>
  <input type="email" class="form-control" id="email">
  <div class="form-text">Help text</div>
</div>
```

#### Custom Controls → Unified Form Components

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.custom-control.custom-checkbox` | `.form-check` |
| `.custom-control.custom-radio` | `.form-check` |
| `.custom-control-input` | `.form-check-input` |
| `.custom-control-label` | `.form-check-label` |
| `.custom-switch` | `.form-check.form-switch` |
| `.custom-select` | `.form-select` |
| `.custom-range` | `.form-range` |
| `.custom-file` | `.form-control` (type="file" natively styled) |
| `.form-control-file` | `.form-control` (type="file") |
| `.form-control-range` | `.form-range` |

```html
<!-- BS4: Checkbox -->
<div class="custom-control custom-checkbox">
  <input type="checkbox" class="custom-control-input" id="check1">
  <label class="custom-control-label" for="check1">Check</label>
</div>

<!-- BS5: Checkbox -->
<div class="form-check">
  <input class="form-check-input" type="checkbox" id="check1">
  <label class="form-check-label" for="check1">Check</label>
</div>
```

```html
<!-- BS4: Switch -->
<div class="custom-control custom-switch">
  <input type="checkbox" class="custom-control-input" id="switch1">
  <label class="custom-control-label" for="switch1">Toggle</label>
</div>

<!-- BS5: Switch -->
<div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" role="switch" id="switch1">
  <label class="form-check-label" for="switch1">Toggle</label>
</div>
```

```html
<!-- BS4: Select -->
<select class="custom-select">
  <option>Choose...</option>
</select>

<!-- BS5: Select -->
<select class="form-select">
  <option>Choose...</option>
</select>
```

```html
<!-- BS4: File input -->
<div class="custom-file">
  <input type="file" class="custom-file-input" id="file1">
  <label class="custom-file-label" for="file1">Choose file</label>
</div>

<!-- BS5: File input -->
<input class="form-control" type="file" id="file1">
```

#### Input Groups

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.input-group-prepend` wrapper | **REMOVED** — direct children |
| `.input-group-append` wrapper | **REMOVED** — direct children |
| *(none)* | `.has-validation` (for validation with input groups) |

```html
<!-- BS4: Input group -->
<div class="input-group">
  <div class="input-group-prepend">
    <span class="input-group-text">$</span>
  </div>
  <input type="text" class="form-control">
  <div class="input-group-append">
    <button class="btn btn-primary">Go</button>
  </div>
</div>

<!-- BS5: Input group (flat structure) -->
<div class="input-group">
  <span class="input-group-text">$</span>
  <input type="text" class="form-control">
  <button class="btn btn-primary">Go</button>
</div>
```

#### Floating Labels (New in BS5)

```html
<!-- BS5: Floating label (new component) -->
<div class="form-floating mb-3">
  <input type="email" class="form-control" id="floatingInput" placeholder="name@example.com">
  <label for="floatingInput">Email address</label>
</div>
```

#### Toggle Buttons (New Markup in BS5)

```html
<!-- BS4: Toggle button required JS wrapper -->
<div class="btn-group-toggle" data-toggle="buttons">
  <label class="btn btn-secondary active">
    <input type="checkbox" checked> Active
  </label>
</div>

<!-- BS5: Toggle button (no JS wrapper needed) -->
<input type="checkbox" class="btn-check" id="btn-check" autocomplete="off">
<label class="btn btn-primary" for="btn-check">Toggle</label>
```

### 3.9 Grid Changes

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.no-gutters` | `.g-0` |
| 30px gutters (fixed) | 1.5rem (24px) gutters (adjustable) |
| Breakpoints: xs, sm, md, lg, xl | Breakpoints: xs, sm, md, lg, xl, **xxl** (>=1400px) |
| *(none)* | `.gx-*` (horizontal gutters) |
| *(none)* | `.gy-*` (vertical gutters) |
| *(none)* | `.g-*` (both-axis gutters) |
| `.row-cols-*` (limited) | `.row-cols-*` (all breakpoints) |

```html
<!-- BS4 -->
<div class="row no-gutters">
  <div class="col-md-6">Half</div>
  <div class="col-md-6">Half</div>
</div>

<!-- BS5 -->
<div class="row g-0">
  <div class="col-md-6">Half</div>
  <div class="col-md-6">Half</div>
</div>
```

```html
<!-- BS5: New gutter controls -->
<div class="row g-3">Large gap both axes</div>
<div class="row gx-5 gy-2">Wide horizontal, tight vertical</div>
<div class="row row-cols-1 row-cols-md-3 g-4">Auto-sizing grid</div>
```

#### New xxl Breakpoint

| Breakpoint | Min-width | Class prefix |
|-----------|-----------|-------------|
| xs | <576px | `.col-*` |
| sm | >=576px | `.col-sm-*` |
| md | >=768px | `.col-md-*` |
| lg | >=992px | `.col-lg-*` |
| xl | >=1200px | `.col-xl-*` |
| **xxl** | **>=1400px** | **`.col-xxl-*`** |

### 3.10 Tables

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.thead-dark` | `.table-dark` on `<thead>` |
| `.thead-light` | `.table-light` on `<thead>` |
| *(none)* | `.table-striped-columns` |
| *(none)* | `.table-group-divider` |

```html
<!-- BS4 -->
<table class="table">
  <thead class="thead-dark">
    <tr><th>Name</th></tr>
  </thead>
</table>

<!-- BS5 -->
<table class="table">
  <thead class="table-dark">
    <tr><th>Name</th></tr>
  </thead>
</table>
```

### 3.11 Cards

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.card-deck` | **REMOVED** — use `.row` + `.col` + `.row-cols-*` |
| `.card-columns` | **REMOVED** — use CSS Masonry or `.row-cols-*` |

```html
<!-- BS4: Card deck -->
<div class="card-deck">
  <div class="card">...</div>
  <div class="card">...</div>
  <div class="card">...</div>
</div>

<!-- BS5: Card grid replacement -->
<div class="row row-cols-1 row-cols-md-3 g-4">
  <div class="col"><div class="card">...</div></div>
  <div class="col"><div class="card">...</div></div>
  <div class="col"><div class="card">...</div></div>
</div>
```

### 3.12 Buttons

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.btn-block` | **REMOVED** — use `.d-grid` + `.gap-*` wrapper |

```html
<!-- BS4: Block button -->
<button class="btn btn-primary btn-block">Full Width</button>

<!-- BS5: Block button -->
<div class="d-grid gap-2">
  <button class="btn btn-primary">Full Width</button>
</div>

<!-- BS5: Block button (simpler for single button) -->
<button class="btn btn-primary w-100">Full Width</button>
```

### 3.12a AdminLTE `.btn-app` → Bootstrap 5 `.btn btn-outline-*` <!-- learned: 2026-03-21 -->

**ChurchCRM Pattern**: Large dashboard/action buttons using `.btn-app` (a custom AdminLTE class) with `.fa-3x` stacked vertical icons have been **fully migrated to Bootstrap 5 native classes**.

#### Pattern Conversion

```html
<!-- AdminLTE: .btn-app with stacked icon and text -->
<a class="btn btn-app bg-info">
  <i class="fa-solid fa-print fa-3x"></i><br>
  Printable Page
</a>

<!-- Bootstrap 5: .btn btn-outline-* with inline icon -->
<button class="btn btn-outline-info" title="Printable Page">
  <i class="fa-solid fa-print me-2"></i>Print
</button>
```

#### Key Changes

| Aspect | AdminLTE | Bootstrap 5 |
|--------|----------|-------------|
| **Button class** | `.btn-app bg-info` | `.btn btn-outline-info` |
| **Icon size** | `fa-3x` (large) | `fa-solid` (normal) |
| **Icon spacing** | Stacked: `<br>` between icon and text | Inline: `me-2` (margin-end) |
| **Button size** | Fixed `min-width: 100px; min-height: 110px` | Flexible, respects padding |
| **Text format** | Multi-line allowed | Single-line preferred |
| **Grouping** | `.row` (grid layout) | `.btn-group` (semantic) |

#### Color Mapping

| AdminLTE | Bootstrap 5 | Use Case |
|----------|-------------|----------|
| `.btn-app.bg-primary` | `.btn-outline-primary` | Primary actions |
| `.btn-app.bg-success` | `.btn-outline-success` | Positive actions (approve, add) |
| `.btn-app.bg-danger` | `.btn-outline-danger` | Destructive actions (delete) |
| `.btn-app.bg-warning` | `.btn-outline-warning` | Cautions (edit, change) |
| `.btn-app.bg-info` | `.btn-outline-info` | Info/view actions |
| `.btn-app.bg-secondary` | `.btn-outline-secondary` | Secondary actions |
| ~~`.btn-app.bg-purple`~~ | `.btn-outline-secondary` | Use secondary instead |
| ~~`.btn-app.bg-maroon`~~ | `.btn-outline-danger` | Use danger instead |
| ~~`.btn-app.bg-navy`~~ | `.btn-outline-primary` | Use primary instead |
| ~~`.btn-app.bg-teal`~~ | `.btn-outline-info` | Use info instead |

#### Migration Checklist

- [ ] Replace `.btn btn-app bg-COLOR` with `.btn btn-outline-COLOR`
- [ ] Remove `fa-3x` from icon, keep normal icon size
- [ ] Remove `<br>` between icon and text
- [ ] Add `me-2` (margin-end) to icon for inline spacing
- [ ] Wrap related buttons in `.btn-group` containers
- [ ] Add `title` attribute for accessibility
- [ ] Simplify multi-line text to single-line format
- [ ] Test responsive behavior on mobile (buttons should wrap naturally)
- [ ] Remove unused `.btn-app` CSS rule after all conversions complete

#### Example: Dashboard Button Group Migration

```html
<!-- BEFORE: AdminLTE -->
<div class="row">
  <a class="btn btn-app bg-info">
    <i class="fa-solid fa-clipboard-check fa-3x"></i><br>
    Verify People
  </a>
  <a class="btn btn-app bg-primary dropdown-toggle" data-toggle="dropdown">
    <i class="fa-solid fa-mail-bulk fa-3x"></i><br>
    Email All
  </a>
  <div class="dropdown-menu">
    <a class="dropdown-item" href="#">All People</a>
  </div>
</div>

<!-- AFTER: Bootstrap 5 -->
<div class="btn-group" role="group">
  <a class="btn btn-outline-info" title="Verify People">
    <i class="fa-solid fa-clipboard-check me-2"></i>Verify
  </a>
  <div class="dropdown d-inline-block">
    <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" title="Email all people">
      <i class="fa-solid fa-mail-bulk me-2"></i>Email
    </button>
    <ul class="dropdown-menu">
      <li><a class="dropdown-item" href="#">All People</a></li>
    </ul>
  </div>
</div>
```

#### CSS Cleanup: Remove `.btn-app` SCSS

After all template conversions, the `.btn-app` SCSS rule can be removed:

```scss
// DELETE THIS from src/skin/scss/_ui-components.scss
.btn-app {
  min-width: 100px;
  min-height: 110px;
  height: auto;
  white-space: normal;
  word-wrap: break-word;
  padding: 15px 10px;
  > .fa-3x {
    display: block;
    margin-bottom: 10px;
  }
}
```

#### Implementation Status

**✅ COMPLETE** — All 40+ `.btn-app` buttons converted across:
- PersonView.php (12 buttons)
- PeopleDashboard.php (4 buttons)
- email/dashboard.php (4 buttons)
- cart/cartfunctions.php (8 buttons)
- groups/class-view.php (4 buttons)
- people/verify-view.php (2 buttons)

**Status**: `.btn-app` CSS rule removed, all templates use `.btn btn-outline-*`

### 3.13 Dropdowns

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.dropdown-menu-right` | `.dropdown-menu-end` |
| `.dropdown-menu-left` | `.dropdown-menu-start` |
| `.dropdown-menu-sm-right` | `.dropdown-menu-sm-end` |
| *(none)* | `.dropdown-menu-dark` (deprecated in v5.3) |
| *(none)* | `.dropdown-center` |
| *(none)* | `.dropstart` (left-opening) |

```html
<!-- BS4 -->
<div class="dropdown">
  <button data-toggle="dropdown">Menu</button>
  <div class="dropdown-menu dropdown-menu-right">
    <a class="dropdown-item" href="#">Action</a>
  </div>
</div>

<!-- BS5 -->
<div class="dropdown">
  <button data-bs-toggle="dropdown">Menu</button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><a class="dropdown-item" href="#">Action</a></li>
  </ul>
</div>
```

### 3.14 Screen Reader / Visually Hidden

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.sr-only` | `.visually-hidden` |
| `.sr-only-focusable` | `.visually-hidden-focusable` |

```html
<!-- BS4 -->
<span class="sr-only">Screen reader only</span>

<!-- BS5 -->
<span class="visually-hidden">Screen reader only</span>
```

### 3.15 Responsive Embeds / Ratios

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.embed-responsive` | `.ratio` |
| `.embed-responsive-16by9` | `.ratio-16x9` |
| `.embed-responsive-4by3` | `.ratio-4x3` |
| `.embed-responsive-21by9` | `.ratio-21x9` |
| `.embed-responsive-1by1` | `.ratio-1x1` |
| `.embed-responsive-item` | **REMOVED** (automatic via `.ratio > *`) |

```html
<!-- BS4 -->
<div class="embed-responsive embed-responsive-16by9">
  <iframe class="embed-responsive-item" src="..."></iframe>
</div>

<!-- BS5 -->
<div class="ratio ratio-16x9">
  <iframe src="..."></iframe>
</div>

<!-- BS5: Custom ratio -->
<div class="ratio" style="--bs-aspect-ratio: 50%;">
  <div>2:1 ratio</div>
</div>
```

### 3.16 Removed Components

| Bootstrap 4 Component | Bootstrap 5 Replacement |
|-----------------------|------------------------|
| `.jumbotron` | **REMOVED** — use utilities (`.bg-light .p-5 .rounded-3`) |
| `.media` / `.media-body` | **REMOVED** — use flex utilities (`.d-flex`) |
| `.pre-scrollable` | **REMOVED** — use `overflow: auto` + `max-height` |

```html
<!-- BS4: Jumbotron -->
<div class="jumbotron">
  <h1 class="display-4">Hello!</h1>
  <p class="lead">Description</p>
</div>

<!-- BS5: Jumbotron replacement -->
<div class="p-5 mb-4 bg-body-tertiary rounded-3">
  <h1 class="display-4">Hello!</h1>
  <p class="lead">Description</p>
</div>
```

```html
<!-- BS4: Media object -->
<div class="media">
  <img src="..." class="mr-3" alt="...">
  <div class="media-body">
    <h5>Title</h5>
    <p>Content</p>
  </div>
</div>

<!-- BS5: Media object replacement -->
<div class="d-flex">
  <img src="..." class="me-3" alt="...">
  <div>
    <h5>Title</h5>
    <p>Content</p>
  </div>
</div>
```

### 3.17 Tooltips / Popovers Internal Classes

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `.arrow` (on tooltip) | `.tooltip-arrow` |
| `.arrow` (on popover) | `.popover-arrow` |
| `whiteList` option | `allowList` option |

### 3.18 Progress Bars

```html
<!-- BS4: aria on inner bar -->
<div class="progress">
  <div class="progress-bar" role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" style="width: 25%">25%</div>
</div>

<!-- BS5: aria on outer container -->
<div class="progress" role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">
  <div class="progress-bar" style="width: 25%">25%</div>
</div>
```

---

## 4. Component JavaScript API Changes

### 4.1 Modal

```javascript
// BS4 (jQuery)
$('#myModal').modal('show');
$('#myModal').modal('hide');
$('#myModal').modal({ backdrop: 'static', keyboard: false });
$('#myModal').on('show.bs.modal', function(e) { /* ... */ });

// BS5 (Vanilla JS)
const modal = new bootstrap.Modal('#myModal', {
  backdrop: 'static',  // true | false | 'static'
  focus: true,          // Focus on open
  keyboard: true        // Close on Escape
});
modal.show();
modal.hide();
modal.toggle();
modal.handleUpdate();   // Recalculate position
modal.dispose();        // Destroy instance

// Static methods
bootstrap.Modal.getInstance('#myModal');          // Returns instance or null
bootstrap.Modal.getOrCreateInstance('#myModal');   // Returns instance, creates if needed

// Events (native DOM)
document.getElementById('myModal').addEventListener('show.bs.modal', (event) => {
  const trigger = event.relatedTarget; // Button that opened the modal
});
document.getElementById('myModal').addEventListener('shown.bs.modal', (event) => { });
document.getElementById('myModal').addEventListener('hide.bs.modal', (event) => { });
document.getElementById('myModal').addEventListener('hidden.bs.modal', (event) => { });
document.getElementById('myModal').addEventListener('hidePrevented.bs.modal', (event) => { });
```

### 4.2 Dropdown

```javascript
// BS4 (jQuery)
$('.dropdown-toggle').dropdown();
$('.dropdown-toggle').dropdown('toggle');

// BS5 (Vanilla JS)
const dropdown = new bootstrap.Dropdown('#myDropdown', {
  autoClose: true,              // true | false | 'inside' | 'outside'
  boundary: 'clippingParents',  // String | HTMLElement
  display: 'dynamic',           // 'dynamic' | 'static'
  offset: [0, 2],               // [skidding, distance]
  popperConfig: null,            // null | object | function
  reference: 'toggle'           // 'toggle' | 'parent' | HTMLElement
});
dropdown.show();
dropdown.hide();
dropdown.toggle();
dropdown.update();
dropdown.dispose();

// Events
element.addEventListener('show.bs.dropdown', (event) => { });
element.addEventListener('shown.bs.dropdown', (event) => { });
element.addEventListener('hide.bs.dropdown', (event) => {
  // event.clickEvent available if closed by click
});
element.addEventListener('hidden.bs.dropdown', (event) => { });
```

**Key change**: The `flip` option is removed. Use `popperConfig` with `fallbackPlacements` instead.

### 4.3 Tooltip (Must Be Explicitly Initialized)

Tooltips are **opt-in** in both BS4 and BS5, but BS5 requires explicit initialization.

```javascript
// BS4 (jQuery)
$('[data-toggle="tooltip"]').tooltip();

// BS5 (Vanilla JS) — REQUIRED initialization
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));

// Single tooltip with options
const tooltip = new bootstrap.Tooltip('#myElement', {
  animation: true,
  container: false,        // false | string | element
  customClass: '',         // string | function
  delay: 0,                // number | { show: 500, hide: 100 }
  fallbackPlacements: ['top', 'right', 'bottom', 'left'],
  html: false,
  offset: [0, 6],
  placement: 'top',        // 'auto' | 'top' | 'bottom' | 'left' | 'right'
  popperConfig: null,
  sanitize: true,
  selector: false,         // string (for event delegation)
  template: '<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>',
  title: '',               // string | element | function
  trigger: 'hover focus'   // 'click' | 'hover' | 'focus' | 'manual'
});

// Methods
tooltip.show();
tooltip.hide();
tooltip.toggle();
tooltip.enable();
tooltip.disable();
tooltip.toggleEnabled();
tooltip.update();
tooltip.setContent({ '.tooltip-inner': 'New content' });
tooltip.dispose();

// Static methods
bootstrap.Tooltip.getInstance(element);
bootstrap.Tooltip.getOrCreateInstance(element);

// Events
element.addEventListener('show.bs.tooltip', (event) => { });
element.addEventListener('shown.bs.tooltip', (event) => { });
element.addEventListener('hide.bs.tooltip', (event) => { });
element.addEventListener('hidden.bs.tooltip', (event) => { });
element.addEventListener('inserted.bs.tooltip', (event) => { });
```

### 4.4 Popover (Must Be Explicitly Initialized)

```javascript
// BS4 (jQuery)
$('[data-toggle="popover"]').popover();

// BS5 (Vanilla JS) — REQUIRED initialization
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
const popoverList = [...popoverTriggerList].map(el => new bootstrap.Popover(el));

// Options (same as tooltip, plus:)
const popover = new bootstrap.Popover('#myElement', {
  content: '',    // string | element | function (popover body)
  title: '',      // string | element | function (popover header)
  // ... all tooltip options also apply
});

// Methods: same as tooltip (show, hide, toggle, dispose, enable, disable, etc.)
// plus:
popover.setContent({
  '.popover-header': 'New Title',
  '.popover-body': 'New Content'
});

// Events: show.bs.popover, shown.bs.popover, hide.bs.popover, hidden.bs.popover, inserted.bs.popover
```

### 4.5 Collapse / Accordion

```javascript
// BS4 (jQuery)
$('#collapseOne').collapse('show');
$('#collapseOne').collapse('toggle');

// BS5 (Vanilla JS)
const collapse = new bootstrap.Collapse('#collapseOne', {
  parent: '#accordionExample',  // selector | DOM element | null
  toggle: true                   // Toggle on instantiation
});
collapse.show();
collapse.hide();
collapse.toggle();
collapse.dispose();

// Events
element.addEventListener('show.bs.collapse', (event) => { });
element.addEventListener('shown.bs.collapse', (event) => { });
element.addEventListener('hide.bs.collapse', (event) => { });
element.addEventListener('hidden.bs.collapse', (event) => { });
```

### 4.6 Offcanvas (New in BS5)

```javascript
const offcanvas = new bootstrap.Offcanvas('#myOffcanvas', {
  backdrop: true,     // true | false | 'static'
  keyboard: true,     // Close on Escape
  scroll: false       // Allow body scroll while open
});
offcanvas.show();
offcanvas.hide();
offcanvas.toggle();
offcanvas.dispose();

// Events
element.addEventListener('show.bs.offcanvas', (event) => { });
element.addEventListener('shown.bs.offcanvas', (event) => { });
element.addEventListener('hide.bs.offcanvas', (event) => { });
element.addEventListener('hidden.bs.offcanvas', (event) => { });
element.addEventListener('hidePrevented.bs.offcanvas', (event) => { });
```

```html
<!-- Offcanvas markup -->
<button data-bs-toggle="offcanvas" data-bs-target="#sidebar">Menu</button>

<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="sidebarLabel">Sidebar</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <!-- Content -->
  </div>
</div>
```

Placement classes: `.offcanvas-start` (left), `.offcanvas-end` (right), `.offcanvas-top`, `.offcanvas-bottom`.

Responsive: `.offcanvas-sm`, `.offcanvas-md`, `.offcanvas-lg`, `.offcanvas-xl`, `.offcanvas-xxl`.

### 4.7 Accordion (New Dedicated Component in BS5)

```html
<!-- BS4: Card-based accordion -->
<div class="accordion" id="accordion">
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">
        <button class="btn btn-link" data-toggle="collapse" data-target="#collapseOne" data-parent="#accordion">
          Section 1
        </button>
      </h5>
    </div>
    <div id="collapseOne" class="collapse show">
      <div class="card-body">Content</div>
    </div>
  </div>
</div>

<!-- BS5: Native accordion component -->
<div class="accordion" id="accordion">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button" type="button"
              data-bs-toggle="collapse" data-bs-target="#collapseOne"
              aria-expanded="true" aria-controls="collapseOne">
        Section 1
      </button>
    </h2>
    <div id="collapseOne" class="accordion-collapse collapse show"
         data-bs-parent="#accordion">
      <div class="accordion-body">Content</div>
    </div>
  </div>
</div>
```

Variants:
- `.accordion-flush` — removes borders for edge-to-edge layout
- Omit `data-bs-parent` — allows multiple items open simultaneously

### 4.8 Scrollspy (Rewritten with Intersection Observer)

```javascript
// BS4 (jQuery)
$('[data-spy="scroll"]').scrollspy({ target: '#navbar', offset: 80 });

// BS5 (Vanilla JS — uses Intersection Observer, NOT scroll events)
const scrollSpy = new bootstrap.ScrollSpy(document.body, {
  target: '#navbar',
  rootMargin: '0px 0px -25%',  // Replaces offset
  smoothScroll: true,
  threshold: [0.1, 0.5, 1]
});

// Refresh after DOM changes
scrollSpy.refresh();
scrollSpy.dispose();

// Event
element.addEventListener('activate.bs.scrollspy', (event) => { });
```

**Breaking**: `offset` option replaced by `rootMargin` (CSS margin values). `method` option removed entirely.

### 4.9 Carousel

```javascript
// BS4 (jQuery)
$('#myCarousel').carousel({ interval: 3000 });
$('#myCarousel').carousel('next');
$('#myCarousel').carousel(2); // Go to slide index

// BS5 (Vanilla JS)
const carousel = new bootstrap.Carousel('#myCarousel', {
  interval: 5000,   // ms between slides (false to stop)
  keyboard: true,
  pause: 'hover',   // 'hover' | false
  ride: false,       // 'carousel' to auto-play
  touch: true,
  wrap: true
});
carousel.next();
carousel.prev();
carousel.to(2);    // Go to slide index
carousel.cycle();  // Start cycling
carousel.pause();
carousel.dispose();

// Events
element.addEventListener('slide.bs.carousel', (event) => {
  event.direction; // 'left' or 'right'
  event.relatedTarget; // Slide being slid into place
  event.from; // Index of current slide
  event.to; // Index of next slide
});
element.addEventListener('slid.bs.carousel', (event) => { });
```

### 4.10 Toast

```javascript
// BS5 (Vanilla JS)
const toast = new bootstrap.Toast('#myToast', {
  animation: true,
  autohide: true,
  delay: 5000       // Default changed to 5000ms in BS5
});
toast.show();
toast.hide();
toast.dispose();
toast.isShown();   // Returns boolean

// Events
element.addEventListener('show.bs.toast', (event) => { });
element.addEventListener('shown.bs.toast', (event) => { });
element.addEventListener('hide.bs.toast', (event) => { });
element.addEventListener('hidden.bs.toast', (event) => { });
```

### 4.11 Alert

```javascript
// BS4 (jQuery)
$('.alert').alert('close');

// BS5 (Vanilla JS)
const alert = new bootstrap.Alert('#myAlert');
alert.close();
alert.dispose();

// Event
element.addEventListener('close.bs.alert', (event) => { });
element.addEventListener('closed.bs.alert', (event) => { });
```

### 4.12 Tab

```javascript
// BS4 (jQuery)
$('#myTab a[href="#profile"]').tab('show');

// BS5 (Vanilla JS)
const tab = new bootstrap.Tab('#profile-tab');
tab.show();
tab.dispose();

// Events
element.addEventListener('show.bs.tab', (event) => {
  event.target;        // Newly activated tab
  event.relatedTarget; // Previously active tab
});
element.addEventListener('shown.bs.tab', (event) => { });
element.addEventListener('hide.bs.tab', (event) => { });
element.addEventListener('hidden.bs.tab', (event) => { });
```

### Common API Patterns Across All BS5 Plugins

```javascript
// Constructor accepts CSS selector OR DOM element
const instance = new bootstrap.Modal('#myModal');
const instance = new bootstrap.Modal(document.getElementById('myModal'));

// Static methods on ALL plugins
bootstrap.Modal.getInstance(element);          // Get existing or null
bootstrap.Modal.getOrCreateInstance(element);   // Get or create
bootstrap.Modal.VERSION;                       // Version string
bootstrap.Modal.NAME;                          // Plugin name

// dispose() available on ALL plugins
instance.dispose();

// All methods are ASYNCHRONOUS — they return before transition ends
// Use events to run code after transition:
element.addEventListener('shown.bs.modal', () => {
  // Safe to interact with the now-visible modal
});

// Calls during transition are IGNORED
modal.show();  // Starts transition
modal.hide();  // IGNORED — show transition still in progress

// Change defaults globally
bootstrap.Modal.Default.keyboard = false;
bootstrap.Tooltip.Default.animation = false;
```

---

## 5. Utility API (New in BS5)

Bootstrap 5 introduces a Sass-based Utility API for generating utility classes. This is relevant when customizing the build.

### Sass Map Structure

```scss
$utilities: (
  "opacity": (
    property: opacity,          // CSS property
    class: opacity,             // Class prefix (null = use values as class names)
    values: (                   // Values map
      0: 0,
      25: .25,
      50: .5,
      75: .75,
      100: 1,
    ),
    responsive: false,          // Generate responsive variants?
    print: false,               // Generate print variants?
    state: null,                // Pseudo-class (:hover, :focus)
    css-var: false,             // Generate CSS variable instead of rule?
    rtl: true,                  // Include in RTL?
  )
);
```

### Adding Custom Utilities

```scss
@import "bootstrap/scss/functions";
@import "bootstrap/scss/variables";
@import "bootstrap/scss/maps";
@import "bootstrap/scss/mixins";
@import "bootstrap/scss/utilities";

$utilities: map-merge($utilities, (
  "cursor": (
    property: cursor,
    class: cursor,
    responsive: true,
    values: auto pointer grab,
  )
));

@import "bootstrap/scss/utilities/api";
```

### Removing Utilities

```scss
$utilities: map-merge($utilities, ("width": null));
// or
$utilities: map-remove($utilities, "width", "float");
```

---

## 6. New Utilities in BS5

| Utility | Classes | Purpose |
|---------|---------|---------|
| Gap | `.gap-0` through `.gap-5` | Flexbox/grid gap |
| Row columns | `.row-cols-*` for all breakpoints | Auto-sizing grid columns |
| Visually hidden | `.visually-hidden`, `.visually-hidden-focusable` | Replaces `.sr-only` |
| Font size | `.fs-1` through `.fs-6` | Responsive font sizing |
| Font weight | `.fw-bold`, `.fw-medium`, `.fw-semibold`, `.fw-normal`, `.fw-light`, `.fw-lighter` | Font weight |
| Font style | `.fst-italic`, `.fst-normal` | Font style |
| Display grid | `.d-grid`, `.d-inline-grid` | CSS Grid |
| Object fit | `.object-fit-contain`, `.object-fit-cover`, `.object-fit-fill`, `.object-fit-scale`, `.object-fit-none` | Object fit (v5.3) |
| Z-index | `.z-n1`, `.z-0`, `.z-1`, `.z-2`, `.z-3` | Z-index layering (v5.3) |
| Opacity | `.opacity-0`, `.opacity-25`, `.opacity-50`, `.opacity-75`, `.opacity-100` | Element opacity |
| Line height | `.lh-1`, `.lh-sm`, `.lh-base`, `.lh-lg` | Line height |
| Overflow | `.overflow-x-auto`, `.overflow-y-hidden`, etc. | Directional overflow (v5.3) |
| Link color | `.link-primary`, `.link-body-emphasis`, etc. | Link styling (v5.3) |
| Text-bg | `.text-bg-primary`, `.text-bg-danger`, etc. | Background + contrasting text (v5.2) |
| Border opacity | `.border-opacity-10`, `.border-opacity-25`, etc. | Border transparency |
| Position | `.top-0`, `.top-50`, `.top-100`, `.start-0`, `.end-0`, etc. | Position utilities |
| Translate | `.translate-middle`, `.translate-middle-x`, `.translate-middle-y` | Centering |

---

## 7. Color System Changes

### CSS Custom Properties

BS5 uses CSS custom properties (variables) extensively:

```css
/* BS5 root variables */
:root {
  --bs-blue: #0d6efd;
  --bs-primary: #0d6efd;
  --bs-primary-rgb: 13, 110, 253;
  --bs-body-color: #212529;
  --bs-body-bg: #fff;
  --bs-link-color: #0d6efd;
  --bs-link-hover-color: #0a58ca;
  /* ... hundreds more */
}
```

### Dark Mode (v5.3+)

```html
<!-- Apply dark mode to entire page -->
<html data-bs-theme="dark">

<!-- Apply dark mode to specific component -->
<div data-bs-theme="dark">
  <div class="card">This card is dark</div>
</div>

<!-- Light section within dark page -->
<div data-bs-theme="light">
  <div class="card">This card is light</div>
</div>
```

### New Color Utilities (v5.3)

```html
<!-- Subtle backgrounds -->
<div class="bg-primary-subtle">Light primary background</div>
<div class="bg-danger-subtle">Light danger background</div>

<!-- Subtle borders -->
<div class="border border-primary-subtle">Light primary border</div>

<!-- Emphasis text -->
<p class="text-primary-emphasis">Darker primary text</p>

<!-- Body variants -->
<p class="text-body">Default body text</p>
<p class="text-body-secondary">Secondary text (replaces text-muted)</p>
<p class="text-body-tertiary">Tertiary text</p>
<p class="text-body-emphasis">Emphasis text</p>
```

### Sass Color Function Changes

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `lighten($color, $amount)` | `tint-color($color, $weight)` (mix with white) |
| `darken($color, $amount)` | `shade-color($color, $weight)` (mix with black) |
| `color-yiq($color)` | `color-contrast($color)` |
| `theme-color($key)` | **REMOVED** — use `$theme-colors` map directly |
| `gray($key)` | **REMOVED** — use `$grays` map directly |
| `color($key)` | **REMOVED** — use `$colors` map directly |

### Opacity Utilities

```html
<div class="bg-primary bg-opacity-25">25% opacity primary background</div>
<div class="bg-primary bg-opacity-50">50% opacity</div>
<div class="bg-primary bg-opacity-75">75% opacity</div>
<div class="text-primary text-opacity-50">50% opacity text</div>
```

---

## 8. Sass / Build Changes

### Required Sass Compiler Change

```bash
# BS4 used Libsass (via node-sass)
npm uninstall node-sass
npm install sass  # Dart Sass
```

### New Import Order (v5.2+)

```scss
// BS5 correct import order
@import "bootstrap/scss/functions";
@import "bootstrap/scss/variables";
@import "bootstrap/scss/variables-dark";   // NEW in v5.3
@import "bootstrap/scss/maps";             // NEW in v5.2 (must come after variables)
@import "bootstrap/scss/mixins";
@import "bootstrap/scss/root";
@import "bootstrap/scss/utilities";
// ... component imports ...
@import "bootstrap/scss/utilities/api";    // Must be LAST
```

### Renamed Variables

| Bootstrap 4 | Bootstrap 5 |
|-------------|-------------|
| `$enable-prefers-reduced-motion-media-query` | `$enable-reduced-motion` |
| `$enable-pointer-cursor-for-buttons` | `$enable-button-pointers` |
| `$yiq-contrasted-threshold` | `$min-contrast-ratio` |
| `$yiq-text-dark` | `$color-contrast-dark` |
| `$yiq-text-light` | `$color-contrast-light` |
| `$text-muted` | `$body-secondary-color` |
| `$embed-responsive-aspect-ratios` | `$aspect-ratios` |

### Removed Variables

- `$theme-color-interval`
- `$enable-print-styles` (print styles removed entirely)
- `$display-1-weight` through `$display-4-weight` (use `$display-font-weight`)
- `$display-1-size` through `$display-4-size` (use `$display-font-sizes` map)

### Removed Mixins

- `hover`, `hover-focus`, `plain-hover-focus`, `hover-focus-active`
- `float()`
- `form-control-mixin()`
- `nav-divider()`
- `retina-img()`
- `text-hide()`
- `visibility()`
- `form-control-focus()`

### Media Query Mixin Change

```scss
// BS4: "down" means "below this breakpoint"
@include media-breakpoint-down(md) { } // targets < 768px

// BS5: "down" now means "below the NEXT breakpoint" — the breakpoint name is the UPPER boundary
@include media-breakpoint-down(md) { } // targets < 768px (max-width: 767.98px)
// NOTE: In BS5, the parameter is the breakpoint at which the range ENDS
// media-breakpoint-down(md) in BS4 = media-breakpoint-down(lg) in BS5 conceptually
```

---

## 9. Migration Checklist

### Phase 1: Preparation

- [ ] Switch from `node-sass` to `sass` (Dart Sass)
- [ ] Upgrade Popper.js from v1 to v2
- [ ] Ensure no IE 10/11 support requirements remain
- [ ] Audit all custom Sass for removed variables/mixins/functions

### Phase 2: HTML/PHP Template Updates

- [ ] Replace ALL `data-*` attributes with `data-bs-*` equivalents
- [ ] Replace `.close` buttons with `.btn-close` (remove inner `<span>&times;</span>`)
- [ ] Replace `.sr-only` with `.visually-hidden`
- [ ] Replace `.ml-*`/`.mr-*` with `.ms-*`/`.me-*`
- [ ] Replace `.pl-*`/`.pr-*` with `.ps-*`/`.pe-*`
- [ ] Replace `.float-left`/`.float-end` with `.float-start`/`.float-end`
- [ ] Replace `.text-left`/`.text-right` with `.text-start`/`.text-end`
- [ ] Replace `.font-weight-*` with `.fw-*`
- [ ] Replace `.font-italic` with `.fst-italic`
- [ ] Replace `.text-monospace` with `.font-monospace`
- [ ] Replace `.badge-*` with `.bg-*` (or `.text-bg-*`)
- [ ] Replace `.badge-pill` with `.rounded-pill`
- [ ] Replace `.dropdown-menu-right` with `.dropdown-menu-end`
- [ ] Replace `.btn-block` with `.d-grid` wrapper or `.w-100`
- [ ] Replace `.no-gutters` with `.g-0`
- [ ] Replace `.form-group` with `.mb-3` (or other spacing utility)
- [ ] Replace `.form-row` with `.row` + `.g-*`
- [ ] Replace `.form-inline` with `.d-flex` or grid
- [ ] Add `.form-label` to ALL form `<label>` elements
- [ ] Replace `.custom-control` / `.custom-checkbox` with `.form-check`
- [ ] Replace `.custom-select` with `.form-select`
- [ ] Replace `.custom-file` with `.form-control` (type="file")
- [ ] Replace `.custom-range` with `.form-range`
- [ ] Remove `.input-group-prepend` and `.input-group-append` wrappers
- [ ] Replace `.card-deck` with `.row` + `.col` grid
- [ ] Remove `.card-columns` (use Masonry if needed)
- [ ] Replace `.jumbotron` with utility classes
- [ ] Replace `.media` / `.media-body` with flex utilities
- [ ] Replace `.embed-responsive` with `.ratio`
- [ ] Replace `.thead-dark`/`.thead-light` with `.table-dark`/`.table-light`
- [ ] Replace `.text-muted` with `.text-body-secondary`
- [ ] Replace `.border-left`/`.border-right` with `.border-start`/`.border-end`
- [ ] Replace `.rounded-left`/`.rounded-right` with `.rounded-start`/`.rounded-end`
- [ ] Update progress bar markup (move `role`/`aria-*` to outer `.progress`)
- [ ] Migrate card-based accordions to `.accordion-item` / `.accordion-button` markup

### Phase 3: JavaScript Updates

- [ ] Initialize all tooltips explicitly: `document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el))`
- [ ] Initialize all popovers explicitly: `document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el))`
- [ ] Replace `$('#modal').modal('show')` with `new bootstrap.Modal('#modal').show()` (or keep jQuery if detected)
- [ ] Replace `$('.dropdown').dropdown()` with `new bootstrap.Dropdown()` calls
- [ ] Update scrollspy: replace `offset` option with `rootMargin`
- [ ] Replace `whiteList` option with `allowList` (tooltip/popover)
- [ ] Update Popper config: replace `flip` with `fallbackPlacements`
- [ ] Update any custom event listeners to use `data-bs-*` namespace in selectors

### Phase 4: Testing

- [ ] Test all modals open/close/dismiss
- [ ] Test all dropdowns open/close/alignment
- [ ] Test all tooltips appear correctly
- [ ] Test all collapse/accordion components
- [ ] Test all form validation states
- [ ] Test all carousels
- [ ] Test scrollspy activation
- [ ] Test responsive behavior at all breakpoints (including new xxl)
- [ ] Test RTL layout if applicable

---

## 10. Quick Reference: Search-and-Replace Patterns

### CSS Class Replacements (for grep/sed)

```
# Spacing
ml-   →  ms-
mr-   →  me-
pl-   →  ps-
pr-   →  pe-

# Float
float-left   →  float-start
float-end  →  float-end

# Text
text-left    →  text-start
text-right   →  text-end
text-monospace → font-monospace

# Font
font-weight-bold    →  fw-bold
font-weight-bolder  →  fw-bolder
font-weight-normal  →  fw-normal
font-weight-light   →  fw-light
font-weight-lighter →  fw-lighter
font-italic         →  fst-italic

# Border
border-left   →  border-start
border-right  →  border-end
rounded-left  →  rounded-start
rounded-right →  rounded-end

# Components
badge-primary   →  bg-primary (on .badge)
badge-secondary →  bg-secondary (on .badge)
badge-success   →  bg-success (on .badge)
badge-danger    →  bg-danger (on .badge)
badge-warning   →  bg-warning (on .badge)
badge-info      →  bg-info (on .badge)
badge-light     →  bg-light (on .badge)
badge-dark      →  bg-dark (on .badge)
badge-pill      →  rounded-pill

close                →  btn-close
sr-only              →  visually-hidden
sr-only-focusable    →  visually-hidden-focusable
dropdown-menu-right  →  dropdown-menu-end
dropdown-menu-left   →  dropdown-menu-start
no-gutters           →  g-0
embed-responsive     →  ratio
thead-dark           →  table-dark (on thead)
thead-light          →  table-light (on thead)
custom-select        →  form-select
custom-control-input →  form-check-input
custom-control-label →  form-check-label
text-hide            →  (removed, no replacement)
text-justify         →  (removed, no replacement)
pre-scrollable       →  (removed, use overflow utilities)
form-group           →  mb-3 (or other spacing)
form-row             →  row g-*
form-inline          →  d-flex / d-inline-flex
btn-block            →  d-grid wrapper or w-100
card-deck            →  row + col
card-columns         →  (removed, use Masonry)
jumbotron            →  (removed, use utilities)
media                →  d-flex
media-body           →  (removed, use div)

# Data attributes
data-toggle=    →  data-bs-toggle=
data-dismiss=   →  data-bs-dismiss=
data-target=    →  data-bs-target=
data-parent=    →  data-bs-parent=
data-ride=      →  data-bs-ride=
data-slide-to=  →  data-bs-slide-to=
data-slide=     →  data-bs-slide=
data-offset=    →  data-bs-offset=
data-spy=       →  data-bs-spy=
data-content=   →  data-bs-content=
data-placement= →  data-bs-placement=
data-trigger=   →  data-bs-trigger=
data-delay=     →  data-bs-delay=
data-animation= →  data-bs-animation=
data-backdrop=  →  data-bs-backdrop=
data-keyboard=  →  data-bs-keyboard=
data-focus=     →  data-bs-focus=
data-scroll=    →  data-bs-scroll=
data-html=      →  data-bs-html=
data-container= →  data-bs-container=
data-boundary=  →  data-bs-boundary=
data-template=  →  data-bs-template=
data-interval=  →  data-bs-interval=
data-pause=     →  data-bs-pause=
data-wrap=      →  data-bs-wrap=
data-touch=     →  data-bs-touch=
data-display=   →  data-bs-display=
data-reference= →  data-bs-reference=
```

---

---

## Bulk Sed Migration — Safe Patterns and Dangerous Anti-Patterns <!-- learned: 2026-03-22 -->

### DANGER: Regexes that corrupt PHP string literals

When running `sed` bulk migrations on mixed PHP+HTML template files, several patterns look innocuous but corrupt PHP string literal content, SQL queries, and comments:

```bash
# ❌ DANGEROUS — removes spaces before quotes inside PHP strings/comments/SQL
s/ +"/"/g      # "SELECT * WHERE id = '"  →  "SELECT * WHERE id ='"
s/ +'/'/g      # breaks SQL and string concatenation

# ❌ DANGEROUS — removes spaces before dots in PHP string concatenation
s/ +\././g     # '$a . "x"'  →  '$a."x"' (breaks readability + can cascade)
```

These regexes trigger on ANY space-before-quote in the file — including SQL queries, log messages, PHP comments, and JavaScript strings embedded in PHP templates.

### Safe bulk migration approach

**Only run substitutions anchored to HTML class attributes:**

```bash
# ✅ SAFE — anchored to class=" context (HTML only, not PHP strings)
s/class="\([^"]*\)form-group\([^"]*\)"/class="\1mb-3\2"/g

# ✅ SAFE — simple exact token replacement that can't appear in PHP code
s/data-toggle="/data-bs-toggle="/g
s/data-dismiss="/data-bs-dismiss="/g
s/data-target="/data-bs-target="/g

# ✅ SAFE — badge class prefix (appears in class attributes, not PHP strings)
s/badge-primary/bg-primary/g
s/badge-success/bg-success/g
s/badge-danger/bg-danger/g
s/badge-warning/bg-warning/g
s/badge-info/bg-info/g

# ✅ SAFE — spacing class prefix (ml-N, mr-N cannot appear in PHP code normally)
s/\bml-\([0-9]\)/ms-\1/g
s/\bmr-\([0-9]\)/me-\1/g
```

**Never** run cleanup/beautification regexes like `s/ +"/"/g` on PHP files — these corrupt real code.

### AdminLTE Colored Card → Tabler Border Migration <!-- learned: 2026-03-22 -->

AdminLTE `card-*` color classes do NOT exist in Tabler/BS5. Use Tabler's border utilities instead:

| AdminLTE (BS4) | Tabler/BS5 |
|----------------|------------|
| `card card-danger` | `card border-top border-danger border-3` |
| `card card-warning` | `card border-top border-warning border-3` |
| `card card-success` | `card border-top border-success border-3` |
| `card card-primary` | `card border-top border-primary border-3` |
| `card card-info` | `card border-top border-info border-3` |
| `card card-outline card-danger` | `card border border-danger` |
| `card card-outline card-warning` | `card border border-warning` |
| `card card-outline card-success` | `card border border-success` |

After bulk migration, always scan for `class="card card"` duplicates created when `card-primary` → `card` leaves a double `card card`. Clean up with:

```bash
s/\bcard card\b/card/g
```

---

### card-body p-0 + overflow:visible → table-responsive <!-- learned: 2026-03-29 -->

A common pre-migration anti-pattern wraps DataTable `<table>` elements in a `card-body` with `p-0` and an inline `overflow: visible` to defeat Bootstrap's card clipping. The Tabler standard replaces this entirely with `table-responsive` placed directly on the card (no `card-body` wrapper needed for table-only cards).

**Find instances to fix:**
```bash
grep -rn 'card-body p-0' src/ --include="*.php"
grep -rn 'overflow: visible' src/ --include="*.php"
```

```html
<!-- ❌ Pre-migration anti-pattern -->
<div class="card-body p-0" style="overflow: visible;">
    <table class="table table-vcenter table-hover card-table">...</table>
</div>

<!-- ✅ Tabler standard -->
<div class="table-responsive">
    <table class="table table-vcenter table-hover card-table">...</table>
</div>
```

Also remove the redundant `<div class="row"><div class="col-lg-12">` outer wrapper when a page has a single full-width card — the `container-xl` from Header.php already provides the correct layout context.

---

## Related Skills

- [Frontend Development](./frontend-development.md) — General frontend patterns
- [Webpack & TypeScript](./webpack-typescript.md) — Build system for JS bundles
