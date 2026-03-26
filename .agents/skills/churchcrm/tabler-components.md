---
title: "Tabler UI Component Reference"
intent: "Complete reference for Tabler framework layout, cards, tables, forms, navigation, and utility components used in ChurchCRM"
tags: ["frontend", "tabler", "bootstrap5", "ui", "components"]
prereqs: ["bootstrap-5-migration.md", "frontend-development.md"]
complexity: "intermediate"
---

# Skill: Tabler UI Component Reference <!-- learned: 2026-03-21 -->

## Overview

Tabler is built on Bootstrap 5.3.x. Every Bootstrap 5 class works in Tabler. Tabler adds its own component layer on top with CSS variables prefixed `--tblr-*`.

**Key constraint**: ChurchCRM keeps jQuery and DataTables.net. Tabler works alongside jQuery — Bootstrap 5 auto-detects jQuery and registers plugin constructors on `$.fn`.

---

## 1. Page Layout (The Shell)

### Combo Layout (Sidebar + Topbar) — ChurchCRM Default

```html
<body class="antialiased">
<div class="page">
  <!-- Sidebar -->
  <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a href="/" class="navbar-brand navbar-brand-autodark">
        <img src="/logo.png" alt="Logo" class="navbar-brand-image">
      </a>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <!-- nav items -->
        </ul>
      </div>
    </div>
  </aside>

  <!-- Topbar -->
  <header class="navbar navbar-expand-md navbar-light d-none d-lg-flex d-print-none sticky-top">
    <div class="container-xl">
      <div class="navbar-nav flex-row order-md-last">
        <!-- right-side icons -->
      </div>
      <div class="collapse navbar-collapse" id="navbar-menu">
        <!-- search bar -->
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-2 align-items-center">
          <div class="col-auto"><h2 class="page-title">Page Title</h2></div>
        </div>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <!-- page content -->
      </div>
    </div>
    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <!-- footer -->
      </div>
    </footer>
  </div>
</div>
</body>
```

### Container: Always Use `container-xl`

Tabler uses `container-xl` (max-width: 1320px) by default, not `container-fluid`. This gives content breathing room on large screens.

### Key Layout Classes

| Class | Purpose |
|-------|---------|
| `.page` | Root wrapper (replaces `.wrapper`) |
| `.page-wrapper` | Content area (replaces `.content-wrapper`) |
| `.page-header` | Title + breadcrumb area (replaces `.content-header`) |
| `.page-body` | Main content (replaces `.content`) |
| `.page-title` | Page heading (`<h2>`) |
| `.navbar-vertical` | Sidebar layout |
| `.navbar-brand-autodark` | Logo auto-inverts in dark mode |
| `.navbar-brand-image` | Logo image sizing (32px height) |

### Unified Page Header (MANDATORY for all pages) <!-- learned: 2026-03-25 -->

Every page **must** have a unified page header rendered by `Header.php`. The header is configured via variables — **never** create a duplicate `<h2>` or `page-header` div inside page content.

**Variables** (set before `require Header.php` or in route `$pageArgs`):

| Variable | Type | Required | Purpose |
|----------|------|----------|---------|
| `$sPageTitle` | string | **Yes** | Browser tab + `<h2>` heading |
| `$sPageSubtitle` | string | No | Muted description below title |
| `$aBreadcrumbs` | array | **Yes** | Breadcrumb trail (3-4 levels max) |
| `$sPageHeaderButtons` | string | No | Admin action buttons (right of breadcrumbs) |
| `$sSettingsCollapseId` | string | No | Inline settings panel collapse container ID |

**Helper class**: `ChurchCRM\view\PageHeader` (`src/ChurchCRM/view/PageHeader.php`)

**Breadcrumbs** — URLs are relative, `getRootPath()` is prepended automatically:
```php
use ChurchCRM\view\PageHeader;

// Legacy page (before require Header.php)
$sPageTitle = gettext('Group View') . ': ' . $groupName;
$sPageSubtitle = gettext('View group members, roles, and properties');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Groups'), '/groups/dashboard'],   // relative URL
    [$groupName],                                // last item = active (no URL)
]);
require_once __DIR__ . '/Include/Header.php';

// MVC route ($pageArgs)
$pageArgs = [
    'sPageTitle'         => gettext('Finance Dashboard'),
    'sPageSubtitle'      => gettext('Manage donations, pledges, and financial records'),
    'aBreadcrumbs'       => PageHeader::breadcrumbs([
        [gettext('Finance')],
    ]),
    'sPageHeaderButtons' => PageHeader::buttons([
        ['label' => gettext('Settings'), 'icon' => 'fa-cog', 'collapse' => '#financialSettings'],
        ['label' => gettext('Donation Funds'), 'url' => '/DonationFundEditor.php', 'icon' => 'fa-hand-holding-dollar'],
    ]),
    'sSettingsCollapseId' => 'financialSettings',
];
```

**Admin action buttons** — two types:

| Type | Purpose | Example |
|------|---------|---------|
| **Settings toggle** | Bootstrap collapse for inline `SettingsPanel` | `['label' => ..., 'icon' => 'fa-cog', 'collapse' => '#sectionSettings']` |
| **Link button** | Navigate to admin/config pages | `['label' => ..., 'url' => '/PropertyList.php?Type=g', 'icon' => 'fa-list']` |

Buttons are admin-only by default. Set `'adminOnly' => false` for all-user buttons. Button URLs are relative (root path prepended automatically).

**Quick Actions cards** (inside page body) are separate from page header buttons. Quick Actions are for everyday user actions (Add Person, Add Family, etc). Page header buttons are for admin shortcuts (Settings, Properties, Types).

**Rules**:
- Never create a duplicate `<h2>`, `.page-header`, or `.page-title` inside page content
- Every page needs `$sPageTitle` + `$aBreadcrumbs` at minimum
- Every MVC page also needs `$sPageSubtitle`
- Breadcrumb depth: Home > Section > [Sub-section >] Current Page (3-4 levels max)
- Main dashboard (`/v2/dashboard`) has no breadcrumbs — it IS Home

---

## Full Tabler UX Checklist (MANDATORY for all page redesigns) <!-- learned: 2026-03-25 -->

When redesigning or fixing UX on any page, apply **all** of the following — not just partial fixes:

| Element | ✅ Tabler Standard | ❌ Wrong |
|---------|-------------------|---------|
| Card emphasis | `card-status-top bg-{color}` (thin line) | `card-header bg-primary` (solid blue bar) |
| Icons | `ti ti-*` (Tabler icons) | `fa-solid fa-*` (FontAwesome) |
| Form labels | `form-label` | `control-label`, `fw-bold` on labels |
| Select elements | `form-select` | `form-control` on `<select>` |
| Form wrapper | Plain `<form>` | `class="well form-horizontal"` |
| Required fields | `class="form-label required"` | Manual `<span class="text-danger">*</span>` |
| Table classes | `table table-vcenter table-hover` | `table-striped`, `table-light` on thead |
| Table headers | Plain text only | Icons in `<th>` |
| Button sizes | Standard (no size class) | `btn-lg` in forms |
| Form actions | Inside `card-footer` | Inside `card-body` with `row mt-3` |
| Context info bar | `card card-sm` | `alert alert-info` |
| Photo button | `btn-ghost-secondary` | `btn-outline-secondary` |
| Person rows | Standard person action menu (View→Edit→Family→Cart→Delete) | Inline buttons |
| Muted text | `text-secondary` | `text-muted` |

---

## 2. Cards

### Basic Card

```html
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Title</h3>
    <div class="card-actions">
      <a href="#" class="btn btn-primary btn-sm">Action</a>
    </div>
  </div>
  <div class="card-body">Content</div>
  <div class="card-footer">Footer</div>
</div>
```

### Status Card (Leader Persona — colored top bar)

```html
<div class="card">
  <div class="card-status-top bg-primary"></div>
  <div class="card-body">
    <h3 class="card-title">Active Members</h3>
    <p class="text-secondary">124 this month</p>
  </div>
</div>
```

Variants: `card-status-top`, `card-status-start` (left), `card-status-bottom`.
Colors: `bg-primary`, `bg-success`, `bg-danger`, `bg-warning`, `bg-info`.

### Stamp Card (Leader Persona — icon stamp)

```html
<div class="card card-sm">
  <div class="card-body">
    <div class="row align-items-center">
      <div class="col-auto">
        <span class="card-stamp">
          <span class="card-stamp-icon bg-primary-lt">
            <i class="ti ti-users"></i>
          </span>
        </span>
      </div>
      <div class="col">
        <div class="fw-medium">Active Members</div>
        <div class="text-secondary">124</div>
      </div>
    </div>
  </div>
</div>
```

### Card Table (Admin Persona — data density)

```html
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Recent People</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table table-sm table-hover">
      <thead>
        <tr>
          <th>Name</th>
          <th>Role</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>John Doe</td>
          <td>Member</td>
          <td><span class="badge bg-success">Active</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

### Card with Tabs

```html
<div class="card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs">
      <li class="nav-item">
        <a href="#tab-info" class="nav-link active" data-bs-toggle="tab">Info</a>
      </li>
      <li class="nav-item">
        <a href="#tab-activity" class="nav-link" data-bs-toggle="tab">Activity</a>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane active" id="tab-info">Info content</div>
      <div class="tab-pane" id="tab-activity">Activity content</div>
    </div>
  </div>
</div>
```

### Card Sizing

| Class | Description |
|-------|-------------|
| `.card-sm` | Compact padding |
| `.card-lg` | Extra padding |
| `.card-stacked` | Stacked visual effect (appears as pile) |

---

## 3. Tables

### Standard Tabler Table

```html
<div class="table-responsive">
  <table class="table table-vcenter">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th class="w-1">Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>John Doe</td>
        <td class="text-secondary">john@example.com</td>
        <td>
          <a href="#" class="btn btn-ghost-primary btn-sm">
            <i class="ti ti-pencil"></i>
          </a>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

### Table Utility Classes

| Class | Purpose |
|-------|---------|
| `.table-vcenter` | Vertically center all cells |
| `.table-sm` | Compact rows (Admin persona) |
| `.table-hover` | Row hover highlight |
| `.table-striped` | Alternating row colors |
| `.table-nowrap` | Prevent text wrapping |
| `.table-responsive` | Horizontal scroll on small screens |
| `.card-table` | Remove card-body padding for full-width table |
| `.w-1` | Shrink column to content width (for action buttons) |

### DataTables Integration

DataTables.net works with Tabler. Use Bootstrap 5 DataTables integration (`dataTables.bootstrap5`), not Bootstrap 4.

```js
$('#myTable').DataTable({
  ...window.CRM.plugin.dataTable,
  // DataTables auto-applies Tabler styling via bootstrap5 integration
});
```

### ⚠️ CRITICAL: Never Add `max-width` Constraints to Card Tables <!-- learned: 2026-03-23 -->

**DO NOT write CSS rules like this:**
```scss
/* ❌ WRONG — breaks full-width table layouts */
.card > .card-body table {
  max-width: 980px;  /* This constraint squishes modern responsive tables */
}
```

**Why this is a problem:**
- `table-responsive` + `w-100` already handle mobile responsiveness
- AdminLTE legacy max-width (980px) doesn't fit Tabler's responsive design
- Constraining table width loses screen real estate on wide displays
- Users can't read full table rows without horizontal scrolling

**What to do instead:**
- Tables in cards should be **full-width** by default
- For **legacy form tables** (FinancialReports, etc.), use a specific class:
  ```scss
  .form-table {
    width: 100%;
    /* Add form-specific styles here, not generic table rules */
  }
  ```
- For **action column width**, use `.w-1` utility class on the `<th>`:
  ```html
  <th class="w-1">Actions</th>
  ```

**Audit checklist:**
- Search codebase for `.card > .card-body table` rules
- Search for `table { max-width:` rules
- Remove any width constraints that apply to data tables in cards
- If you find constraints, move them to specific component classes (e.g., `.form-table`, `#reportTable`)

---

## 4. Forms

### Standard Form Layout

```html
<form>
  <div class="mb-3">
    <label class="form-label">First Name</label>
    <input type="text" class="form-control" placeholder="Enter name">
  </div>
  <div class="mb-3">
    <label class="form-label">Classification</label>
    <select class="form-select">
      <option>Member</option>
      <option>Visitor</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-check">
      <input type="checkbox" class="form-check-input">
      <span class="form-check-label">Active</span>
    </label>
  </div>
</form>
```

### Form Layout Patterns

```html
<!-- Horizontal form -->
<div class="row mb-3">
  <label class="col-3 col-form-label">Name</label>
  <div class="col">
    <input type="text" class="form-control">
  </div>
</div>

<!-- Row of inputs -->
<div class="row g-2">
  <div class="col-6">
    <input type="text" class="form-control" placeholder="First">
  </div>
  <div class="col-6">
    <input type="text" class="form-control" placeholder="Last">
  </div>
</div>
```

### Key Form Class Changes (BS4 → Tabler/BS5)

| Old (BS4) | New (Tabler/BS5) |
|-----------|-----------------|
| `.form-group` | `<div class="mb-3">` |
| `.custom-select` | `.form-select` |
| `.custom-control .custom-checkbox` | `.form-check` |
| `.custom-control-input` | `.form-check-input` |
| `.custom-control-label` | `.form-check-label` |
| `.form-control-file` | `.form-control` (on `<input type="file">`) |
| `.input-group-append` | Removed — nest directly in `.input-group` |
| `.input-group-prepend` | Removed — nest directly in `.input-group` |

### Input Groups (BS5 — no append/prepend wrappers)

```html
<!-- BS4 (old) -->
<div class="input-group">
  <input type="text" class="form-control">
  <div class="input-group-append">
    <button class="btn btn-primary">Go</button>
  </div>
</div>

<!-- BS5/Tabler (new) — no wrapper needed -->
<div class="input-group">
  <input type="text" class="form-control">
  <button class="btn btn-primary">Go</button>
</div>
```

### Switches (replaces custom toggles)

```html
<label class="form-check form-switch">
  <input class="form-check-input" type="checkbox" checked>
  <span class="form-check-label">Enable notifications</span>
</label>
```

### Validation Styling

```html
<div class="mb-3">
  <label class="form-label">Email</label>
  <input type="email" class="form-control is-invalid">
  <div class="invalid-feedback">Please enter a valid email.</div>
</div>
```

---

## 5. Navigation

### Correct Page Layout Structure (Tabler vertical nav) <!-- learned: 2026-03-21 -->

The `<header>` (topbar) must be **inside** `<div class="page-wrapper">`, not before it:

```html
<div class="page">
  <aside class="navbar navbar-vertical navbar-expand-lg">...</aside>
  <div class="page-wrapper">          <!-- ← page-wrapper wraps BOTH topbar and content -->
    <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sticky-top">...</header>
    <div class="page-header">...</div>
    <div class="page-body">...</div>
    <footer>...</footer>
  </div>
</div>
```

**Do NOT use `navbar-dark` or `navbar-glass`** on the sidebar `<aside>` — the transparent vertical variant is the default with no extra class.

### Sidebar Nav Item

```html
<li class="nav-item">
  <a class="nav-link" href="/people">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="fa fa-user"></i></span>
    <span class="nav-link-title">People</span>
  </a>
</li>
```

### Sidebar Collapsible Submenu (Tabler 1.4.0) <!-- learned: 2026-03-21 -->

**Critical**: Tabler 1.4.0 uses Bootstrap collapse for sidebar submenus — NOT dropdown.
Use `data-bs-toggle="collapse"` with a `<div class="collapse">` target.

**Do NOT add `navbar-collapse` to the submenu div** — inside `.navbar-expand-lg`, Bootstrap's CSS forces `.navbar-collapse` to `display:flex` on large screens, making ALL submenus always visible regardless of the `collapse` class.

Sub-items use `ps-3` (Bootstrap padding class) directly on the inner `<ul>` for indentation — no custom CSS needed.

`nav-link-arrow` does NOT exist in Tabler 1.4.0 — use a real FA icon with CSS rotation.

```html
<!-- Parent (collapsible folder) -->
<li class="nav-item">
  <a class="nav-link" href="#menu-1" data-bs-toggle="collapse"
     role="button" aria-expanded="false" aria-controls="menu-1">
    <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="fa fa-users"></i></span>
    <span class="nav-link-title">Groups</span>
    <span class="nav-link-arrow"><i class="fa fa-chevron-down" aria-hidden="true"></i></span>
  </a>
  <div class="collapse" id="menu-1">         <!-- ← ONLY "collapse", not "navbar-collapse" -->
    <ul class="navbar-nav ps-3">             <!-- ← ps-3 inlines indentation, no custom CSS -->
      <li class="nav-item">
        <a class="nav-link" href="/groups/dashboard">
          <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="fa fa-list"></i></span>
          <span class="nav-link-title">List Groups</span>
        </a>
      </li>
    </ul>
  </div>
</li>
```

Required CSS for the chevron (add to your custom SCSS):
```scss
.navbar-vertical .nav-link {
  .nav-link-title { flex: 1; }
  .nav-link-arrow {
    display: inline-flex; align-items: center;
    padding-left: 0.25rem; flex-shrink: 0;
    opacity: 0.4; font-size: 0.65rem;
    transition: transform 0.2s ease;
  }
  &[aria-expanded="true"] .nav-link-arrow { transform: rotate(180deg); opacity: 0.7; }
}
```

### Sidebar Badges + Chevron — Flex Wrapper Pattern <!-- learned: 2026-03-22 -->

Tabler's CSS positions `.badge` elements **absolutely** on topbar nav-links (for notification dot style). In the vertical sidebar, this causes badges to overflow off-screen to the right.

**Fix**: Wrap the `<a>` element and any badge `<span>` elements in a `<div class="d-flex align-items-center">`. The collapse div must remain **outside** this flex wrapper (as a block sibling), not inside it.

```html
<li class="nav-item<?= $isActive ? ' active' : '' ?>">
  <!-- flex wrapper contains ONLY the link + badges -->
  <div class="d-flex align-items-center">
    <a href="..." class="nav-link flex-fill">
      <span class="nav-link-icon ..."><i class="fa ..."></i></span>
      <span class="nav-link-title">Menu Label</span>
    </a>
    <!-- badges sit INSIDE the flex row, to the right of the link -->
    <span class="badge bg-danger me-1" id="pendingCount">0</span>
  </div>
  <!-- collapse div is a BLOCK sibling, NOT inside the flex wrapper -->
  <div class="collapse" id="menu-N">
    <ul class="navbar-nav ps-3">...</ul>
  </div>
</li>
```

For **collapsible parent items** with badges and a chevron, the chevron must also be a sibling of `<a>` (not a child), and the CSS selector must use the `~` sibling combinator (not descendant):

```html
<div class="d-flex align-items-center">
  <a class="nav-link flex-fill" href="#menu-N" data-bs-toggle="collapse"
     aria-expanded="false" aria-controls="menu-N">
    <span class="nav-link-icon ..."><i class="fa ..."></i></span>
    <span class="nav-link-title">Label</span>
  </a>
  <!-- optional badge -->
  <span class="badge bg-info me-1">3</span>
  <!-- chevron is LAST in flex row, SIBLING of <a>, not child -->
  <span class="nav-link-arrow me-2"><i class="fa fa-chevron-down"></i></span>
</div>
```

CSS — note the `~` sibling combinator (child `>` won't work when arrow is outside `<a>`):
```scss
// In _tabler-bridge.scss
.navbar-vertical .nav-link {
  .nav-link-title { flex: 1; }
}
.navbar-vertical .nav-link-arrow {
  display: inline-flex; align-items: center;
  flex-shrink: 0; opacity: 0.4; font-size: 0.65rem;
  transition: transform 0.2s ease;
}
// ~ sibling combinator: .nav-link-arrow is NOT a child of [aria-expanded="true"]
.navbar-vertical .nav-link[aria-expanded="true"] ~ .nav-link-arrow {
  transform: rotate(180deg); opacity: 0.7;
}
```

### Sidebar Active State <!-- learned: 2026-03-22 -->

Tabler's left blue border on active sidebar items is triggered by **`.nav-item.active`** on the `<li>`, not just `.nav-link.active` on the `<a>`. Apply **both**:

```html
<li class="nav-item active">           <!-- ← triggers the left-border highlight -->
  <a class="nav-link active" href="..."> <!-- ← triggers the link text color -->
    ...
  </a>
</li>
```

The border is rendered by `.navbar-vertical .navbar-expand-lg .navbar-collapse .nav-item.active:after { border-left: ...}` in Tabler's CSS.

### Sidebar Font Contrast Override <!-- learned: 2026-03-22 -->

By default, Tabler's `.navbar-vertical` uses a light body color that may be low contrast on darker sidebar backgrounds. Override the CSS variables on the sidebar element directly:

```scss
// In _tabler-bridge.scss
.navbar-vertical {
  --tblr-navbar-color: var(--tblr-body-color);        // normal state
  --tblr-navbar-hover-color: var(--tblr-emphasis-color); // hover state
}
```

This ensures sidebar text uses full body-level contrast and emphasis color on hover, without affecting the topbar navbar.

### Breadcrumbs

```html
<ol class="breadcrumb" aria-label="breadcrumbs">
  <li class="breadcrumb-item"><a href="/">Home</a></li>
  <li class="breadcrumb-item"><a href="/people">People</a></li>
  <li class="breadcrumb-item active" aria-current="page">John Doe</li>
</ol>
```

### Tabs (inside card or standalone)

```html
<ul class="nav nav-tabs" data-bs-toggle="tabs">
  <li class="nav-item"><a href="#info" class="nav-link active" data-bs-toggle="tab">Info</a></li>
  <li class="nav-item"><a href="#notes" class="nav-link" data-bs-toggle="tab">Notes</a></li>
</ul>
<div class="tab-content">
  <div class="tab-pane active" id="info">...</div>
  <div class="tab-pane" id="notes">...</div>
</div>
```

---

## 6. Buttons

### Standard Variants

```html
<button class="btn btn-primary">Primary</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-warning">Warning</button>
<button class="btn btn-outline-primary">Outline</button>
<button class="btn btn-ghost-primary">Ghost</button> <!-- Tabler-only: transparent bg -->
```

### Sizes

| Class | Usage |
|-------|-------|
| `.btn-sm` | Compact — Admin tables |
| (default) | Standard — desktop forms |
| `.btn-lg` | Large — Volunteer/mobile touch targets |

### Icon Buttons

```html
<!-- Icon only -->
<a href="#" class="btn btn-icon btn-ghost-primary">
  <i class="ti ti-pencil"></i>
</a>

<!-- Icon + text -->
<a href="#" class="btn btn-primary">
  <i class="ti ti-plus me-1"></i> Add Person
</a>
```

---

## 7. Badges, Avatars & Status

### Badges

```html
<span class="badge bg-primary">Active</span>
<span class="badge bg-success-lt">Approved</span>  <!-- light variant -->
<span class="badge bg-danger-lt">Overdue</span>
```

Light variants: append `-lt` to any color (e.g., `bg-primary-lt`, `bg-success-lt`).

### Avatars

```html
<!-- Image avatar -->
<span class="avatar" style="background-image: url(photo.jpg)"></span>

<!-- Initials avatar -->
<span class="avatar bg-blue-lt">JD</span>

<!-- Sizes -->
<span class="avatar avatar-sm">SM</span>
<span class="avatar avatar-md">MD</span>
<span class="avatar avatar-lg">LG</span>
<span class="avatar avatar-xl">XL</span>

<!-- Avatar list -->
<div class="avatar-list avatar-list-stacked">
  <span class="avatar">A</span>
  <span class="avatar">B</span>
  <span class="avatar">+3</span>
</div>
```

### Status Dots

```html
<span class="status status-green">Active</span>
<span class="status status-red">Inactive</span>
<span class="status status-yellow">Pending</span>
```

### Status Indicator (on avatar)

```html
<span class="avatar">
  JD
  <span class="badge bg-success"></span> <!-- green dot in corner -->
</span>
```

---

## 8. Modals

```html
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-status bg-danger"></div> <!-- colored top bar -->
      <div class="modal-body text-center py-4">
        <i class="ti ti-alert-triangle text-danger mb-2" style="font-size: 3rem;"></i>
        <h3>Are you sure?</h3>
        <p class="text-secondary">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <a href="#" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</a>
        <a href="#" class="btn btn-danger ms-auto">Yes, delete</a>
      </div>
    </div>
  </div>
</div>
```

### Modal Sizes

| Class | Width |
|-------|-------|
| `.modal-sm` | 300px |
| (default) | 500px |
| `.modal-lg` | 800px |
| `.modal-xl` | 1140px |
| `.modal-fullscreen` | 100% |

### Show/Hide via JS

```js
// Create
var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
modal.show();
modal.hide();

// jQuery also works (BS5 auto-registers):
$('#confirmModal').modal('show');
$('#confirmModal').modal('hide');
```

### Form/Action Modal — Best Practice Pattern <!-- learned: 2026-03-22 -->

For modals that contain a form or collect user input before triggering an action, use the default size (no size class = 500px), a clear icon in the title, an `alert-info` explanation block (no dismiss button needed), and a Cancel + primary action footer:

```html
<div class="modal fade" id="actionModal" role="dialog">
  <div class="modal-dialog">  <!-- default size, not modal-lg -->
    <div class="modal-content">
      <form name="actionForm">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="ti ti-bug me-2"></i><?= gettext('Report an Issue') ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"
                  aria-label="<?= gettext('Close') ?>"></button>
        </div>
        <div class="modal-body">
          <!-- Explanation alert — no dismiss button, not dismissible -->
          <div class="alert alert-info mb-3">
            <i class="ti ti-info-circle me-1"></i>
            <?= gettext('Brief explanation of what will happen.') ?>
          </div>
          <!-- Optional input -->
          <div class="form-group">
            <label for="fieldId" class="font-weight-bold">
              <?= gettext('Field label') ?>
              <span class="text-muted font-weight-normal">(<?= gettext('optional') ?>)</span>
            </label>
            <textarea id="fieldId" class="form-control" rows="4"
                      placeholder="<?= gettext('Placeholder…') ?>"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary"
                  data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
          <button type="button" class="btn btn-primary" id="submitBtn">
            <i class="ti ti-brand-github me-1"></i><?= gettext('Primary Action') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
```

**Key rules:**
- Use default `modal-dialog` (500px) for simple forms — `modal-lg` is for data-dense content only
- Alert inside modal body: use `alert-info` without `alert-dismissible` — the modal's own close button is sufficient
- Footer always has Cancel (`btn-secondary` + `data-bs-dismiss`) before the primary action
- Icon in modal title uses `me-2` spacing

---

## 9. Toasts

```html
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div class="toast" role="alert" data-bs-autohide="true" data-bs-delay="3000">
    <div class="toast-header">
      <span class="avatar avatar-xs bg-primary me-2">!</span>
      <strong class="me-auto">Notification</strong>
      <button type="button" class="ms-2 btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body">Record saved successfully.</div>
  </div>
</div>
```

```js
// Show toast via JS
var toast = new bootstrap.Toast(document.querySelector('.toast'));
toast.show();
```

---

## 10. Steps / Wizard

```html
<div class="card">
  <div class="card-header">
    <ul class="steps">
      <li class="step-item active">
        <a href="#" class="step-link"><span class="step-number">1</span>Basic Info</a>
      </li>
      <li class="step-item">
        <a href="#" class="step-link"><span class="step-number">2</span>Details</a>
      </li>
      <li class="step-item">
        <a href="#" class="step-link"><span class="step-number">3</span>Confirm</a>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <!-- step content (manage via JS) -->
  </div>
</div>
```

**Note**: This is CSS-only. Step navigation logic must be written in JS. For full wizard functionality, keep BS Stepper or write custom JS.

---

## 11. Empty States

```html
<div class="empty">
  <div class="empty-icon">
    <i class="ti ti-mood-sad" style="font-size: 3rem;"></i>
  </div>
  <p class="empty-title">No results found</p>
  <p class="empty-subtitle text-secondary">
    Try adjusting your search or filter to find what you're looking for.
  </p>
  <div class="empty-action">
    <a href="#" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add New</a>
  </div>
</div>
```

---

## 12. Tabler CSS Variables

```css
:root {
  --tblr-primary:      #206bc4;
  --tblr-primary-lt:   #d7e8f7;
  --tblr-secondary:    #667382;
  --tblr-success:      #2fb344;
  --tblr-success-lt:   #d3f2d8;
  --tblr-info:         #4dabf7;
  --tblr-warning:      #f76707;
  --tblr-danger:       #d63939;
  --tblr-danger-lt:    #fce8e8;
  --tblr-body-color:   #1d273b;
  --tblr-body-bg:      #f1f5f9;
  --tblr-card-bg:      #fff;
  --tblr-border-color: rgba(98, 105, 118, 0.16);
  --tblr-border-radius: 4px;
  --tblr-font-sans-serif: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}
```

### Dark Mode

```html
<body data-bs-theme="dark">
```

Toggle via JS:
```js
document.body.setAttribute('data-bs-theme',
  document.body.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark'
);
```

---

## 13. Utility Quick Reference

### Spacing (Bootstrap 5)

| BS4 | BS5/Tabler |
|-----|-----------|
| `ml-*` | `ms-*` (margin-start) |
| `mr-*` | `me-*` (margin-end) |
| `pl-*` | `ps-*` (padding-start) |
| `pr-*` | `pe-*` (padding-end) |

### Typography

| Class | Purpose |
|-------|---------|
| `.fw-bold` | Font weight bold (replaces `.font-weight-bold`) |
| `.fw-medium` | Font weight 500 |
| `.fw-normal` | Font weight normal |
| `.fs-1` to `.fs-6` | Font size scale |
| `.text-secondary` | Muted/secondary text |

### Display

| Class | Purpose |
|-------|---------|
| `.d-print-none` | Hide when printing |
| `.visually-hidden` | Screen reader only (replaces `.sr-only`) |

---

## 14. Iconography Dual System

### UI Actions → Tabler Icons (`ti-`)

```html
<i class="ti ti-pencil"></i>      <!-- Edit -->
<i class="ti ti-trash"></i>       <!-- Delete -->
<i class="ti ti-device-floppy"></i> <!-- Save -->
<i class="ti ti-x"></i>           <!-- Close -->
<i class="ti ti-search"></i>      <!-- Search -->
<i class="ti ti-filter"></i>      <!-- Filter -->
<i class="ti ti-settings"></i>    <!-- Settings -->
<i class="ti ti-plus"></i>        <!-- Add -->
<i class="ti ti-download"></i>    <!-- Download -->
<i class="ti ti-upload"></i>      <!-- Upload -->
<i class="ti ti-maximize"></i>    <!-- Fullscreen -->
<i class="ti ti-menu-2"></i>      <!-- Menu toggle -->
<i class="ti ti-logout"></i>      <!-- Sign out -->
<i class="ti ti-key"></i>         <!-- Password -->
<i class="ti ti-shield"></i>      <!-- Security/2FA -->
<i class="ti ti-bug"></i>         <!-- Report issue -->
<i class="ti ti-book"></i>        <!-- Documentation -->
<i class="ti ti-headset"></i>     <!-- Support -->
<i class="ti ti-confetti"></i>    <!-- New release -->
<i class="ti ti-users"></i>       <!-- Group/team -->
```

### Domain Entities → FontAwesome 7 Duotone

```html
<i class="fa-duotone fa-solid fa-user"></i>           <!-- Person -->
<i class="fa-duotone fa-solid fa-house-user"></i>     <!-- Family -->
<i class="fa-duotone fa-solid fa-people-group"></i>   <!-- Group -->
<i class="fa-duotone fa-solid fa-circle-dollar"></i>  <!-- Finance -->
<i class="fa-duotone fa-solid fa-calendar-days"></i>  <!-- Event -->
<i class="fa-duotone fa-solid fa-cart-shopping"></i>   <!-- Cart -->
<i class="fa-duotone fa-solid fa-clipboard-check"></i> <!-- Check-in -->
```

### CSS for Tabler Icons (add to Header-HTML-Scripts.php)

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
```

Or install via npm and add to webpack:
```bash
npm install @tabler/icons-webfont
```
```scss
// In churchcrm.scss
@import "~@tabler/icons-webfont/dist/tabler-icons.min.css";

---

## 10. Topbar Navbar — Exact Tabler Pattern <!-- learned: 2026-03-22 -->

Match `docs.tabler.io/ui/layout/navbars` exactly for the topbar:

```html
<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none sticky-top">
  <div class="container-xl">
    <button class="navbar-toggler" ...>

    <!-- RIGHT NAV: flex-row + order-md-last + ms-auto -->
    <div class="navbar-nav flex-row order-md-last ms-auto">

      <!-- Icon dropdowns — all need dropdown-menu-arrow -->
      <div class="nav-item dropdown ms-1">
        <a class="nav-link px-0" data-bs-toggle="dropdown" href="#">
          <i class="ti ti-headset"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
          ...
        </div>
      </div>

      <!-- User avatar — proper Tabler avatar with initials -->
      <div class="nav-item dropdown">
        <a href="#" class="nav-link d-flex lh-1 text-reset ps-2" data-bs-toggle="dropdown">
          <span class="avatar avatar-sm rounded-circle"
                style="background-color: #667eea; color: #fff; flex-shrink: 0;">CA</span>
          <div class="d-none d-xl-block ps-2">
            <div>Church Admin</div>
            <div class="mt-1 small text-secondary">Administrator</div>
          </div>
        </a>
        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">...</div>
      </div>

    </div><!-- /.navbar-nav.order-md-last -->

    <!-- SEARCH: Tabler input-icon, constrained width, NOT full-fill -->
    <div class="collapse navbar-collapse" id="navbar-menu">
      <div style="position: relative; width: min(480px, 100%);">
        <div class="input-icon">
          <span class="input-icon-addon"><i class="ti ti-search"></i></span>
          <input type="search" id="globalSearch" class="form-control"
                 placeholder="Search people, families, groups…"
                 autocomplete="off" spellcheck="false">
        </div>
        <div id="globalSearchDropdown" class="dropdown-menu w-100"
             style="top: calc(100% + 2px); left: 0; position: absolute;"></div>
      </div>
    </div>

  </div>
</header>
```

**Key rules:**
- `ms-auto` on right nav pushes it to the right edge (do NOT rely on `order-md-last` alone)
- Every `dropdown-menu` in the topbar must have `dropdown-menu-arrow` for the arrow pointer
- Use `dropdown-menu-end` to right-align all dropdown menus
- Search: use `input-icon` + `input-icon-addon`, constrained width (`min(480px, 100%)`), NOT `w-100 flex-fill`
- Never use `<select class="multiSearch">` + Select2 for the global search — it generates incompatible markup

### Avatar with Name-Hash Color (site-wide pattern)

Use a deterministic color derived from the user's full name to give each user a consistent, unique avatar colour:

```php
$nameParts    = explode(' ', trim($fullName));
$initials     = mb_strtoupper(mb_substr($nameParts[0], 0, 1)) .
                (count($nameParts) > 1 ? mb_strtoupper(mb_substr(end($nameParts), 0, 1)) : '');
$colors       = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
$color        = $colors[array_sum(array_map('ord', str_split($fullName))) % count($colors)];
```

```html
<span class="avatar avatar-sm rounded-circle"
      style="background-color: <?= $color ?>; color: #fff; flex-shrink: 0;">
  <?= $initials ?>
</span>
```

Used in: `Header.php` (topbar user), `CartToEvent.php` (person rows).

---

## 11. Global Search — Custom Autocomplete (no Select2) <!-- learned: 2026-03-22 -->

### Why not Select2 for global search

Select2 generates its own DOM container that is incompatible with Tabler's design tokens — the dropdown, border, and focus states all look foreign in the navbar.

### Search API gotcha: groupName embeds count

`BaseSearchResultProvider::formatSearchGroup()` returns a group name like `"Persons (5)"`, not `"Persons"`. Strip the suffix before icon lookup in both PHP templates and JS:

```php
// PHP template
$displayName = (string) preg_replace('/\s*\(\d+\)$/', '', $group->groupName);
$icon        = $groupIcons[$displayName] ?? 'ti-search';
```

```js
// JS autocomplete
var baseName = group.text.split("(")[0].trim(); // "Persons (5)" → "Persons"
var icon = groupIcons[baseName] || "ti-search";
```

If you skip this, every group will render with the fallback `ti-search` icon.

### Badge colour rule

Never use `bg-secondary` (dark) for count badges on white card headers — it clashes. Use Tabler's light variants:

```html
<!-- ✅ non-clashing -->
<span class="badge bg-blue-lt text-blue">5</span>

<!-- ❌ dark badge clashes on white background -->
<span class="badge bg-secondary">5</span>
```

### Badge Text Contrast — Always Use `text-white` on Dark Badges <!-- learned: 2026-03-23 -->

**Problem**: Status badges with dark backgrounds (danger, warning, success, primary, info) without explicit `text-white` can have poor readability depending on browser rendering and system fonts.

**Solution**: Always add `text-white` class to badges with colored backgrounds to ensure WCAG AA contrast compliance:

```html
<!-- ✅ CORRECT — explicit text-white ensures readable contrast -->
<span class="badge bg-danger text-white">Failed Logins: 3</span>
<span class="badge bg-success text-white">Enabled</span>
<span class="badge rounded-pill bg-warning text-white"><i class="fa-solid fa-exclamation me-1"></i>Pending</span>

<!-- ❌ WRONG — relies on implicit browser styling, may fade on some backgrounds -->
<span class="badge bg-danger">Failed Logins: 3</span>
<span class="badge bg-success">Enabled</span>
<span class="badge rounded-pill bg-warning"><i class="fa-solid fa-exclamation me-1"></i>Pending</span>
```

**For light badge variants** (e.g., `bg-danger-lt`, `bg-success-lt`), use the explicit text color that matches:

```html
<!-- ✅ light variant with matching text color -->
<span class="badge bg-danger-lt text-danger">Not enrolled</span>
<span class="badge bg-success-lt text-success">Active</span>

<!-- Light variants default `text-dark` so are readable, but explicit color improves consistency -->
```

**Pill badge pattern** (status indicators):
```html
<!-- ✅ Pill badge with icon and explicit text-white -->
<span class="badge rounded-pill bg-success text-white">
  <i class="fa-solid fa-shield-check me-1"></i>Enabled
</span>

<!-- State variations -->
<span class="badge rounded-pill bg-danger text-white">
  <i class="fa-solid fa-shield-slash me-1"></i>Disabled
</span>
<span class="badge rounded-pill bg-warning text-white">
  <i class="fa-solid fa-hourglass-half me-1"></i>Pending
</span>
```

**Apply to all occurrences**: Search codebase for `.badge` without `text-white` or explicit text color class and fix them as you encounter them.

### "?" keyboard shortcut — use keydown, not keyup

`e.preventDefault()` on `keyup` is too late — the character has already been typed into the focused input. Use `keydown` and guard against any focused input element:

```js
window.addEventListener("keydown", (e) => {
  const tag = document.activeElement?.tagName ?? "";
  const inInput = tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT"
                  || document.activeElement?.isContentEditable;
  if (e.shiftKey && e.key === "?" && !inInput) {
    e.preventDefault();
    searchInput.focus();
  }
});
```

### Search results page route pattern

Add to `src/v2/routes/search.php` and register in `src/v2/index.php`. Calls search providers server-side — no extra API round-trip from the browser:

```php
$app->get('/search', function (Request $request, Response $response): Response {
    $query  = trim($request->getQueryParams()['q'] ?? '');
    $groups = [];
    if (mb_strlen($query) >= 2) {
        foreach ($providers as $provider) {
            $result = $provider->getSearchResults($query);
            if (count($result->results) > 0) $groups[] = $result;
        }
    }
    // render template at src/v2/templates/search/search-results.php
});
```

Do NOT add `autofocus` to the search results page input — it prevents the `?` shortcut from working (the input is already focused, so `?` types into it).
```

---

## Standard Table Action Dropdown <!-- learned: 2026-03-23 -->

> **See the dedicated skill: [`table-action-menu.md`](./table-action-menu.md)**
>
> That file has the complete pattern (PHP + JS), full rules table, overflow/clipping fixes, cart button pattern, order/sort actions, and a pre-commit checklist. Always read it when adding or editing any table with row-level actions.

---

## Profile Page Pattern (Person / Family) <!-- learned: 2026-03-26 -->

Person and Family profile pages share a consistent two-column layout. Family mirrors Person but with deliberate differences to visually distinguish them.

### Layout Structure

| Element | Person Page | Family Page |
|---------|------------|-------------|
| Sidebar column | **Left** (`col-lg-4`) | **Right** (`col-lg-4`) |
| Content column | **Right** (`col-lg-8`) | **Left** (`col-lg-8`) |
| Photo style | 120px square, left side of card | Full-width `card-img-top` |
| Page subtitle | `Person Profile — ID: X` | `Family Profile — ID: X` |

### Action Toolbar (flat `d-flex`, NOT wrapped in a card)

```html
<div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
    <a class="btn btn-ghost-primary" href="..."><i class="fa-solid fa-pen me-1"></i>Edit</a>
    <button class="btn btn-ghost-success AddToCart" ...>Cart</button>
    <!-- More primary actions as ghost buttons -->
    <div class="dropdown ms-auto">
        <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fa-solid fa-ellipsis-vertical me-1"></i>Actions
        </button>
        <!-- Overflow items: Verify, Photo, Activate/Deactivate, Delete -->
    </div>
</div>
```

**Rules:**
- Primary actions (Edit, Cart, Add Note, Add Member) as `btn-ghost-*` buttons directly in the bar
- Secondary/destructive actions in the "Actions" overflow dropdown (`ms-auto` pushes it right)
- Label is always "Actions" (not "More") — matches Person page
- Photo management (Upload/View/Delete) goes inside Actions under a "Photo" header

### Sidebar Cards

- **Photo + attributes**: Always visible, not collapsible
- **Address**: Always visible, not collapsible
- **Contact Info**: Always visible, not collapsible (essential info)
- **Custom Fields**: Collapsible, only rendered if fields exist
- **Properties**: Collapsible, `list-group list-group-flush` style with ghost-danger delete buttons

### Collapsible Card Pattern

```html
<div class="card mb-3">
    <div class="card-header d-flex align-items-center" role="button"
         data-bs-toggle="collapse" data-bs-target="#section-id" aria-expanded="true">
        <h3 class="card-title m-0"><i class="fa-solid fa-icon me-1"></i> Title</h3>
        <div class="ms-auto"><i class="fa-solid fa-chevron-down"></i></div>
    </div>
    <div class="collapse show" id="section-id">
        <div class="card-body">...</div>
    </div>
</div>
```

### Family Members Table (grouped by role)

Use `renderSectionHeader()` + `renderMemberTable()` helper functions. Three groups:
- **Key People** (Head/Spouse) — crown icon, warning color, shows Role column
- **Children** — children icon, info color, NO Role column, adds Sunday School column (if `bEnabledSundaySchool`)
- **Other Members** — user-group icon, secondary color, shows Role column

Each section has its own `<table class="table table-vcenter card-table">` with `avatar-sm` photos.

---

## Pill Nav Filters for DataTables <!-- learned: 2026-03-26 -->

When a DataTable needs client-side filtering (e.g., type or category), use Tabler pill nav tabs instead of toggle switches or checkboxes. This is instant (no server round-trip) and clearly communicates the active filter state.

### Pattern

```html
<div class="card-header d-flex align-items-center flex-wrap gap-2">
    <h3 class="card-title m-0">Title</h3>
    <div class="ms-auto d-flex align-items-center gap-2">
        <ul class="nav nav-pills" role="tablist">
            <li class="nav-item"><a class="nav-link active filter-pill" href="#" data-filter="">All</a></li>
            <li class="nav-item"><a class="nav-link filter-pill" href="#" data-filter="TypeA">Type A</a></li>
            <li class="nav-item"><a class="nav-link filter-pill" href="#" data-filter="TypeB">Type B</a></li>
        </ul>
    </div>
</div>
```

```javascript
$(".filter-pill").on("click", function (e) {
    e.preventDefault();
    $(".filter-pill").removeClass("active");
    $(this).addClass("active");
    dataTable.column(targetCol).search($(this).data("filter") || "").draw();
});
```

### When to use pill filters vs toggle switches

| Use case | Pattern |
|----------|---------|
| Filter table rows by category (Pledge/Payment, Active/Inactive) | **Pill nav** — instant client-side DataTable column search |
| Enable/disable a feature or setting | **Toggle switch** (`form-check form-switch`) |
| Boolean preference that persists (show/hide section) | **Toggle switch** with API save |
| Never | Raw `<input type="checkbox">` without Tabler wrapper |

### Fiscal Year Filter

For finance tables, offer "All Time" / "FY {year}" pills instead of a date picker. Pass `$currentFY` from the route (computed via `FinancialService::formatFiscalYear(FiscalYearUtils::getCurrentFiscalYearId())`). Filter client-side on the Fiscal Year column.
