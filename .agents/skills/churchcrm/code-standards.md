---
title: Code Standards
intent: ChurchCRM coding floor. Details live in src and the topic skills.
---

# Code Standards

PHP floor: 8.4+. Stack versions: `package.json` and `composer.json`.
Review gates: `maintainer-review-gates.md`.

- ORM for DB. No new `RunQuery()` / raw SQL
- Business logic in `src/ChurchCRM/Service/`
- `use` imports. Explicit `?int $param = null`
- Deleted `Functions.php` globals are gone — `ChurchCRM\Utils\*`
- IDs: `(int)` or `InputUtils::filterInt()`
- Output: `InputUtils::escapeHTML()` / `escapeAttribute()`
- Redirects: `RedirectUtils`
- UI: Tabler + Bootstrap 5. Wrap `gettext()` / `i18next.t()`
- Tests with behavior changes. Linked issue on every PR

## Strict vs Loose Comparisons

`mysqli_fetch_array()` / `extract()` / raw `$_GET` values are strings.
When you switch `==` to `===`, cast first: `(int)$type_ID === 11`.
Do not `(int)` a string slug getter.
