# Tabler Migration Rules for ChurchCRM

This file is the authoritative skill reference for the AdminLTE → Tabler migration.
All agents working on UI migration tasks **must** read this file first.

---

## Context

We are migrating ChurchCRM from AdminLTE (Bootstrap 4) to Tabler (Bootstrap 5).

- **Framework**: Tabler (Bootstrap 5)
- **Typography**: Inter Variable Font (`font-family: 'Inter', sans-serif`)
- **Icons**: Tabler Icons (`ti-`) for UI actions; FontAwesome 7 (`fa-`) for domain entities
- **Constraints**: Retain all PHP logic, jQuery, DataTables.net — no React/Vue

---

## SKILL: THE TABLER SHELL

Every migrated page must use this layout structure:

```html
<!-- Page wrapper — replaces AdminLTE .content-wrapper -->
<div class="page-wrapper">

  <!-- Page header — replaces .content-header -->
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col-auto">
          <h2 class="page-title"><?= $sPageTitle ?></h2>
        </div>
        <!-- Breadcrumb col goes here if needed -->
      </div>
    </div>
  </div>

  <!-- Page body — replaces .content > .container-fluid -->
  <div class="page-body">
    <div class="container-xl">
      <!-- page content here -->
    </div>
  </div>

</div>
```

### Sidebar (Vertical Navbar)

```html
<aside class="navbar navbar-vertical navbar-expand-lg navbar-dark" id="sidebar">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="navbar-brand navbar-brand-autodark">
      <img src="<?= SystemURLs::getRootPath() ?>/Images/CRM_50x50.png" alt="ChurchCRM" class="navbar-brand-image">
    </a>
    <div class="collapse navbar-collapse" id="sidebar-menu">
      <ul class="navbar-nav pt-lg-3">
        <?php MenuRenderer::renderMenu(); ?>
      </ul>
    </div>
  </div>
</aside>
```

### Topbar (Horizontal Navbar)

```html
<header class="navbar navbar-expand-md navbar-light d-none d-lg-flex d-print-none sticky-top"
        style="backdrop-filter: blur(8px); background: rgba(255,255,255,0.85);">
  <div class="container-xl">
    <!-- search -->
    <!-- right-side icons: locale, cart, support, user -->
  </div>
</header>
```

---

## SKILL: DATA DENSITY (PERSONAS)

### Power Admin (Desktop) — Data Density First

```html
<div class="card card-table">
  <table class="table table-sm table-hover table-vcenter">
    <!-- high-density rows -->
  </table>
</div>
```

- Use `.table-sm` + `.table-hover` + `.card-table` for all admin data grids.
- DataTables.net: keep as-is, Tabler skin applied via webpack bundling.

### Leader/Pastor (Dashboard) — Visual Storytelling

```html
<div class="card card-sm">
  <div class="card-status-top bg-primary"></div>
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
        <div class="font-weight-medium">Active Members</div>
        <div class="text-muted">123</div>
      </div>
    </div>
  </div>
</div>
```

### Volunteer (Mobile) — Touch Targets First

- All action buttons must be `btn-lg` on mobile breakpoints.
- Use Tabler's responsive cards (stacked layout) instead of wide tables on `< md`.
- Example:
  ```html
  <button class="btn btn-primary btn-lg w-100 d-md-none">Check In</button>
  ```

---

## SKILL: ICONOGRAPHY MAPPING

| Context | Icon System | Syntax |
|---------|-------------|--------|
| UI Actions (Edit, Save, Delete, Close, Search, Filter, Toggle) | Tabler Icons | `<i class="ti ti-[name]"></i>` |
| Domain Entities (Person, Family, Group, Money, Church, Calendar) | FontAwesome 7 Duotone | `<i class="fa-duotone fa-solid fa-[name]"></i>` |
| Brand/Social | FontAwesome 7 Brands | `<i class="fa-brands fa-[name]"></i>` |

### Common Mappings

| Purpose | Old (FA4/FA6) | New Tabler Icon |
|---------|--------------|-----------------|
| Edit | `fa-edit` | `ti ti-pencil` |
| Save | `fa-save` | `ti ti-device-floppy` |
| Delete | `fa-trash` | `ti ti-trash` |
| Close/X | `fa-times` | `ti ti-x` |
| Search | `fa-search` | `ti ti-search` |
| Filter | `fa-filter` | `ti ti-filter` |
| Settings | `fa-cogs` | `ti ti-settings` |
| Add/Plus | `fa-plus` | `ti ti-plus` |
| Download | `fa-download` | `ti ti-download` |
| Upload | `fa-upload` | `ti ti-upload` |
| Fullscreen | `fa-expand-arrows-alt` | `ti ti-maximize` |
| Bars/Menu | `fa-bars` | `ti ti-menu-2` |

| Purpose | Domain Icon (FA7 Duotone) |
|---------|--------------------------|
| Person / Member | `fa-duotone fa-solid fa-user` |
| Family / Household | `fa-duotone fa-solid fa-house-user` |
| Group / Ministry | `fa-duotone fa-solid fa-people-group` |
| Finance / Pledge | `fa-duotone fa-solid fa-circle-dollar` |
| Event / Calendar | `fa-duotone fa-solid fa-calendar-days` |
| Check-in | `fa-duotone fa-solid fa-clipboard-check` |

---

## SKILL: LEGACY BRIDGE — CSS CLASS MAPPING

When encountering AdminLTE or Bootstrap 4 classes on legacy pages, replace as follows:

### Layout

| AdminLTE / BS4 | Tabler / BS5 |
|----------------|-------------|
| `.wrapper` | `.page` |
| `.content-wrapper` | `.page-wrapper` |
| `.content-header` | `.page-header` |
| `.content` | `.page-body` |
| `.container-fluid` | `.container-xl` |
| `.main-footer` | `.footer` |
| `.main-sidebar` | `aside.navbar-vertical` |
| `.main-header` | `header.navbar` |

### Components

| AdminLTE / BS4 | Tabler / BS5 |
|----------------|-------------|
| `.box` | `.card` |
| `.box-header` | `.card-header` |
| `.box-body` | `.card-body` |
| `.box-footer` | `.card-footer` |
| `.box-title` | `.card-title` |
| `.info-box` | `.card .card-stamp` |
| `.small-box` | `.card .card-sm` |
| `data-toggle="..."` | `data-bs-toggle="..."` |
| `data-dismiss="..."` | `data-bs-dismiss="..."` |
| `data-target="..."` | `data-bs-target="..."` |
| `.badge-*` | `.bg-*` (e.g. `.bg-primary`) |
| `.ml-*` / `.mr-*` | `.ms-*` / `.me-*` |
| `.float-end` | `.float-end` |
| `.font-weight-*` | `.fw-*` |
| `.text-left` | `.text-start` |
| `.text-right` | `.text-end` |

### Form Controls

| BS4 | BS5 / Tabler |
|-----|-------------|
| `.form-group` | `<div class="mb-3">` |
| `.custom-select` | `.form-select` |
| `.custom-control` | `.form-check` |
| `.input-group-append` | Removed — inline child of `.input-group` |
| `.input-group-prepend` | Removed — inline child of `.input-group` |

---

## SKILL: BOOTSTRAP 4 → 5 ATTRIBUTE MIGRATION

**Critical**: Every `data-toggle`, `data-dismiss`, `data-target`, `data-parent` must be updated.

```php
// ❌ Bootstrap 4 (AdminLTE)
<button data-toggle="modal" data-target="#myModal">Open</button>
<button data-dismiss="modal">Close</button>

// ✅ Bootstrap 5 (Tabler)
<button data-bs-toggle="modal" data-bs-target="#myModal">Open</button>
<button data-bs-dismiss="modal">Close</button>
```

---

## SKILL: TABLER BREADCRUMB

Include breadcrumbs in the `page-header` when `$sBreadcrumb` is set:

```html
<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col-auto">
        <h2 class="page-title"><?= $sPageTitle ?></h2>
      </div>
      <?php if (!empty($sBreadcrumb)): ?>
      <div class="col-12">
        <ol class="breadcrumb" aria-label="breadcrumbs">
          <li class="breadcrumb-item">
            <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><?= gettext('Home') ?></a>
          </li>
          <?= $sBreadcrumb ?>
        </ol>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
```

---

## Migration Checklist (per page)

1. [ ] Wrap content in `.page-wrapper > .page-body > .container-xl`
2. [ ] Replace `.box/.box-header/.box-body` with `.card/.card-header/.card-body`
3. [ ] Replace `data-toggle/dismiss/target` with `data-bs-*` equivalents
4. [ ] Replace all Bootstrap 4 spacing/flex utilities with BS5 equivalents
5. [ ] Replace UI action icons with Tabler Icons (`ti-`)
6. [ ] Verify domain entity icons use FA7 Duotone
7. [ ] Test DataTables.net still initializes (no changes needed, just CSS skin)
8. [ ] Test jQuery plugins still fire (Select2, InputMask, DatePicker)
9. [ ] Check responsive behaviour at `< md` (volunteer persona)
