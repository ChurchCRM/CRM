---
name: icon-management
description: Standard patterns for Font Awesome icons across ChurchCRM. Single source of truth for icon usage, free tier compliance, and common substitutions.
tags: ["frontend", "icons", "font-awesome", "ui"]
learned: "2026-08-15"
---

# Skill: Icon Management & Font Awesome Integration <!-- learned: 2026-08-15 -->

## Context

ChurchCRM uses **Font Awesome 7.3+ exclusively** for all icons. No other icon libraries (Tabler, Material Icons, etc.) are used or permitted. Font Awesome is a production dependency.

**Package:** `@fortawesome/fontawesome-free` (free tier only)

---

## Font Awesome Free Tier (Only Permitted Variants)

ChurchCRM only uses **free** Font Awesome icons. Paid tier variants are **prohibited**.

### Permitted variants

- `fa-solid` — Solid filled icons (primary, most commonly used)
- `fa-regular` — Outlined icons (used for contrast or lighter visual weight)
- `fa-brands` — Brand/social icons (GitHub, Twitter, PayPal, etc.)

### Prohibited variants (NEVER use these)

- `fa-light` — Light weight (paid-only)
- `fa-thin` — Thin weight (paid-only)
- `fa-duotone` — Two-color (paid-only)
- `fa-sharp` — Sharp variant (paid-only)

**Verification:** Linting/auditing tools will scan for paid variants and block them at build time.

---

## HTML Markup Pattern

```html
<!-- ✅ CORRECT — solid icon with spacing -->
<i class="fa-solid fa-pencil me-2"></i>Edit

<!-- ✅ CORRECT — regular/outlined icon -->
<i class="fa-regular fa-trash me-2"></i>

<!-- ✅ CORRECT — brand icon -->
<i class="fa-brands fa-github me-2"></i>GitHub

<!-- ❌ WRONG — missing variant class (ambiguous) -->
<i class="fa-pencil"></i>

<!-- ❌ WRONG — paid variant -->
<i class="fa-light fa-pencil"></i>
```

### Icon Classes Structure

Every Font Awesome icon requires **two classes**:

1. **Variant class** (required): `fa-solid`, `fa-regular`, or `fa-brands`
2. **Icon class** (required): `fa-{icon-name}` (e.g. `fa-pencil`, `fa-trash`, `fa-users`)

Both classes must be present. The variant class determines visual weight; the icon class selects the specific glyph.

---

## Spacing & Sizing

### Spacing with adjacent text

```html
<!-- Icon before text (most common) -->
<i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
<!--                            ↑ margin-end = space to the right -->

<!-- Icon after text -->
<?= gettext('Check') ?><i class="fa-solid fa-check ms-2"></i>
<!--                                              ↑ margin-start = space to the left -->
```

Use Bootstrap margin utilities: `me-1` (small), `me-2` (standard), `me-3` (large).

### Icon sizes

| Use case | Class | Size |
|----------|-------|------|
| Inline with text | (none) | ~1em (default) |
| Stat card avatar | (none) | ~1em in 44×44px circle |
| Large badge/flag | `fa-lg` | 1.33em |
| Toolbar button | (none) | ~1em in 38-44px btn |
| Centered callout | `fa-2x` to `fa-4x` | 2–4× default |

```html
<!-- Stat card avatar (44×44px circle) -->
<span class="avatar bg-primary text-white rounded-circle">
    <i class="fa-solid fa-user"></i>
</span>

<!-- Large error callout (4× size) -->
<i class="fa-solid fa-triangle-exclamation fa-4x text-danger mb-2"></i>
```

---

## Common Icon Patterns & Substitutions

### Navigation / UI Actions

| Action | Icon | Example |
|--------|------|---------|
| **Edit** | `fa-pencil` | `<i class="fa-solid fa-pencil me-2"></i>` |
| **Delete/Remove** | `fa-trash` | `<i class="fa-solid fa-trash me-2"></i>` |
| **View/Open** | `fa-eye` | `<i class="fa-solid fa-eye me-2"></i>` |
| **Add/Create** | `fa-plus` or `fa-plus-circle` | `<i class="fa-solid fa-plus me-2"></i>` |
| **Save/Confirm** | `fa-check` or `fa-floppy-disk` | `<i class="fa-solid fa-floppy-disk me-2"></i>` |
| **Close/Cancel** | `fa-x` or `fa-xmark` | `<i class="fa-solid fa-xmark"></i>` |
| **Settings/Config** | `fa-cog` or `fa-sliders` | `<i class="fa-solid fa-cog me-2"></i>` |
| **Search/Find** | `fa-magnifying-glass` | `<i class="fa-solid fa-magnifying-glass me-2"></i>` |
| **Filter** | `fa-filter` | `<i class="fa-solid fa-filter me-2"></i>` |
| **Menu/Dropdown** | `fa-ellipsis-v` (vertical) or `fa-ellipsis` (horizontal) | `<i class="fa-solid fa-ellipsis-v"></i>` |

### Dashboard / Status Icons

| Status | Icon | Example |
|--------|------|---------|
| **People/Users** | `fa-users` | `<i class="fa-solid fa-users me-2"></i>` |
| **Family/Home** | `fa-house` | `<i class="fa-solid fa-house me-2"></i>` |
| **Calendar/Events** | `fa-calendar` or `fa-calendar-days` | `<i class="fa-solid fa-calendar me-2"></i>` |
| **Money/Finance** | `fa-dollar-sign` or `fa-credit-card` | `<i class="fa-solid fa-dollar-sign"></i>` |
| **Clock/Time** | `fa-clock` or `fa-hourglass` | `<i class="fa-solid fa-clock me-2"></i>` |
| **Location/Map** | `fa-map-pin` or `fa-map` | `<i class="fa-solid fa-map-pin me-2"></i>` |
| **Email/Message** | `fa-envelope` or `fa-message` | `<i class="fa-solid fa-envelope me-2"></i>` |
| **Phone/Call** | `fa-phone` | `<i class="fa-solid fa-phone me-2"></i>` |
| **Document/File** | `fa-file` or `fa-file-lines` | `<i class="fa-solid fa-file me-2"></i>` |
| **Download** | `fa-download` | `<i class="fa-solid fa-download me-2"></i>` |
| **Upload** | `fa-upload` | `<i class="fa-solid fa-upload me-2"></i>` |
| **Settings** | `fa-cog` or `fa-sliders` | `<i class="fa-solid fa-cog me-2"></i>` |

### Alerts / Notifications

| Alert Type | Icon | Example |
|------------|------|---------|
| **Success/Check** | `fa-check-circle` or `fa-circle-check` | `<i class="fa-solid fa-circle-check text-success"></i>` |
| **Warning/Caution** | `fa-triangle-exclamation` or `fa-exclamation` | `<i class="fa-solid fa-triangle-exclamation text-warning"></i>` |
| **Error/Alert** | `fa-circle-xmark` or `fa-x-circle` | `<i class="fa-solid fa-circle-xmark text-danger"></i>` |
| **Info** | `fa-circle-info` or `fa-info-circle` | `<i class="fa-solid fa-circle-info text-info"></i>` |
| **Question/Help** | `fa-circle-question` or `fa-question` | `<i class="fa-solid fa-circle-question"></i>` |

### Tabler → Font Awesome Equivalents (Legacy Migration)

When migrating from Tabler icons (deprecated), use this mapping:

| Tabler | Font Awesome |
|--------|--------------|
| `ti-alert-circle` | `fa-circle-info` |
| `ti-alert-triangle` | `fa-triangle-exclamation` |
| `ti-arrow-down` | `fa-arrow-down` |
| `ti-arrow-up` | `fa-arrow-up` |
| `ti-brand-github` | `fa-brands fa-github` |
| `ti-building` | `fa-building` |
| `ti-calendar` | `fa-calendar` |
| `ti-calendar-off` | `fa-calendar-slash` |
| `ti-cart` | `fa-cart-shopping` |
| `ti-check` | `fa-check` |
| `ti-circle-check` | `fa-circle-check` |
| `ti-credit-card` | `fa-credit-card` |
| `ti-device-floppy` | `fa-floppy-disk` |
| `ti-dots-vertical` | `fa-ellipsis-v` |
| `ti-download` | `fa-download` |
| `ti-edit` | `fa-pencil` |
| `ti-eye` | `fa-eye` |
| `ti-file` | `fa-file` |
| `ti-filter` | `fa-filter` |
| `ti-flag` | `fa-flag` |
| `ti-folder` | `fa-folder` |
| `ti-home` | `fa-house` |
| `ti-home-plus` | `fa-house-plus` |
| `ti-info-circle` | `fa-circle-info` |
| `ti-key` | `fa-key` |
| `ti-location` | `fa-location-dot` |
| `ti-logout` | `fa-sign-out` |
| `ti-map-pin` | `fa-map-pin` |
| `ti-menu-2` | `fa-bars` |
| `ti-message` | `fa-message` |
| `ti-mood-sad` | `fa-face-sad-tear` |
| `ti-pencil` | `fa-pencil` |
| `ti-phone` | `fa-phone` |
| `ti-pin` | `fa-thumbtack` |
| `ti-plus` | `fa-plus` |
| `ti-search` | `fa-magnifying-glass` |
| `ti-settings` | `fa-cog` |
| `ti-shield` | `fa-shield` |
| `ti-shopping-cart` | `fa-cart-shopping` |
| `ti-stack-2` | `fa-layer-group` |
| `ti-trash` | `fa-trash` |
| `ti-upload` | `fa-upload` |
| `ti-users` | `fa-users` |
| `ti-x` | `fa-xmark` |

---

## PHP Usage (Server-Side Rendering)

### In templates

```php
<?php
// Render an edit button with icon
?>
<a href="<?= $editUrl ?>">
    <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
</a>

// Render a notification icon
?>
<i class="fa-solid fa-circle-info text-info me-1"></i>
```

### Dynamic icon selection

```php
$iconClass = match($status) {
    'success' => 'fa-circle-check',
    'error'   => 'fa-circle-xmark',
    'warning' => 'fa-triangle-exclamation',
    'info'    => 'fa-circle-info',
    default   => 'fa-question-circle',
};

echo '<i class="fa-solid ' . InputUtils::escapeAttribute($iconClass) . '"></i>';
```

---

## JavaScript Usage (Client-Side Rendering)

### In template literals / dynamic HTML

```javascript
// ✅ CORRECT — Font Awesome class only
const iconHtml = `<i class="fa-solid fa-pencil me-2"></i>`;

// ✅ CORRECT — With sanitization for dynamic content
const userText = apiData.status; // untrusted input
const html = `<i class="fa-solid fa-check me-2"></i>${window.CRM.escapeHtml(userText)}`;
elem.innerHTML = html;

// ❌ WRONG — Missing variant class
const broken = `<i class="fa-pencil"></i>`;
```

### jQuery icon manipulation

```javascript
// Add Font Awesome icon to button
$(button).html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Loading...');

// Change icon on state change
$(elem).find('i').removeClass('fa-plus').addClass('fa-check');

// Check for icon presence
if ($(elem).find('i.fa-solid').length > 0) {
    console.log('Has Font Awesome icon');
}
```

---

## DataTables Column Rendering

```javascript
{
    title: i18next.t('Actions'),
    data: null,
    orderable: false,
    render: function(data, type, row) {
        return '<i class="fa-solid fa-pencil me-2"></i>' +
               '<a href="#">' + i18next.t('Edit') + '</a>';
    }
}
```

---

## Accessibility Considerations

### Decorative vs semantic icons

**Decorative icons** (visual enhancement only):
```html
<!-- Icon is decorative; text conveys meaning -->
<i class="fa-solid fa-heart" aria-hidden="true"></i> Favorites
```

**Semantic icons** (icon conveys essential meaning):
```html
<!-- Icon is essential; add screen reader text -->
<button>
    <i class="fa-solid fa-times" aria-label="Close"></i>
</button>
```

### Screen reader guidance

- Use `aria-hidden="true"` on decorative icons next to descriptive text
- Use `aria-label` or `.visually-hidden` text when the icon alone must be understood
- Avoid icon-only buttons unless they're well-known (close `×`, menu ≡)

---

## Pre-Commit Checklist

Before committing icon-related changes:

- [ ] Only Font Awesome free tier icons used (`fa-solid`, `fa-regular`, `fa-brands`)
- [ ] No paid Font Awesome variants (`fa-light`, `fa-thin`, `fa-duotone`, `fa-sharp`)
- [ ] All icons have both variant class (e.g. `fa-solid`) and icon class (e.g. `fa-pencil`)
- [ ] Icon spacing uses Bootstrap utilities (`me-2`, `ms-2`)
- [ ] No hardcoded Tabler icons (`ti ti-*`) remain in production code
- [ ] Dynamic icons are escaped if sourced from untrusted input
- [ ] Decorative icons use `aria-hidden="true"` when appropriate
- [ ] Icon-only buttons have `aria-label` when not self-evident

---

## Related Skills

- `[[frontend-development.md]]` — General UI patterns and component structure
- `[[bootstrap-5-migration.md]]` — Bootstrap 5 utilities for styling and spacing
- `[[table-action-menu.md]]` — Standard dropdown menu patterns with icon usage
- `[[code-standards.md]]` — Pre-commit checklist includes icon usage rules

---

Last updated: 2026-08-15
