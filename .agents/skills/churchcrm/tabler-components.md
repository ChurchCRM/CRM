---
title: Tabler Components
intent: Use current Tabler + Bootstrap 5. Copy from existing pages, not from memory.
---

# Tabler Components

Versions: `package.json` (`@tabler/core`, `bootstrap`).
Working examples: `src/views/`, `src/admin/views/`, `src/Include/Header.php`.
Layout / breakpoints: `responsive-design-guidelines.md`.

## Must

- Bootstrap 5 utilities (`ms-`/`me-`, `fw-bold`, `text-end`, `btn-close`)
- Data attributes: `data-bs-*`
- Badges: Tabler `-lt` + matching `text-*` for labels. Do not use bare `bg-info` / `bg-warning` without a text class (contrast fails in Tabler)
- Modals: one `bootstrap.Modal` instance; destroy TomSelect/Quill before swapping `innerHTML`
- No inline styles that fight Tabler theme tokens

If you need a component, open a nearby page that already has it and match that markup.
