---
title: "Bootstrap 5 Migration"
intent: "Canonical Bootstrap 4 to Bootstrap 5 class and attribute mappings used by ChurchCRM"
tags: ["frontend", "bootstrap5", "migration"]
prereqs: ["[[frontend-development]]"]
complexity: "intermediate"
---

# Bootstrap 4 → 5 Migration

ChurchCRM uses Bootstrap 5.3 through Tabler. Use this file for framework
class and attribute migrations; use `tabler-components.md` for page design and
`icon-management.md` for icons.

## Required attribute changes

| Bootstrap 4 | Bootstrap 5 |
|---|---|
| `data-toggle` | `data-bs-toggle` |
| `data-target` | `data-bs-target` |
| `data-dismiss` | `data-bs-dismiss` |
| `data-parent` | `data-bs-parent` |
| `data-offset` | `data-bs-offset` |

## Common class changes

| Bootstrap 4 | Bootstrap 5 |
|---|---|
| `.ml-*`, `.mr-*` | `.ms-*`, `.me-*` |
| `.pl-*`, `.pr-*` | `.ps-*`, `.pe-*` |
| `.text-left`, `.text-right` | `.text-start`, `.text-end` |
| `.float-left`, `.float-right` | `.float-start`, `.float-end` |
| `.font-weight-*` | `.fw-*` |
| `.custom-select` | `.form-select` |
| `.custom-control` | `.form-check` |
| `.input-group-prepend`, `.input-group-append` | Remove wrapper |

Do not perform broad regex rewrites over PHP source. Make structural changes
manually or with narrowly scoped scripts, then run the PHP and frontend
validation commands from `development-workflows.md`.

## Icons

Bootstrap migration does not authorize Tabler icon classes. ChurchCRM uses
Font Awesome free-tier classes exclusively; see `icon-management.md` and the
`lint:icons:tabler` check in `package.json`.
