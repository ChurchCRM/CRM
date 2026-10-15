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
- Data attributes: `data-bs-*`. Never `data-toggle` / `data-dismiss` / `data-placement` / `data-backdrop` / `data-keyboard` (Bootstrap 4, ignored). Custom hooks read by our own JS (`data-target` + `dataset`) are fine
- Field help text: `.form-text`, not `.form-hint` (removed in Tabler 1.5+)
- No `tabler.getColor()` / `tabler.hexToRgba()` or `*-focus-box-shadow` Sass variables; use CSS variables (`var(--tblr-primary)`)
- Legend (Tabler 1.6+): `<button class="legend" style="--legend-color: #hex"><span class="legend-dot"></span>Label</button>`, `.legend-off` for a toggled-off item, `.legend-list` for a group. Do not define your own `.legend-dot`. The map legend (`src/people/views/map-view.php`) is the working example
- `npm run lint:tabler` fails on `.form-hint`, the removed JS helpers and Bootstrap 4 data attributes
- Badges: Tabler `-lt` + matching `text-*` for labels. Do not use bare `bg-info` / `bg-warning` without a text class (contrast fails in Tabler)
- Modals: one `bootstrap.Modal` instance; destroy TomSelect/Quill before swapping `innerHTML`
- No inline styles that fight Tabler theme tokens

If you need a component, open a nearby page that already has it and match that markup.
