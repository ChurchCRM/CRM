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
- Accessibility: `Header.php` carries Tabler's `.skip-link` to `#main-content`. Keep that id on the page body
- Badges: Tabler `-lt` + matching `text-*` for labels (a bare `bg-info` / `bg-warning` needs a text class for contrast)
- Modals: one `bootstrap.Modal` instance; destroy TomSelect/Quill before swapping `innerHTML`
- No inline styles that fight Tabler theme tokens

If you need a component, open a nearby page that already has it and match that markup.
