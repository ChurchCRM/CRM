---
title: "Tabler Library Replacement Guide"
intent: "Which 3rd-party UI libraries to replace with Tabler built-ins, how to do it via webpack, and which to keep"
tags: ["frontend", "tabler", "webpack", "migration", "libraries"]
prereqs: ["tabler-components.md", "webpack-typescript.md", "bootstrap-5-migration.md"]
complexity: "advanced"
---

# Skill: Library Replacement Guide — AdminLTE → Tabler <!-- learned: 2026-03-21 -->

## Overview

ChurchCRM currently loads **16+ 3rd-party UI libraries** via Grunt (copied to `src/skin/external/`) and webpack. This skill documents which libraries Tabler replaces, which to keep, and the exact npm/webpack changes needed.

**Golden rule**: Everything via webpack or Grunt copy. No CDN links in production. Every asset tracked in `package.json`.

---

## Current Build Pipeline

```
npm install → node_modules/
      ↓
Grunt copy → src/skin/external/  (vendored JS/CSS loaded via <script>/<link>)
      ↓
Webpack → src/skin/v2/  (bundled JS/CSS loaded via <script>/<link>)
      ↓
SCSS → churchcrm.min.css  (all CSS combined)
```

**Grunt** copies pre-built vendor files to `src/skin/external/` for direct `<script>` loading.
**Webpack** bundles TypeScript/React modules and SCSS into `src/skin/v2/`.

---

## Replacement Decision Matrix

### REPLACE — Tabler has a built-in equivalent

| Current Library | npm Package | Tabler Replacement | npm Install | Effort |
|----------------|-------------|-------------------|-------------|--------|
| **Bootstrap DatePicker** | `bootstrap-datepicker@^1.10.1` | **Flatpickr** | `flatpickr` | Low |
| **DateRangePicker** | `daterangepicker@^3.1.0` | **Litepicker** | `litepicker` | Low |
| **Select2** | `select2@^4.0.13` | **Tom Select** | `tom-select` | Medium |
| **InputMask** | `inputmask@^5.0.9` | **IMask** | `imask` | Low |
| **Bootbox** | `bootbox@^6.0.4` | **Bootstrap 5 Modal** (+ thin wrapper) | Built-in | Medium |
| **BS Stepper** | `bs-stepper@^1.7.0` | **Tabler Steps** (CSS) + custom JS | Built-in | Low |
| **AdminLTE** | `admin-lte@3.2.0` | **Tabler CSS/JS** | `@tabler/core` | Already done |
| **Bootstrap 4** | `bootstrap@^4.6.2` | **Bootstrap 5** (via Tabler) | `@tabler/core` | Already done |
| **Notyf** | `notyf@^3.10.0` | **Bootstrap 5 Toast** | Built-in | Low |
| **Moment.js** | `moment@^2.30.1` | **Day.js** | `dayjs` | Medium |

### KEEP — No Tabler equivalent or too embedded

| Library | npm Package | Why Keep |
|---------|-------------|----------|
| **DataTables.net** | `datatables.net@^2.3.7` + extensions | Too feature-rich to replace (export, server-side, row-select). Upgrade BS4→BS5 integration. |
| **jQuery** | `jquery@^3.7.1` | Deeply embedded. BS5 auto-detects it. Keep. |
| **Chart.js** | `chart.js@^4.5.1` | Works fine. Tabler recommends ApexCharts but migration is optional. |
| **FullCalendar** | `fullcalendar@^6.1.19` | Tabler also uses FullCalendar. Already correct. |
| **Leaflet** | `leaflet@^1.9.4` | No Tabler map equivalent for street-level maps. Keep. |
| **i18next** | `i18next@^25.8.18` | Core infrastructure. No replacement. |
| **Font Awesome** | `@fortawesome/fontawesome-free@^7.2.0` | 1,060+ uses. Gradual migration to Tabler Icons for UI actions only. |
| **Flag Icons** | `flag-icons@^7.5.0` | Only 2 uses. Low priority. Keep. |
| **JustValidate** | `just-validate@^4.3.0` | Only 4 uses but needed for wizard. Keep until full Tabler form validation. |
| **Uppy** | `@uppy/*` | Photo upload. No Tabler equivalent. Keep. |
| **jszip/pdfmake** | `jszip@^3.10.1`, `pdfmake@^0.3.6` | DataTables export dependencies. Keep with DataTables. |

### REMOVE — Unused / dead dependencies

| Library | npm Package | Reason |
|---------|-------------|--------|
| **Quill** | `quill@2.0.3` | Zero imports found. Dead code. |
| **react-datepicker** | `react-datepicker@^9.1.0` | No imports found. |
| **react-select** | `react-select@^5.10.2` | No imports found. |
| **react-bootstrap** | `react-bootstrap@^2.10.10` | No imports found. |
| **select2-bootstrap4-theme** | `@ttskch/select2-bootstrap4-theme@^1.5.2` | Obsolete with Select2 removal. |

---

## Replacement Implementation Details

### 1. Bootstrap DatePicker → Flatpickr

**Install:**
```bash
npm install flatpickr
npm uninstall bootstrap-datepicker
```

**Webpack (skin-main or per-page entry):**
```js
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
```

**SCSS (churchcrm.scss):**
```scss
// Remove this:
// @import "external/bootstrap-datepicker/bootstrap-datepicker.standalone.min.css";
```

**Gruntfile.js:** Remove the `bootstrap-datepicker` copy block.

**PHP template change:**
```html
<!-- OLD -->
<input type="text" class="form-control date-picker" data-provide="datepicker">

<!-- NEW -->
<input type="text" class="form-control" data-flatpickr>
```

**JS initialization (if not using data attributes):**
```js
flatpickr('.date-picker', {
  dateFormat: window.CRM.datePickerformat,
  locale: window.CRM.shortLocale
});
```

**Files to update:** 8 files use DatePicker.

---

### 2. DateRangePicker → Litepicker

**Install:**
```bash
npm install litepicker
npm uninstall daterangepicker
```

**Webpack:**
```js
import { Litepicker } from 'litepicker';
```

**SCSS:**
```scss
// Remove this:
// @import "external/bootstrap-daterangepicker/daterangepicker.css";
```

**Gruntfile.js:** Remove the `bootstrap-daterangepicker` copy block.

**JS change:**
```js
// OLD
$('#dateRange').daterangepicker({ startDate: start, endDate: end });

// NEW
new Litepicker({
  element: document.getElementById('dateRange'),
  singleMode: false,
  format: 'YYYY-MM-DD'
});
```

**Files to update:** 8 files.

---

### 3. Select2 → Tom Select

**Install:**
```bash
npm install tom-select
npm uninstall select2 @ttskch/select2-bootstrap4-theme
```

**Webpack:**
```js
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';
```

**SCSS:**
```scss
// Remove this:
// @import "external/select2/select2.min.css";
```

**Gruntfile.js:** Remove the `select2` copy block.

**JS migration pattern:**
```js
// OLD (Select2)
$('.person-select').select2({
  ajax: { url: '/api/persons/search', dataType: 'json' },
  minimumInputLength: 2
});

// NEW (Tom Select)
new TomSelect('.person-select', {
  valueField: 'id',
  labelField: 'name',
  searchField: 'name',
  load: function(query, callback) {
    fetch(`/api/persons/search?query=${encodeURIComponent(query)}`)
      .then(r => r.json())
      .then(callback);
  }
});
```

**Files to update:** 19 files, 61 usages. This is the biggest migration.

---

### 4. InputMask → IMask

**Install:**
```bash
npm install imask
npm uninstall inputmask
```

**Webpack:**
```js
import IMask from 'imask';
```

**Gruntfile.js:** Remove the `inputmask` copy block.

**PHP template (Tabler data attribute approach):**
```html
<!-- OLD -->
<input type="text" data-inputmask="'mask': '(999) 999-9999'">

<!-- NEW (Tabler convention) -->
<input type="text" data-mask="(000) 000-0000" data-mask-visible="true">
```

**Or JS approach:**
```js
IMask(document.getElementById('phone'), { mask: '(000) 000-0000' });
```

**Files to update:** 4 files, 9 usages.

---

### 5. Bootbox → Custom Tabler Modal Wrapper

**Install:** Nothing — uses built-in Bootstrap 5 Modal.

**Uninstall:**
```bash
npm uninstall bootbox
```

**Gruntfile.js:** Remove the `bootbox` copy block.

**Create `webpack/confirm-dialog.ts`:**
```typescript
/**
 * Tabler-styled confirmation dialog replacing Bootbox.
 * Usage: confirmDialog({ title, message, onConfirm })
 */
export function confirmDialog(opts: {
  title?: string;
  message: string;
  confirmText?: string;
  confirmClass?: string;
  onConfirm: () => void;
  onCancel?: () => void;
}) {
  const id = 'confirm-' + Date.now();
  const html = `
    <div class="modal fade" id="${id}" tabindex="-1">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-status bg-danger"></div>
          <div class="modal-body text-center py-4">
            <i class="ti ti-alert-triangle text-danger mb-2" style="font-size:3rem;"></i>
            ${opts.title ? `<h3>${opts.title}</h3>` : ''}
            <p class="text-secondary">${opts.message}</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-link link-secondary" data-bs-dismiss="modal">
              Cancel
            </button>
            <button class="btn ${opts.confirmClass || 'btn-danger'} ms-auto" id="${id}-ok">
              ${opts.confirmText || 'Confirm'}
            </button>
          </div>
        </div>
      </div>
    </div>`;

  document.body.insertAdjacentHTML('beforeend', html);
  const el = document.getElementById(id)!;
  const modal = new bootstrap.Modal(el);

  el.querySelector(`#${id}-ok`)!.addEventListener('click', () => {
    modal.hide();
    opts.onConfirm();
  });
  el.addEventListener('hidden.bs.modal', () => {
    el.remove();
    opts.onCancel?.();
  });

  modal.show();
}

export function alertDialog(message: string, callback?: () => void) {
  confirmDialog({
    message,
    confirmText: 'OK',
    confirmClass: 'btn-primary',
    onConfirm: () => callback?.(),
  });
}
```

**Migration pattern:**
```js
// OLD
bootbox.confirm("Delete this person?", function(result) {
  if (result) { deletePerson(id); }
});

// NEW
import { confirmDialog } from './confirm-dialog';
confirmDialog({
  message: "Delete this person?",
  onConfirm: () => deletePerson(id)
});
```

**Files to update:** 19 files, 33 usages.

---

### 6. Moment.js → Day.js

**Install:**
```bash
npm install dayjs
npm uninstall moment
```

**Gruntfile.js:** Remove the `moment` copy block.

**JS migration:**
```js
// OLD
moment(date).format('YYYY-MM-DD');
moment(date).fromNow();

// NEW — API-compatible
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

dayjs(date).format('YYYY-MM-DD');
dayjs(date).fromNow();
```

**Note:** Day.js has a Moment.js-compatible API. Most code is search-and-replace.

**Files to update:** 5 files.

---

### 7. DataTables: BS4 → BS5 Integration

**Do NOT remove DataTables.** Upgrade the Bootstrap integration only.

**Install:**
```bash
npm install datatables.net-bs5 datatables.net-buttons-bs5 datatables.net-responsive-bs5 datatables.net-select-bs5
npm uninstall datatables.net-bs4 datatables.net-buttons-bs4 datatables.net-responsive-bs4 datatables.net-select-bs4
```

**Gruntfile.js:** Update all DataTables copy paths from `-bs4` to `-bs5`:
```js
// OLD
"node_modules/datatables.net-bs4/js/dataTables.bootstrap4.min.js"
"node_modules/datatables.net-bs4/css/dataTables.bootstrap4.min.css"

// NEW
"node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js"
"node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css"
```

**SCSS (churchcrm.scss):** Update CSS imports:
```scss
// OLD
@import "external/datatables/dataTables.bootstrap4.min.css";
@import "external/datatables/buttons.bootstrap4.min.css";
@import "external/datatables/responsive.bootstrap4.min.css";
@import "external/datatables/select.bootstrap4.min.css";

// NEW
@import "external/datatables/dataTables.bootstrap5.min.css";
@import "external/datatables/buttons.bootstrap5.min.css";
@import "external/datatables/responsive.bootstrap5.min.css";
@import "external/datatables/select.bootstrap5.min.css";
```

**Footer.php:** Already updated (references `bootstrap5` variants).

**Gruntfile patchDataTablesCSS:** Update the file path:
```js
var filePath = "src/skin/external/datatables/dataTables.bootstrap5.min.css";
```

---

### 8. Tabler Core via npm

**Install:**
```bash
npm install @tabler/core @tabler/icons-webfont
```

**Gruntfile.js — Add Tabler copy blocks:**
```js
// Tabler CSS + JS
{
  expand: true,
  filter: "isFile",
  flatten: true,
  src: [
    "node_modules/@tabler/core/dist/css/tabler.min.css",
    "node_modules/@tabler/core/dist/css/tabler.rtl.min.css",
    "node_modules/@tabler/core/dist/js/tabler.min.js",
  ],
  dest: "src/skin/external/tabler/",
},
// Tabler Icons webfont
{
  expand: true,
  cwd: "node_modules/@tabler/icons-webfont/dist",
  src: ["**"],
  dest: "src/skin/external/tabler-icons/",
},
```

**Header-HTML-Scripts.php — Local references (no CDN):**
```php
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/tabler/tabler.min.css') ?>">
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/tabler-icons/tabler-icons.min.css') ?>">
```

**OR bundle into webpack SCSS:**
```scss
// In churchcrm.scss (before custom styles)
@import "~@tabler/core/dist/css/tabler.min.css";
@import "~@tabler/icons-webfont/dist/tabler-icons.min.css";
```

---

## package.json Changes Summary

### Add
```json
"@tabler/core": "^1.4.0",
"@tabler/icons-webfont": "^3.40.0",
"tom-select": "^2.4.3",
"flatpickr": "^4.6.13",
"litepicker": "^2.0.12",
"imask": "^7.6.1",
"dayjs": "^1.11.13"
```

### Remove
```json
"admin-lte": "3.2.0",
"bootstrap": "^4.6.2",
"@ttskch/select2-bootstrap4-theme": "^1.5.2",
"select2": "^4.0.13",
"bootstrap-datepicker": "^1.10.1",
"daterangepicker": "^3.1.0",
"inputmask": "^5.0.9",
"bootbox": "^6.0.4",
"moment": "^2.30.1",
"notyf": "^3.10.0",
"quill": "2.0.3",
"react-datepicker": "^9.1.0",
"react-select": "^5.10.2",
"react-bootstrap": "^2.10.10",
"datatables.net-bs4": "^2.3.7",
"datatables.net-buttons-bs4": "^3.2.6",
"datatables.net-responsive-bs4": "^3.0.8",
"datatables.net-select-bs4": "^3.1.3"
```

### Upgrade (BS4 → BS5 variants)
```json
"datatables.net-bs5": "^2.3.7",
"datatables.net-buttons-bs5": "^3.2.6",
"datatables.net-responsive-bs5": "^3.0.8",
"datatables.net-select-bs5": "^3.1.3"
```

---

## Gruntfile.js Changes Summary

### Remove Copy Blocks
- `admin-lte` → replaced by `@tabler/core`
- `bootstrap/dist/js/` → replaced by Tabler JS
- `bootstrap-datepicker` → replaced by flatpickr
- `bootstrap-daterangepicker` → replaced by litepicker
- `select2` → replaced by tom-select
- `inputmask` → replaced by imask
- `bootbox` → replaced by custom confirm-dialog.ts
- `moment` → replaced by dayjs
- `bs-stepper` → replaced by Tabler Steps CSS

### Add Copy Blocks
- `@tabler/core/dist/css/` + `dist/js/` → `src/skin/external/tabler/`
- `@tabler/icons-webfont/dist/` → `src/skin/external/tabler-icons/`
- All `datatables.net-bs4` references → `datatables.net-bs5`

### Update patchDataTablesCSS
- `dataTables.bootstrap4.min.css` → `dataTables.bootstrap5.min.css`

---

## churchcrm.scss Changes Summary

### Remove Imports
```scss
// @import "external/bootstrap-datepicker/bootstrap-datepicker.standalone.min.css";
// @import "external/bootstrap-daterangepicker/daterangepicker.css";
// @import "external/datatables/dataTables.bootstrap4.min.css";
// @import "external/datatables/buttons.bootstrap4.min.css";
// @import "external/datatables/responsive.bootstrap4.min.css";
// @import "external/datatables/select.bootstrap4.min.css";
// @import "external/select2/select2.min.css";
// @import "external/adminlte/adminlte.min.css";
// @import "external/bs-stepper/bs-stepper.min.css";
```

### Add Imports
```scss
@import "external/datatables/dataTables.bootstrap5.min.css";
@import "external/datatables/buttons.bootstrap5.min.css";
@import "external/datatables/responsive.bootstrap5.min.css";
@import "external/datatables/select.bootstrap5.min.css";
```

**Note:** Tabler CSS and icons are loaded via `<link>` in Header-HTML-Scripts.php (loaded before churchcrm.min.css) so they don't need SCSS import.

---

## Footer.php Script Changes Summary

### Remove
```html
<!-- <script src="...bootstrap/js/bootstrap.bundle.min.js"> -->
<!-- <script src="...adminlte/adminlte.min.js"> -->
<!-- <script src="...inputmask/jquery.inputmask.min.js"> -->
<!-- <script src="...inputmask/inputmask.binding.js"> -->
<!-- <script src="...bootstrap-datepicker/bootstrap-datepicker.min.js"> -->
<!-- <script src="...bootstrap-daterangepicker/daterangepicker.js"> -->
<!-- <script src="...select2/select2.full.min.js"> -->
<!-- <script src="...bootbox/bootbox.min.js"> -->
<!-- <script src="...bs-stepper/bs-stepper.min.js"> (if present) -->
```

### Add
```html
<script src="<?= SystemURLs::assetVersioned('/skin/external/tabler/tabler.min.js') ?>"></script>
```

### Update (BS4 → BS5)
```html
<!-- dataTables.bootstrap4.min.js → dataTables.bootstrap5.min.js -->
<!-- buttons.bootstrap4.min.js → buttons.bootstrap5.min.js -->
<!-- responsive.bootstrap4.min.js → responsive.bootstrap5.min.js -->
<!-- select.bootstrap4.min.js → select.bootstrap5.min.js -->
```

### Keep Unchanged
```html
<script src="...datatables/dataTables.min.js"></script>
<script src="...datatables/dataTables.buttons.min.js"></script>
<script src="...datatables/buttons.html5.min.js"></script>
<script src="...datatables/buttons.print.min.js"></script>
<script src="...datatables/dataTables.responsive.min.js"></script>
<script src="...datatables/dataTables.select.min.js"></script>
<script src="...datatables/jszip.min.js"></script>
<script src="...datatables/pdfmake.min.js"></script>
<script src="...datatables/vfs_fonts.js"></script>
<script src="...chartjs/chart.umd.js"></script>
<script src="...fullcalendar/index.global.min.js"></script>
<script src="...i18next/i18next.min.js"></script>
<script src="...just-validate/just-validate.production.min.js"></script>
<script src="...leaflet/leaflet.js"></script> <!-- page-specific -->
```

---

## Migration Order (Recommended)

| Phase | Library Swap | Files | Risk |
|-------|-------------|-------|------|
| **0** | Install `@tabler/core` + `@tabler/icons-webfont`, Grunt copy | 3 config files | Low |
| **1** | Remove dead deps: quill, react-datepicker, react-select, react-bootstrap | package.json only | Zero |
| **2** | DataTables BS4 → BS5 | Gruntfile + SCSS + Footer.php | Low |
| **3** | bootstrap-datepicker → flatpickr | 8 PHP files | Low |
| **4** | daterangepicker → litepicker | 8 PHP files | Low |
| **5** | inputmask → imask | 4 PHP files | Low |
| **6** | bootbox → confirm-dialog.ts | 19 PHP/JS files | Medium |
| **7** | select2 → tom-select | 19 PHP/JS files | Medium |
| **8** | moment → dayjs | 5 JS files | Low |
| **9** | Remove AdminLTE CSS (once bridge proves stable) | 1 SCSS file | Medium |
| **10** | Remove Bootstrap 4 dep | package.json | Low (after BS5 verified) |
