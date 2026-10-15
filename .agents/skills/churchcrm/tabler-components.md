---
title: Tabler Components
intent: Use current Tabler + Bootstrap 5. Copy from existing pages, not from memory.
---

# Tabler Components

Versions: `package.json` (`@tabler/core` 1.6.x, `bootstrap`).
Working examples: `src/views/`, `src/admin/views/`, `src/Include/Header.php`.
Layout / breakpoints: `responsive-design-guidelines.md`.

## Must

- Bootstrap 5 utilities (`ms-`/`me-`, `fw-bold`, `text-end`, `btn-close`)
- Data attributes: `data-bs-*` for every Bootstrap and Tabler hook (`data-bs-toggle`, `data-bs-dismiss`, `data-bs-placement`, `data-bs-backdrop`, `data-bs-keyboard`). Custom hooks read by our own JS (`data-target` + `dataset`) are fine
- Field help text: `.form-text`
- Colours: CSS variables (`var(--tblr-primary)`); Sass uses Tabler's current variables
- Legend: `<button class="legend" style="--tblr-legend-color: #hex"><span class="legend-dot"></span>Label</button>`, `.legend-off` for a toggled-off item, `.legend-list` for a group. Size and colour come from Tabler's `.legend-dot` and `--tblr-legend-color`. The map legend (`src/people/views/map-view.php`) is the working example
- `npm run lint:tabler` checks these conventions
- Stock Tabler: root font size, theme base, palette and font stay at Tabler's defaults. Brand accent comes from `data-bs-theme-primary` (user setting). `_tabler-bridge.scss` covers DataTables and layout only
- Styling in templates: a Tabler/Bootstrap utility class, or a named class in the area partial (`src/skin/scss/_page-<area>.scss`; shared components in `_page-shared.scss`). `style=""` and `<style>` hold only values computed from data (a percentage width, a database colour) or state that JS toggles. Setup pages load `setup.css` and carry their own rules
- Colours in custom CSS: Tabler tokens (`var(--tblr-bg-surface)`, `--tblr-body-color`, `--tblr-secondary-color`, `--tblr-border-color`) so light and dark both render; brand and data colours stay explicit
- A table inside a card whose row menus must escape the wrapper: `.table-clip-x`
- Keep every `@media` rule when restyling; check desktop, tablet (~820px) and phone (~390px)
- Accessibility: `Header.php` carries Tabler's `.skip-link` to `#main-content`. Keep that id on the page body
- Badges: Tabler `-lt` + matching `text-*` for labels (a bare `bg-info` / `bg-warning` needs a text class for contrast)
- Modals: one `bootstrap.Modal` instance; destroy TomSelect/Quill before swapping `innerHTML`
- `npm run lint:ui` (part of `npm run lint`) rejects a new static `style=""`, a page `<style>` block, a Bootstrap 4 class name, a root font-size, palette or theme-base override and a Bootstrap 4 selector. Remaining static inline styles are listed per file in `scripts/ui-conventions-baseline.json`; a JS-toggled one is added there in the same PR with a reviewer-visible reason, and the count only goes down
- Bootstrap 5 names: `ms-`/`me-`/`ps-`/`pe-` (not `ml-`/`mr-`/`pl-`/`pr-`), `text-start`/`text-end`, `float-start`/`float-end`, `fw-bold`, `visually-hidden`, `g-0`, `mb-3` for a form row, `form-check`, `bg-<colour>-lt text-<colour>` for a badge

If you need a component, open a nearby page that already has it and match that markup.
