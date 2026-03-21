---
title: "Tabler Migration Playbook"
intent: "Step-by-step playbook for migrating ChurchCRM pages from AdminLTE/BS4 to Tabler/BS5, with full codebase audit inventory and phased execution plan"
tags: ["frontend", "tabler", "migration", "bootstrap5", "playbook"]
prereqs: ["tabler-components.md", "tabler-library-replacement.md", "bootstrap-5-migration.md"]
complexity: "advanced"
---

# Skill: Tabler Migration Playbook <!-- learned: 2026-03-21 -->

## Overview

This playbook is the operational guide for migrating ChurchCRM page-by-page from AdminLTE/Bootstrap 4 to Tabler/Bootstrap 5. It includes the exact codebase audit inventory, per-page instructions, and a phased execution plan.

**Epic Issue**: [#8301 — UI Migration: AdminLTE to Tabler 2026](https://github.com/ChurchCRM/CRM/issues/8301)
**Skill files**: `.claudecode/migration-rules.md` (agent rules), `tabler-components.md` (component reference), `tabler-library-replacement.md` (library swaps), `bootstrap-5-migration.md` (BS4→BS5 mapping)

---

## Codebase Audit Inventory

### Bootstrap 4 Data Attributes (MUST FIX — breaks JS behavior)

**Total: ~116 occurrences across 22 files**

| Pattern | Count | Files |
|---------|-------|-------|
| `data-toggle` | 63 | 22 files |
| `data-dismiss` | 18 | ~14 files |
| `data-target` | 25 | ~15 files |
| `data-parent` | 10 | ~5 files |

**Priority files (most occurrences):**

| File | `data-toggle` | `data-dismiss` | `data-target` | Total |
|------|:---:|:---:|:---:|:---:|
| `PersonView.php` | 9 | 2 | 4 | 15 |
| `admin/views/debug.php` | 9 | 1 | 9 | 19 |
| `setup/templates/setup-steps.php` | 7 | 1 | 7 | 15 |
| `admin/views/upgrade.php` | 5 | 1 | 5 | 11 |
| `GroupView.php` | 5 | 1 | 3 | 9 |
| `v2/templates/people/family-view.php` | 4 | 1 | 3 | 8 |
| `admin/views/church-info.php` | 3 | 0 | 2 | 5 |
| `admin/views/get-started.php` | 3 | 0 | 2 | 5 |
| `v2/templates/calendar/calendar.php` | 2 | 0 | 1 | 3 |
| `Checkin.php` | 2 | 1 | 1 | 4 |
| `PeopleDashboard.php` | 2 | 0 | 0 | 2 |
| `SystemSettings.php` | 2 | 0 | 2 | 4 |

**Regex for find-and-replace:**
```
data-toggle="  →  data-bs-toggle="
data-dismiss=" →  data-bs-dismiss="
data-target="  →  data-bs-target="
data-parent="  →  data-bs-parent="
```

---

### Bootstrap 4 Spacing Classes (cosmetic — won't break, but look wrong)

**Total: ~490 occurrences across 30 files**

| Pattern | BS5 Equivalent | Count |
|---------|---------------|-------|
| `ml-*` | `ms-*` | ~200 |
| `mr-*` | `me-*` | ~180 |
| `pl-*` | `ps-*` | ~60 |
| `pr-*` | `pe-*` | ~50 |

**Top files:**
1. `PersonView.php` — 44 occurrences
2. `admin/views/upgrade.php` — 40
3. `admin/views/debug.php` — 36
4. `external/templates/registration/family-register.php` — 25
5. `plugins/views/management.php` — 24

---

### Bootstrap 4 Form Classes

| Pattern | BS5 Equivalent | Count | Files |
|---------|---------------|-------|-------|
| `.form-group` | `<div class="mb-3">` | 194 | 41 |
| `.custom-select` | `.form-select` | ~30 | 12 |
| `.custom-control` | `.form-check` | ~32 | 12 |
| `.input-group-append/prepend` | Remove wrapper | 9 | 9 |

---

### Other BS4 Classes

| Pattern | BS5 Equivalent | Count | Files |
|---------|---------------|-------|-------|
| `.float-right` | `.float-end` | ~38 | 13 |
| `.float-left` | `.float-start` | ~5 | 4 |
| `.badge-*` (color) | `.bg-*` | ~115 | 31 |
| `.text-right` | `.text-end` | ~80 | ~35 |
| `.text-left` | `.text-start` | ~70 | ~38 |
| `.font-weight-*` | `.fw-*` | ~20 | ~10 |
| `.close` (button) | `.btn-close` | ~18 | 14 |
| `.sr-only` | `.visually-hidden` | ~5 | ~3 |
| `thead-dark/light` | `.table-dark/light` on `<thead>` | ~3 | ~2 |
| `.no-gutters` | `.g-0` | ~2 | ~2 |

---

### Legacy AdminLTE Widget Classes (orphaned)

| Pattern | Files | Notes |
|---------|-------|-------|
| `.small-box-footer` | `PeopleDashboard.php` | 4 occurrences. Replace with Tabler card footer. |
| `.info-box` | 0 files | Clean |
| `.box / .box-header / .box-body` | 7 files | Bridge CSS handles these. Migrate when touching file. |

---

### AdminLTE JS Reference (remaining)

| File | Reference |
|------|-----------|
| `src/Include/FooterNotLoggedIn.php` | Loads `adminlte.min.js` — needs Tabler equivalent for login pages |

---

## Per-Page Migration Procedure

### Before Starting Any Page

1. Read `.claudecode/migration-rules.md`
2. Read `tabler-components.md` for the component you're implementing
3. Read `bootstrap-5-migration.md` for the class/attribute mapping

### Step-by-Step for Each Page

```
1. AUDIT — Count BS4 patterns in the file
   grep -c 'data-toggle\|data-dismiss\|data-target\|ml-\|mr-\|form-group\|badge-\|float-right\|text-right' src/PageName.php

2. STRUCTURE — Wrap content (if not already from Header.php)
   - Content should be inside .page-body > .container-xl (provided by Header.php)
   - Replace any local .content-wrapper / .content-header patterns

3. DATA ATTRIBUTES — Find and replace
   data-toggle=   →  data-bs-toggle=
   data-dismiss=  →  data-bs-dismiss=
   data-target=   →  data-bs-target=
   data-parent=   →  data-bs-parent=

4. CARDS — Replace box/info-box patterns
   .box            → .card
   .box-header     → .card-header
   .box-body       → .card-body
   .box-footer     → .card-footer
   .box-title      → .card-title
   .box-tools      → .card-actions
   .small-box      → .card .card-sm + .card-stamp

5. SPACING — Replace margin/padding
   ml-  → ms-     mr-  → me-
   pl-  → ps-     pr-  → pe-

6. LAYOUT — Replace alignment/float
   float-right      → float-end
   float-left       → float-start
   text-right       → text-end
   text-left        → text-start
   font-weight-bold → fw-bold
   font-weight-*    → fw-*

7. BADGES — Replace color variants
   badge-primary  → bg-primary
   badge-success  → bg-success
   badge-danger   → bg-danger
   badge-warning  → bg-warning
   badge-info     → bg-info

8. BUTTONS — Replace close buttons
   <button class="close" ...>&times;</button>
   →
   <button class="btn-close" data-bs-dismiss="..."></button>

9. FORMS — Replace deprecated form classes
   .form-group               → <div class="mb-3">
   .custom-select            → .form-select
   .custom-control-input     → .form-check-input
   .custom-control-label     → .form-check-label
   .input-group-append       → remove wrapper, keep children in .input-group
   .input-group-prepend      → remove wrapper, keep children in .input-group

10. ICONS — Replace UI action icons
    fa-edit       → ti ti-pencil
    fa-trash      → ti ti-trash
    fa-save       → ti ti-device-floppy
    fa-times      → ti ti-x
    fa-search     → ti ti-search
    fa-plus       → ti ti-plus
    fa-cogs       → ti ti-settings
    fa-download   → ti ti-download
    (Domain entity icons stay as FA7 duotone)

11. TABLES — Apply Tabler table classes
    Add: table-vcenter, table-sm (if admin), table-hover
    Wrap in: .table-responsive
    For card: use .card-table

12. TEST — Open page in browser
    - Check all dropdowns open/close
    - Check modals open/close
    - Check DataTables initialize
    - Check Select2/Tom Select works
    - Check mobile responsive at < 768px
    - Check print layout (d-print-none hides nav)
```

---

## Phased Execution Plan

### Phase 0: Foundation (COMPLETED)
- [x] Create `.claudecode/migration-rules.md`
- [x] Create `src/skin/scss/_tabler-bridge.scss`
- [x] Refactor `Header.php` → Tabler shell
- [x] Refactor `Footer.php` → Tabler shell
- [x] Update `Header-HTML-Scripts.php`
- [x] Create skill files

### Phase 1: Infrastructure (Next)
- [ ] Install `@tabler/core` + `@tabler/icons-webfont` via npm
- [ ] Add Grunt copy blocks for Tabler assets
- [ ] Run `npm run build:js:legacy` to copy Tabler files
- [ ] Remove dead npm deps (quill, react-datepicker, react-select, react-bootstrap)
- [ ] Upgrade DataTables BS4 → BS5 (package.json + Gruntfile + SCSS)
- [ ] Remove AdminLTE from SCSS imports (keep `_tabler-bridge.scss`)
- [ ] Run `npm run build` — verify no errors

### Phase 2: Global Sweep (data attributes)
- [ ] Bulk find-replace `data-toggle` → `data-bs-toggle` in all 22 files
- [ ] Bulk find-replace `data-dismiss` → `data-bs-dismiss`
- [ ] Bulk find-replace `data-target` → `data-bs-target`
- [ ] Bulk find-replace `data-parent` → `data-bs-parent`
- [ ] Update `FooterNotLoggedIn.php` to load Tabler JS instead of AdminLTE
- [ ] Run `npm run build` — verify

### Phase 3: Priority Pages
- [ ] **Dashboard** (`src/v2/templates/root/dashboard.php`) — Leader persona
  - Replace `.small-box` with Tabler stamp cards
  - Replace `.box` with `.card`
  - Apply status cards for KPIs
- [ ] **PersonView** (`src/PersonView.php`) — Admin persona (44 spacing changes)
  - Dense card layout
  - Tabs → Tabler card tabs
  - All 15 data-attribute fixes
- [ ] **FamilyView** (`src/v2/templates/people/family-view.php`)
  - Card layout
  - 8 data-attribute fixes
- [ ] **PeopleDashboard** (`src/PeopleDashboard.php`)
  - Replace `.small-box-footer`
  - Stamp cards for member stats

### Phase 4: Admin Module
- [ ] `admin/views/debug.php` (36 spacing + 19 data attributes)
- [ ] `admin/views/upgrade.php` (40 spacing + 11 data attributes)
- [ ] `admin/views/church-info.php`
- [ ] `admin/views/get-started.php`
- [ ] `admin/views/users.php`
- [ ] `admin/views/logs.php`
- [ ] `admin/views/orphaned-files.php`
- [ ] `admin/views/restore.php`
- [ ] `admin/views/csv-import.php`
- [ ] `admin/views/dashboard.php`

### Phase 5: Library Swaps
- [ ] Replace bootstrap-datepicker → flatpickr (8 files)
- [ ] Replace daterangepicker → litepicker (8 files)
- [ ] Replace inputmask → imask (4 files)
- [ ] Replace bootbox → confirm-dialog.ts (19 files)
- [ ] Replace select2 → tom-select (19 files)
- [ ] Replace moment → dayjs (5 files)

### Phase 6: Remaining Pages
- [ ] `GroupView.php` (9 data-attribute fixes)
- [ ] `Checkin.php`
- [ ] `EventEditor.php`
- [ ] `ManageEnvelopes.php`
- [ ] `PropertyTypeList.php`
- [ ] `SystemSettings.php`
- [ ] `groups/views/sundayschool/class-view.php`
- [ ] `groups/views/sundayschool/dashboard.php`
- [ ] `finance/views/dashboard.php`
- [ ] `v2/templates/calendar/calendar.php`
- [ ] `v2/templates/map/map-view.php`
- [ ] `plugins/views/management.php`
- [ ] `external/templates/verify/*`
- [ ] `setup/templates/setup-steps.php`
- [ ] `session/templates/*`

### Phase 7: Form Pages (heaviest work)
- [ ] `PersonEditor.php` (43 form-group occurrences)
- [ ] `FamilyEditor.php` (19 form-group)
- [ ] `DonatedItemEditor.php` (14 form-group)
- [ ] `external/templates/registration/family-register.php` (25 spacing + 28 form-group)
- [ ] All remaining editor pages

### Phase 8: Global Cleanup
- [ ] Bulk replace `ml-*` → `ms-*` across all files
- [ ] Bulk replace `mr-*` → `me-*` across all files
- [ ] Bulk replace `badge-*` → `bg-*` across all files
- [ ] Bulk replace `float-right` → `float-end`
- [ ] Bulk replace `text-right` → `text-end`, `text-left` → `text-start`
- [ ] Bulk replace `.close` button → `.btn-close`
- [ ] Bulk replace `.font-weight-*` → `.fw-*`
- [ ] Bulk replace `.sr-only` → `.visually-hidden`

### Phase 9: MenuRenderer Rewrite
- [ ] Update `src/ChurchCRM/view/MenuRenderer.php` to output Tabler-compatible nav HTML
  - `nav-icon` → `nav-link-icon`
  - `nav-treeview` → `dropdown-menu`
  - `fa-solid fa-angle-left` → Remove (Tabler uses `dropdown-toggle` CSS)
  - `menu-open` → managed by `data-bs-auto-close="false"`
  - `<p>` wrapper → `<span class="nav-link-title">`

### Phase 10: Final
- [ ] Remove `admin-lte` from package.json
- [ ] Remove `bootstrap@^4.6.2` from package.json
- [ ] Delete `src/skin/external/adminlte/`
- [ ] Delete `src/skin/external/bootstrap/`
- [ ] Remove all AdminLTE Grunt copy blocks
- [ ] Remove `_tabler-bridge.scss` (no longer needed once all legacy classes are gone)
- [ ] Mobile responsive audit
- [ ] Print layout audit
- [ ] Accessibility audit (ARIA labels)
- [ ] Cypress test selector updates

---

## Quick Reference: Regex Patterns for Bulk Migration

```bash
# Data attributes (run on src/ directory)
find src -name "*.php" -exec sed -i '' 's/data-toggle="/data-bs-toggle="/g' {} +
find src -name "*.php" -exec sed -i '' 's/data-dismiss="/data-bs-dismiss="/g' {} +
find src -name "*.php" -exec sed -i '' 's/data-target="/data-bs-target="/g' {} +
find src -name "*.php" -exec sed -i '' 's/data-parent="/data-bs-parent="/g' {} +

# Spacing (careful — only replace class attributes, not arbitrary text)
# Best done per-file with manual review, not blind global replace.

# Badges
find src -name "*.php" -exec sed -i '' 's/badge-primary/bg-primary/g' {} +
find src -name "*.php" -exec sed -i '' 's/badge-success/bg-success/g' {} +
find src -name "*.php" -exec sed -i '' 's/badge-danger/bg-danger/g' {} +
find src -name "*.php" -exec sed -i '' 's/badge-warning/bg-warning/g' {} +
find src -name "*.php" -exec sed -i '' 's/badge-info/bg-info/g' {} +
find src -name "*.php" -exec sed -i '' 's/badge-secondary/bg-secondary/g' {} +
```

---

## Validation Checklist (per phase)

After each phase:
1. `npm run build` — must succeed
2. `npm run lint` — no new errors
3. Open 3+ pages in browser — visual check
4. Test dropdowns, modals, tabs — JS behavior
5. Test at mobile width (< 768px) — responsive
6. Run Cypress smoke test if available
