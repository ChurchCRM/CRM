---
name: icon-management
description: Standard patterns for Font Awesome icons across ChurchCRM. Use for icon selection, free-tier compliance, markup, accessibility, and validation.
tags: ["frontend", "icons", "font-awesome", "ui"]
---

# Skill: Icon Management & Font Awesome Integration

ChurchCRM uses Font Awesome Free (`@fortawesome/fontawesome-free`, version in `package.json`) for every icon.

## Variants

- `fa-solid`: filled, the default
- `fa-regular`: outlined, for lighter weight
- `fa-brands`: brand icons (GitHub, PayPal)

Use only these three. `fa-light`, `fa-thin`, `fa-duotone` and `fa-sharp` are Pro-only.

## Markup

Every icon carries a variant class and an icon class:

```html
<i class="fa-solid fa-pencil me-2"></i>Edit
<i class="fa-regular fa-trash-can me-2"></i>
<i class="fa-brands fa-github me-2"></i>GitHub
```

### Spacing and size

- Icon before text: `me-2` (`me-1` small, `me-3` large). Icon after text: `ms-2`
- Default size is ~1em. Use `fa-lg` for badges, `fa-2x` to `fa-4x` for centered callouts
- Stat card avatars put the icon inside `<span class="avatar bg-primary text-white rounded-circle">`

## Choosing an icon name

Pick the obvious free-tier name (edit is `fa-pencil`, delete is `fa-trash`). Grep `src/` for the same concept to match existing usage. Row action menus use `fa-ellipsis-vertical` (`table-action-menu.md`).

Confirm a name ships in the free build:

```bash
grep -E '^\.fa-<name>[ ,{]' node_modules/@fortawesome/fontawesome-free/css/all.css
```

Font Awesome 7 declares each icon as `.fa-<name> { --fa: "\eXXX"; }`. A missing class renders as blank space with no console error. `fa-calendar-slash`, `fa-house-plus`, `fa-map-pin-slash`, `fa-shield-check` and `fa-users-group` are not in the free build.

## Tabler → Font Awesome Equivalents

The Tabler icon webfont is not shipped, so `ti` / `ti-*` classes render blank. Use these names:

| Tabler | Font Awesome |
|--------|--------------|
| `ti-pencil` | `fa-pencil` |
| `ti-trash` | `fa-trash` |
| `ti-plus` | `fa-plus` |
| `ti-check` | `fa-check` |
| `ti-x` | `fa-xmark` |
| `ti-search` | `fa-magnifying-glass` |
| `ti-settings` | `fa-gear` |
| `ti-home` | `fa-house` |
| `ti-user` | `fa-user` |
| `ti-user-plus` | `fa-user-plus` |
| `ti-users` | `fa-users` |
| `ti-alert-triangle` | `fa-triangle-exclamation` |
| `ti-info-circle` | `fa-circle-info` |
| `ti-circle-check` | `fa-circle-check` |
| `ti-mail` | `fa-envelope` |
| `ti-phone` | `fa-phone` |
| `ti-calendar` | `fa-calendar` |
| `ti-map-pin` | `fa-location-dot` |
| `ti-download` / `ti-upload` | `fa-download` / `fa-upload` |
| `ti-printer` | `fa-print` |
| `ti-eye` | `fa-eye` |
| `ti-lock` | `fa-lock` |
| `ti-copy` | `fa-copy` |
| `ti-device-floppy` | `fa-floppy-disk` |
| `ti-dots-vertical` | `fa-ellipsis-vertical` |
| `ti-refresh` | `fa-arrows-rotate` |
| `ti-filter` | `fa-filter` |
| `ti-file-text` | `fa-file-lines` |
| `ti-shopping-cart` | `fa-cart-shopping` |
| `ti-building-church` | `fa-church` |
| `ti-tag` | `fa-tag` |

## Guards

`npm run lint:icons` runs both checks (also in the `code-quality` CI job):

| Script | Rejects |
|--------|---------|
| `scripts/validate-no-tabler-icons.js` | any `ti` / `ti-*` class |
| `scripts/validate-fa-icons-exist.js` | any `fa-*` class with no `.fa-<name>` rule in `all.css` |

Both scan `src/` and `webpack/` through `scripts/lib/icon-source-files.js`.

**Dynamic icon names are whole class names.** A prefix such as `'fa-' . $icon` or `` `fa-${name}` `` cannot be verified statically. Build lookup maps from complete names:

```php
$icons = ['person' => 'fa-user', 'family' => 'fa-people-roof'];
echo '<i class="fa-solid ' . InputUtils::escapeAttribute($icons[$type]) . '"></i>';
```

## JavaScript

```javascript
const iconHtml = `<i class="fa-solid fa-pencil me-2"></i>`;
elem.innerHTML = `<i class="fa-solid fa-check me-2"></i>${window.CRM.escapeHtml(apiData.status)}`;

$(button).html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Loading...');
$(elem).find('i').removeClass('fa-plus').addClass('fa-check');
```

DataTables column render:

```javascript
render: (data, type, row) =>
    '<i class="fa-solid fa-pencil me-2"></i><a href="#">' + i18next.t('Edit') + '</a>'
```

## Accessibility

- Decorative icon next to text: `aria-hidden="true"`
- Icon-only control: `aria-label="Close"` on the control, or `.visually-hidden` text
- Reserve icon-only buttons for familiar glyphs (close, menu)

## Checklist

- [ ] Variants are `fa-solid`, `fa-regular` or `fa-brands`
- [ ] Each icon has a variant class and an icon class
- [ ] Spacing uses `me-*` / `ms-*`
- [ ] `npm run lint:icons` passes
- [ ] Dynamic values are escaped
- [ ] Decorative icons have `aria-hidden="true"`; icon-only buttons have `aria-label`

## Related

- `frontend-development.md`
- `table-action-menu.md`
- `code-standards.md`
