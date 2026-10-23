---
title: Frontend Development
intent: The one place for ChurchCRM UI and styling rules: Tabler markup, themes, screen sizes. Read package.json and src for versions and components. Copy from existing pages, not from memory.
---

# Frontend Development

Stack versions live in `package.json` (`@tabler/core`, `bootstrap`, `webpack`, `typescript`). Use whatever those pin; do not write a version into code or docs. UI is Tabler (which bundles Bootstrap) with `data-bs-*` attributes, in plain TypeScript/JS.

**This file is the only place UI and styling rules live.** Other cards link here; they do not restate it.

## Themes and screen sizes (part of every PR)

Tabler builds in light and dark themes (`data-bs-theme` on `<html>`, set in `Header.php`) and Bootstrap's responsive breakpoints. Using Tabler's tokens and classes gets both for free; hard-coded colours or widths break them.

- Two themes: **light and dark**. Every new or touched page renders correctly in both
- Three screen sizes: **desktop, tablet (~820px) and phone (~390px)**. Layout patterns: `responsive-design-guidelines.md`
- Author: check all six combinations in a browser before commit (`git-workflow.md`); screenshots go in the PR
- Reviewer: check the same in code review and style review (`pr-review.md`, `maintainer-review-gates.md`). A UI PR with no stated pass is a hard block

Working examples: `src/views/`, `src/admin/views/`, `src/Include/Header.php`. If you need a component, open a nearby page that already has it and match that markup.
Breakpoints: `responsive-design-guidelines.md`. Bundling: `webpack-typescript.md` and `webpack/`.

## Design each page around a role's task

Name the role and the goal before you lay out a page ("treasurer finds the open deposit", "secretary adds a family"), then build the page so that task takes the fewest steps. The Cypress spec for the page follows the same workflow (`cypress-testing.md`).

- The primary action is a prominent button in the page header (`$sPageHeaderButtons`, `btn btn-success` or `btn-primary` with an icon), not a small control in a card header
- A button that starts a task lands the user in that task: a "Create X" link on another page goes to the form or opens the modal (`?new=1` on the list page), not to a list the user must search
- A list page shows its table above the fold. Filters are compact or collapsed with a one-line summary; summary tiles (a row of Tabler `card card-sm`) answer the role's first question. Copy `src/finance/views/dashboard.php` and `src/finance/views/deposits/editor.php`
- One search per list. Bulk actions appear when rows are selected, with the count. The ID or name links to the record
- A form saves, then returns the user to the page they came from (`linkBack`) with the result visible

## Must

- Asset URLs: `SystemURLs::getRootPath()` — see any current view under `src/views/` or `src/admin/views/`
- Notify: `window.CRM.notify()` + `i18next.t()`. Confirm with bootbox or a Bootstrap modal
- API from webpack: `webpack/api-utils.ts` (`buildAPIUrl`, `buildAdminAPIUrl`, `fetchAPIJSON`). Take the root path from those helpers
- Admin jQuery pages: `window.CRM.AdminAPIRequest()`; path `'database/reset'` → `/admin/api/database/reset`
- DataTables page length: merge `window.CRM.plugin.dataTable` last so the user preference wins (`src/Include/Header.php`)
- Row actions: `table-action-menu.md` before you add a table menu
- Settings panel pattern: Finance dashboard — copy from that page, reuse it
- i18n: wrap only. See `i18n-localization.md`
- Translate whole phrases: `gettext('Add New Fund')`

## Tabler and Bootstrap markup

- Data attributes: `data-bs-*` for every Bootstrap and Tabler hook (`data-bs-toggle`, `data-bs-dismiss`, `data-bs-placement`, `data-bs-backdrop`, `data-bs-keyboard`). Custom hooks read by our own JS (`data-target` + `dataset`) are fine
- Current Bootstrap names (what the pinned version uses): `ms-`/`me-`/`ps-`/`pe-` (not `ml-`/`mr-`/`pl-`/`pr-`), `text-start`/`text-end`, `float-start`/`float-end`, `fw-bold`, `btn-close`, `visually-hidden`, `g-0`, `mb-3` for a form row, `form-check`
- Field help text: `.form-text`
- Stock Tabler: root font size, theme base, palette and font stay at Tabler's defaults. Brand accent comes from `data-bs-theme-primary` (user setting). `_tabler-bridge.scss` covers DataTables and layout only
- Styling in templates: a Tabler/Bootstrap utility class, or a named class in the area partial (`src/skin/scss/_page-<area>.scss`; shared components in `_page-shared.scss`). `style=""` and `<style>` hold only values computed from data (a percentage width, a database colour) or state that JS toggles. Setup pages load `setup.css` and carry their own rules
- Colours in custom CSS: Tabler tokens (`var(--tblr-bg-surface)`, `--tblr-body-color`, `--tblr-secondary-color`, `--tblr-border-color`, `var(--tblr-primary)`) so light and dark both render; brand and data colours stay explicit. Sass uses Tabler's current variables
- Legend: `<button class="legend" style="--tblr-legend-color: #hex"><span class="legend-dot"></span>Label</button>`, `.legend-off` for a toggled-off item, `.legend-list` for a group. The map legend (`src/people/views/map-view.php`) is the working example
- Badges: Tabler `-lt` + matching `text-*` for labels (`bg-<colour>-lt text-<colour>`). A bare `bg-info` / `bg-warning` needs a text class for contrast
- A table inside a card whose row menus must escape the wrapper: `.table-clip-x`
- Keep every `@media` rule when restyling
- Accessibility: `Header.php` carries Tabler's `.skip-link` to `#main-content`. Keep that id on the page body
- Modals: one `bootstrap.Modal` instance; destroy TomSelect/Quill before swapping `innerHTML`
- `npm run lint` checks these. It rejects a new static `style=""`, a page `<style>` block, a Bootstrap 4 class name, a removed Tabler pattern (`.form-hint`, `tabler.getColor()`, Bootstrap 4 `data-toggle`), a fixed-colour `table-success`/`table-warning`/`table-secondary` row variant, a root font-size, palette or theme-base override and a Bootstrap 4 selector. Remaining static inline styles are listed per file in `scripts/ui-conventions-baseline.json`; a JS-toggled one is added there in the same PR with a reviewer-visible reason, and the count only goes down
