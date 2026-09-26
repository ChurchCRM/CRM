---
title: Code Standards
intent: Always-on floor. Topic skills and src/ hold the rest.
---

# Code Standards

PHP 8.4+. Versions: `package.json`, `composer.json`. Review: `maintainer-review-gates.md`.

- ORM for DB. No new `RunQuery()` / raw SQL
- Business logic in `src/ChurchCRM/Service/`
- `use` imports. Explicit `?int $param = null`
- Deleted `Functions.php` globals → `ChurchCRM\Utils\*`
- IDs: `(int)` or `InputUtils::filterInt()`
- Output: `InputUtils::escapeHTML()` / `escapeAttribute()`
- JSON in `<script>`: `InputUtils::jsonEncodeForScript()`
- Redirects: `RedirectUtils` — not raw `header('Location')`
- UI: Tabler + Bootstrap 5. Wrap `gettext()` / `i18next.t()`
- Tests with behavior changes. Linked issue on every PR
- Comments are rare. Names and tests carry intent. Do not restate the next line. Comment only a *why* that the code cannot say (CI trap, security invariant, deliberate deviation). Do not add paragraph comments in specs.

## Strict vs Loose Comparisons

`mysqli_fetch_array()` / `extract()` / raw `$_GET` values are **strings**.
When you change `==` to `===`, cast first: `(int)$type_ID === 11`.
Do not `(int)` a string slug getter.
