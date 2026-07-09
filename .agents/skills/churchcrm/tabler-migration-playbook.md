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

## Codebase Audit Inventory <!-- updated: 2026-03-22 -->

### Bootstrap 4 Data Attributes — ✅ COMPLETE

All `data-toggle`, `data-dismiss`, `data-target`, `data-parent` migrated to `data-bs-*`. **0 occurrences remain** in PHP files.

---

### Bootstrap 4 Spacing Classes — ✅ COMPLETE

All `ml-*`→`ms-*`, `mr-*`→`me-*`, `pl-*`→`ps-*`, `pr-*`→`pe-*` migrated. **0 occurrences remain** in PHP templates (1 false positive in Propel model `Family.php`).

---

### Bootstrap 4 Form Classes — ⚠️ 64 REMAINING

| Pattern | BS5 Equivalent | Remaining | Files |
|---------|---------------|-----------|-------|
| `.form-group` | `<div class="mb-3">` | **2** | Header.php, Functions.php |
| `.custom-select` / `.custom-control` | `.form-select` / `.form-check` | **62** | 12 files (see Phase 7 for breakdown) |
| `.input-group-append/prepend` | Remove wrapper | TBD | Needs audit |

---

### Other BS4 Classes — Mostly Complete

| Pattern | BS5 Equivalent | Remaining | Status |
|---------|---------------|-----------|--------|
| `.float-end/left` | `.float-end/start` | **0** | ✅ Done |
| `.badge-*` (color) | `.bg-*` | **0** | ✅ Done |
| `.text-right/left` | `.text-end/start` | **0** | ✅ Done |
| `.font-weight-*` | `.fw-*` | **0** | ✅ Done |
| `.close` (button) | `.btn-close` | **0** | ✅ Done |
| `.sr-only` | `.visually-hidden` | **0** | ✅ Done |

---

### Legacy AdminLTE Widget Classes

| Pattern | Files | Notes |
|---------|-------|-------|
| `.small-box` / `.small-box-footer` | **0** | ✅ Migrated to Tabler stamp cards |
| `.info-box` | **0** | ✅ Clean |
| `.box / .box-header / .box-body` | **3 files** | Bridge CSS handles. `admin/views/users.php`, `plugins/core/external-backup/views/settings.php`, `plugins/core/mailchimp/views/dashboard.php` |

---

### AdminLTE References — ✅ REMOVED FROM CODE

AdminLTE npm dep removed. No `adminlte.min.js` or `adminlte.min.css` loaded. Bridge CSS (`_tabler-bridge.scss`) and SCSS references (`_ui-components.scss`, `_calendars.scss`) remain for legacy `.box` compat only.

---

### Chart.js — ✅ FULLY REPLACED BY APEXCHARTS

`chart.js` removed from package.json. ApexCharts `^5.10.4` used in all dashboards and chart views (7 files).

---

## Per-Page Migration Procedure

### Before Starting Any Page

1. Read `.claudecode/migration-rules.md`
2. Read `tabler-components.md` for the component you're implementing
3. Read `bootstrap-5-migration.md` for the class/attribute mapping

### Step-by-Step for Each Page

```
1. AUDIT — Count BS4 patterns in the file
   grep -c 'data-toggle\|data-dismiss\|data-target\|ml-\|mr-\|form-group\|badge-\|float-end\|text-right' src/PageName.php

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
   float-end      → float-end
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

### Phase 1: Infrastructure (COMPLETED) <!-- verified: 2026-03-22 -->
- [x] Install `@tabler/core` + `@tabler/icons-webfont` via npm
- [x] Add Grunt copy blocks for Tabler assets
- [x] Run `npm run build:js:legacy` to copy Tabler files
- [x] Remove React ecosystem (react, react-dom, react-bootstrap, react-datepicker, react-select) — **done in 7.2.0**
- [x] Upgrade DataTables BS4 → BS5 (package.json + Gruntfile + SCSS)
- [x] Remove AdminLTE from SCSS imports (keep `_tabler-bridge.scss`)
- [x] Run `npm run build` — verify no errors

### Phase 1.5: jQuery Initialization Race Conditions (COMPLETED) <!-- learned: 2026-03-22 -->

**Issue**: Webpack bundles were using jQuery before `window.jQuery` was set globally, causing runtime errors:
- "Cannot read properties of undefined (reading 'extend')" in MainDashboard.js
- "Cannot read properties of undefined (reading 'ajax')" in CRMJSOM.js  
- "$ is not defined" in IssueReporter.js and Footer.js
- jQuery plugin loading failures (DataTables, DatePicker, InputMask)

**Solution Implemented**:
- [x] Convert `IssueReporter.js` to ES6 module with proper jQuery import
- [x] Replace all `$` references with `window.jQuery` in CRMJSOM.js
- [x] Add jQuery availability guards and deferred initialization in Footer.js, MainDashboard.js, root-dashboard.js
- [x] Return proper Promise rejections when AJAX functions called before jQuery loads
- [x] Add retry logic with 500ms timeout for deferred initializations

**Files Modified**:
- `src/skin/js/IssueReporter.js` — ES6 module with DOM ready guard
- `src/skin/js/CRMJSOM.js` — Remove module-level `$` assignment, use `window.jQuery.ajax()`
- `src/skin/js/Footer.js` — Guard `$(document).ready()` with jQuery availability check
- `src/skin/js/MainDashboard.js` — Defer initialization if `$.extend()` not available
- `webpack/root-dashboard.js` — Guard DOM event handlers with jQuery check

**Testing**:
- [x] Webpack builds with no new errors
- [x] Lint passes
- [x] All jQuery errors resolved in browser console

### Phase 2: Global Sweep — data attributes (COMPLETED) <!-- verified: 2026-03-22 -->
- [x] Bulk find-replace `data-toggle` → `data-bs-toggle` — **0 occurrences remain in PHP**
- [x] Bulk find-replace `data-dismiss` → `data-bs-dismiss`
- [x] Bulk find-replace `data-target` → `data-bs-target`
- [x] Bulk find-replace `data-parent` → `data-bs-parent`
- [x] Update `FooterNotLoggedIn.php` to load Tabler JS instead of AdminLTE
- [x] Run `npm run build` — verify

### Phase 3: Priority Pages (COMPLETED) <!-- verified: 2026-03-22 -->
- [x] **Dashboard** (`src/v2/templates/root/dashboard.php`) — Tabler stamp cards, ApexCharts
- [x] **PersonView** (`src/PersonView.php`) — data attributes + spacing migrated
- [x] **FamilyView** (`src/v2/templates/people/family-view.php`) — data attributes migrated
- [x] **PeopleDashboard** (`src/PeopleDashboard.php`) — stamp cards for member stats, ApexCharts

### Phase 3.5: Chart.js → ApexCharts (COMPLETED) <!-- verified: 2026-03-22 -->
- [x] Chart.js fully removed from package.json
- [x] ApexCharts (`^5.10.4`) installed and used in: MainDashboard.js, DepositSlipEditor.js, root-dashboard, sundayschool-class-view, PeopleDashboard, people/dashboard, groups/sundayschool/dashboard

### Phase 4: Admin Module (PARTIALLY COMPLETE) <!-- verified: 2026-03-22 -->
- [x] `admin/views/debug.php` — data attrs + spacing migrated
- [x] `admin/views/upgrade.php` — data attrs + spacing migrated (still has 3 `custom-control`)
- [x] `admin/views/church-info.php` — migrated
- [x] `admin/views/get-started.php` — migrated
- [~] `admin/views/users.php` — still has legacy `.box` classes (3 files with box patterns remain)
- [x] `admin/views/logs.php` — migrated
- [x] `admin/views/orphaned-files.php` — migrated
- [x] `admin/views/restore.php` — migrated
- [x] `admin/views/csv-import.php` — migrated
- [x] `admin/views/dashboard.php` — migrated
- [~] `admin/views/backup.php` — still has 6 `custom-select/custom-control` occurrences

### Phase 5: Library Swaps (PARTIALLY COMPLETE) <!-- verified: 2026-03-23 -->

**Completed:**
- [x] Replace select2 → tom-select for **person listing filters** — `tom-select@^2.5.2` now wired into webpack bundle `webpack/people/person-list.js` with proper multi-select support (TomSelect CSS + JS imports)
  - Fixed TomSelect API usage in filter handlers (was using incorrect `ts.options[val].text`)
  - Refactored filter initialization order: populate options → initialize TomSelect → set values
  - Clear filters button now uses TomSelect `.clear()` API
  - Initial filter values set via TomSelect `.setValue()` API, not jQuery `.val()`
  - Files changed: `src/v2/templates/people/person-list.php`, `webpack/people/person-list.js`

**Still TODO:**
- [ ] Replace bootstrap-datepicker → flatpickr (8 files) — `bootstrap-datepicker@^1.10.1` still installed
- [ ] Replace daterangepicker → litepicker (8 files) — `daterangepicker@^3.1.0` still installed
- [ ] Replace inputmask → imask (4 files) — `inputmask@^5.0.9` still installed
- [ ] Replace bootbox → confirm-dialog.ts — **28 files still reference bootbox** (up from original 19 estimate)
- [ ] Replace select2 → tom-select for **other pages** (Select2 still active in 3 remaining JS bundles + `src/skin/external/select2/`)
- [ ] Replace moment → dayjs (5 files) — `moment@^2.30.1` still installed
- [ ] Replace notyf → Bootstrap 5 Toast — `notyf@^3.10.0` still installed
- [ ] Replace bs-stepper → Tabler Steps CSS — `bs-stepper@^1.7.0` still installed
- [x] ~~Remove dead deps: react, react-datepicker, react-select, react-bootstrap~~ — **done in 7.2.0**

### Phase 6: Remaining Pages (NOT STARTED) <!-- verified: 2026-03-22 -->

Data attributes are clean (Phase 2 handled globally). Remaining work is `custom-select/custom-control` form classes and legacy `.box` widget classes.

- [ ] `GroupView.php` — box classes remain
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
- [ ] `plugins/views/management.php` — 3 `custom-select/custom-control`
- [ ] `external/templates/verify/*`
- [ ] `setup/templates/setup-steps.php`
- [ ] `session/templates/*`

### Phase 7: Form Pages — `custom-select/custom-control` cleanup (62 remaining) <!-- verified: 2026-03-22 -->

**Verified residual counts** (total: 62 occurrences across 12 files):

| File | Count | Notes |
|------|:---:|-------|
| `PersonEditor.php` | 12 | Heaviest form page |
| `external/templates/registration/family-register.php` | 9 | |
| `Include/Functions.php` | 6 | Shared utility — affects many pages |
| `plugins/core/external-backup/views/settings.php` | 6 | |
| `v2/templates/user/user.php` | 6 | |
| `admin/views/backup.php` | 6 | |
| `kiosk/views/manager.php` | 3 | |
| `FamilyEditor.php` | 3 | |
| `v2/templates/people/photo-gallery.php` | 3 | |
| `admin/views/upgrade.php` | 3 | |
| `plugins/views/management.php` | 3 | |
| `DepositSlipEditor.php` | 2 | |

Also: `form-group` reduced to **2 occurrences** (Header.php modal, Functions.php).

### Phase 8: Global Cleanup (MOSTLY COMPLETE) <!-- verified: 2026-03-22 -->

| Pattern | Status | Remaining |
|---------|--------|-----------|
| `ml-*` → `ms-*` | ✅ Done | 0 in PHP templates (1 false positive in Propel model) |
| `mr-*` → `me-*` | ✅ Done | 0 |
| `pl-*` → `ps-*` | ✅ Done | 0 |
| `pr-*` → `pe-*` | ✅ Done | 0 |
| `badge-*` → `bg-*` | ✅ Done | 0 |
| `float-end/left` → `float-end/start` | ✅ Done | 0 |
| `text-right/left` → `text-end/start` | ✅ Done | 0 |
| `.close` → `.btn-close` | ✅ Done | 0 (`class="close"` not found) |
| `.sr-only` → `.visually-hidden` | ✅ Done | 0 |
| `.font-weight-*` → `.fw-*` | ⚠️ Remaining | **25 occurrences in 6 files** (dashboards + Header.php) |

Remaining `font-weight-` files:
- `people/views/dashboard.php` (8)
- `groups/views/sundayschool/dashboard.php` (6)
- `groups/views/dashboard.php` (4)
- `v2/templates/root/dashboard.php` (5)
- `admin/views/dashboard.php` (1)
- `Include/Header.php` (1)

⚠️ **WARNING**: Previous bulk sed run (`2026-03-22`) used unsafe regex `s/ +"/"/g` which corrupted PHP string literals in non-template files. When re-running bulk migration, use ONLY the safe patterns documented in `bootstrap-5-migration.md` § "Bulk Sed Migration". Backend PHP files (Services, Utils, Models, APIs) were reverted to HEAD after corruption was discovered.

### Phase 9: MenuRenderer Rewrite (COMPLETED) <!-- verified: 2026-03-22 -->
- [x] Update `src/ChurchCRM/view/MenuRenderer.php` to output Tabler-compatible nav HTML
  - 0 occurrences of `nav-icon`, `nav-treeview`, or `menu-open` remain in PHP files

### Phase 10: Final Cleanup (NOT STARTED)
- [x] Remove `admin-lte` from package.json — **already removed**
- [x] Bootstrap upgraded to 5.3.8 — `bootstrap@^4.6.2` replaced with `bootstrap@^5.3.8`
- [ ] Delete `src/skin/external/adminlte/` — check if directory still exists
- [ ] Delete `src/skin/external/bootstrap/` — check if directory still exists
- [ ] Remove all AdminLTE Grunt copy blocks — verify no remaining references
- [ ] Remove `_tabler-bridge.scss` — still needed (3 files with `.box` classes remain)
- [ ] Mobile responsive audit
- [ ] Print layout audit
- [ ] Accessibility audit (ARIA labels)
- [ ] Cypress test selector updates
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
