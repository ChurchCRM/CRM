---
title: Frontend Development
intent: ChurchCRM UI rules. Read package.json and src for versions and components.
---

# Frontend Development

Stack versions live in `package.json` (`bootstrap`, `@tabler/core`, `webpack`, `typescript`). Current UI is Tabler + Bootstrap 5. `data-bs-*`. No React.

Components: `tabler-components.md`. Breakpoints: `responsive-design-guidelines.md`. Bundling: `webpack-typescript.md` and `webpack/`.

## Must

- Asset URLs: `SystemURLs::getRootPath()` — see any current view under `src/views/` or `src/admin/views/`
- Notify: `window.CRM.notify()` + `i18next.t()`. No `alert()` / `confirm()` — bootbox or a Bootstrap 5 modal
- API from webpack: `webpack/api-utils.ts` (`buildAPIUrl`, `buildAdminAPIUrl`, `fetchAPIJSON`). Do not assign `window.CRM.root` in a bundle constructor
- Admin jQuery pages: `window.CRM.AdminAPIRequest()`; path `'database/reset'` → `/admin/api/database/reset`
- DataTables page length: merge `window.CRM.plugin.dataTable` last so the user preference wins (`src/Include/Header.php`)
- Row actions: `table-action-menu.md` before you add a table menu
- Settings panel pattern: Finance dashboard — copy from that page, do not invent a third pattern
- i18n: wrap only. See `i18n-localization.md`

## Do not

- Reject Bootstrap 5 classes
- Run `locale:build` in a feature PR
- Concatenate `gettext('Add New') . ' ' . gettext('Fund')`
