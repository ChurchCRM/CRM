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
- Reports: never extend `QueryView.php` / `QueryList.php` or add `query_qry` rows (#9921). Core reports → `src/v2/` route + controller + Twig. One church's needs → a plugin in `src/plugins/` (shareable in `src/plugins/community/`)
- Tests with behavior changes
- Comments are rare. Names and tests carry intent. Do not restate the next line. Comment only a *why* that the code cannot say (CI trap, security invariant, deliberate deviation). Do not add paragraph comments in specs.

## Strict vs Loose Comparisons

`mysqli_fetch_array()` / `extract()` / raw `$_GET` values are **strings**.
When you change `==` to `===`, cast first: `(int)$type_ID === 11`.
Do not `(int)` a string slug getter.

## SystemConfig Settings (Frozen)

**Do not add new keys to `SystemConfig::buildCategories()`.** The old-style settings page is frozen. Define the `ConfigItem` in `buildConfigs()`, omit it from `buildCategories()`, and surface it in its area admin hub (`/admin/people`). See `settings-placement.md`.

## Admin Page Headers

All new admin pages must follow this pattern for consistent navigation and styling. `Header.php` renders the page header; pass variables only.

**Route handler:** Pass breadcrumbs, title, subtitle via `$pageArgs`:
```php
'aBreadcrumbs' => PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Page Name')],
]),
'sPageTitle' => gettext('Page Title'),
'sPageSubtitle' => gettext('Optional subtitle'),
```

**View:** Include `Header.php` at the top (it renders breadcrumbs, title, subtitle automatically). Do NOT duplicate the header in the view—`Header.php` handles it.

See `src/admin/views/feature-toggles.php` and `src/admin/routes/system.php` (Feature Toggles page) for pattern example.
