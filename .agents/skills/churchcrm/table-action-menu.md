---
name: table-action-menu
description: Standard pattern for per-row action menus in all ChurchCRM tables. Read this whenever adding, editing, or reviewing a table that has row-level actions (edit, delete, move, cart, etc.).
tags: ["frontend", "tabler", "tables", "dropdowns", "cart", "ux"]
---

# Skill: Table Action Menu <!-- learned: 2026-03-23 -->

## Rule

Every table row that has per-row actions **must** use the standard Tabler action dropdown. No exceptions. This applies to PHP templates, JS-rendered DataTables columns, and React tables alike.

---

## Standard HTML Pattern (PHP)

```html
<td class="w-1">
    <div class="dropdown">
        <button class="btn btn-sm btn-ghost-secondary" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="ti ti-dots-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="Editor.php?ID=<?= $id ?>">
                <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
            </a>
            <div class="dropdown-divider"></div>
            <button type="submit" class="dropdown-item text-danger">
                <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
            </button>
        </div>
    </div>
</td>
```

Add `w-1` to the `<th>` / `<td>` so the column shrinks to fit the icon button and doesn't waste horizontal space.

---

## Shared JS Renderers (use these — do NOT duplicate inline) <!-- learned: 2026-03-24 -->

`CRMJSOM.js` exposes two shared renderers on `window.CRM`. **Always use these** in DataTable `render:` functions instead of writing raw HTML strings.

```javascript
// Standard person action menu: View → Edit → [View Family?] → [divider] → Cart → [divider] → Delete
window.CRM.renderPersonActionMenu(personId, fullName, { familyId, inCart })

// Standard family action menu: View → Edit → [divider] → Cart → [divider] → Delete
window.CRM.renderFamilyActionMenu(familyId, familyName, { inCart })
```

- `familyId` — optional; when provided, adds a "View Family" item after Edit
- `inCart` — optional; flips cart button to RemoveFromCart state
- The global `.delete-person` delegated handler is registered in `CRMJSOM.js` — **no per-page copy needed**
- These functions call `i18next.t()` at render time (safe: DataTables render after locales load)

```javascript
// DataTables column example
{
    title: i18next.t('Actions'),
    data: null,
    orderable: false,
    searchable: false,
    className: 'text-end w-1 no-export',
    render: function(data, type, row) {
        return window.CRM.renderPersonActionMenu(row.PersonId, row.FullName, { familyId: row.FamilyId });
    }
}
```

For PHP-rendered tables (non-DataTables), write the HTML directly using the standard pattern below.

---

## Standard Person / Family Action Menu Order <!-- learned: 2026-03-24 -->

All person and family action menus must have these 4 items in this exact order:

| # | Item | Condition |
|---|------|-----------|
| 1 | **View** (`ti ti-eye`) | Always |
| 2 | **Edit** (`ti ti-pencil`) | Always |
| 2b | **View Family** (`ti ti-users`) | Only if `familyId` is available |
| — | `dropdown-divider` | Always |
| 3 | **Cart** (Add/Remove, `.AddToCart` / `.RemoveFromCart`) | Always |
| — | `dropdown-divider` | Always |
| 4 | **Delete** (`ti ti-trash`, `text-danger`) | Always |

For persons, Delete uses a `.delete-person` button with `data-person_id` + `data-person_name` — handled globally by `CRMJSOM.js`.
For families, Delete links to `SelectDelete.php?FamilyID={id}`.

---

## Rules — Never Violate

| Rule | ✅ Correct | ❌ Wrong |
|------|-----------|---------|
| Trigger class | `btn-ghost-secondary` | `btn-outline-secondary`, `btn-secondary` |
| Trigger icon | `ti ti-dots-vertical` | `fa-solid fa-ellipsis-v`, `fa-ellipsis-v` |
| Menu alignment | `dropdown-menu-end` | `dropdown-menu-right` |
| Aria attribute | `aria-expanded="false"` only | `aria-haspopup="true"` |
| Inline styles | none | `style="z-index:..."`, `style="position:..."` |
| stopPropagation | never | `onclick="event.stopPropagation()"` |
| Icon spacing | `ti ti-pencil me-2` | icon only, no `me-2` |
| Dividers | before destructive actions | none, or between every item |
| Destructive items | `dropdown-item text-danger` | `dropdown-item btn-danger` |

---

## Overflow / Dropdown Clipping <!-- learned: 2026-03-26 -->

Bootstrap's `.table-responsive` sets `overflow-x: auto`, which clips dropdown menus.

**Preferred fix: `data-bs-display="static"` on the dropdown trigger button.** This tells Bootstrap to render the dropdown in-place (no Popper positioning) so it isn't clipped by `overflow` containers. Works in both PHP templates and JS-rendered DataTable columns.

```html
<!-- PHP template -->
<button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static">

<!-- JS DataTable render -->
'<button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static">'
```

**Fallback (only if `data-bs-display="static"` causes positioning issues):**

| Situation | Fix |
|-----------|-----|
| Table has ≤ 5 columns — no real horizontal scroll needed | **Remove** `table-responsive` wrapper entirely |
| Table genuinely needs horizontal scroll | Add `style="overflow: visible;"` to **both** the `card-body` and `.table-responsive` divs |
| DataTables-managed table inside a card | Add `style="overflow: visible;"` to the `card-body` only |

**Never** add `z-index` or `position: relative` to the `<td>` or `.dropdown` container — they do not fix clipping.

---

## Cart Button in a Dropdown Item

When a row action toggles cart membership, use this pattern. It works with `cart.js`'s delegated `$(document).on("click", ".AddToCart")` handler:

```php
<?php $inCart = isset($_SESSION['aPeopleCart']) && in_array($item->getId(), $_SESSION['aPeopleCart'], false); ?>
<button type="button"
    class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
    data-cart-id="<?= $item->getId() ?>"
    data-cart-type="person"
    data-label-add="<?= gettext('Add to Cart') ?>"
    data-label-remove="<?= gettext('Remove from Cart') ?>">
    <i class="<?= $inCart ? 'ti ti-trash' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
    <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
</button>
```

`cart.js::updateButtonState` detects `isDropdownItem` via `.hasClass("dropdown-item")` and swaps Tabler icons + `.cart-label` text accordingly. Never use `stopPropagation` — it silently breaks this delegation.

---

## Order / Sort Actions <!-- learned: 2026-03-24 -->

When rows support reordering (move up / move down), show the divider **only when at least one move action is present**. If there's only one row, no move items appear, and a lonely divider above Delete looks broken.

```php
echo '<div class="dropdown-menu dropdown-menu-end">';
if ($row !== 1) {
    echo '<a class="dropdown-item" href="Editor.php?act=up&row_num=' . $row . '">
            <i class="ti ti-arrow-up me-2"></i>' . gettext('Move up') . '</a>';
}
if ($row !== $numRows) {
    echo '<a class="dropdown-item" href="Editor.php?act=down&row_num=' . $row . '">
            <i class="ti ti-arrow-down me-2"></i>' . gettext('Move down') . '</a>';
}
// Only show divider when at least one move action is present
if ($row !== 1 || $row !== $numRows) {
    echo '<div class="dropdown-divider"></div>';
}
echo '<a class="dropdown-item text-danger" href="Editor.php?act=delete&ID=' . $id . '">
        <i class="ti ti-trash me-2"></i>' . gettext('Delete') . '</a>';
echo '</div>';
```

---

## Table Header

The Actions column header must be:

```html
<th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
```

- `w-1` — collapses to minimum width
- `no-export` — excluded from DataTables CSV/Excel export
- `text-center` — aligns with the centered icon button

---

## Person Rows Always Get Standard Menu <!-- learned: 2026-03-25 -->

Any table that lists people (attendees, members, visitors, etc.) **must** include the standard person action menu items — even if the page has domain-specific actions (e.g. Check Out). Domain actions go between the standard items, separated by dividers:

1. View / Edit / View Family (standard)
2. `dropdown-divider`
3. Domain-specific actions (Check Out, Assign Role, etc.)
4. `dropdown-divider`
5. Cart (standard)
6. `dropdown-divider`
7. Delete (standard, `text-danger`)

---

## Cart Page: "Remove Only" Variant <!-- learned: 2026-03-25 -->

On the cart view (`/v2/cart`), every person is already in the cart, so the cart button is always in `RemoveFromCart` state. Do **not** add a custom click handler — the global `CartManager` in `cart.js` handles `.RemoveFromCart` clicks via event delegation. The standard dropdown still applies (View, Edit, View Family, divider, Remove from Cart).

```php
<button type="button"
    class="dropdown-item RemoveFromCart text-danger"
    data-cart-id="<?= $Person->getId() ?>"
    data-cart-type="person"
    data-label-add="<?= gettext('Add to Cart') ?>"
    data-label-remove="<?= gettext('Remove from Cart') ?>">
    <i class="ti ti-trash me-2"></i>
    <span class="cart-label"><?= gettext('Remove from Cart') ?></span>
</button>
```

No Delete action is shown on the cart page — users can only remove from cart, not delete the person.

---

## Checklist Before Committing Any Table Change

- [ ] Trigger uses `btn-ghost-secondary` + `ti ti-dots-vertical`
- [ ] Menu uses `dropdown-menu-end` (not `dropdown-menu-right`)
- [ ] No `aria-haspopup` attribute
- [ ] No inline styles on trigger, menu, `<td>`, or `.dropdown`
- [ ] No `stopPropagation` anywhere in the row or dropdown
- [ ] Divider separates primary actions from destructive actions
- [ ] Destructive items use `text-danger` class
- [ ] All icons have `me-2` spacing
- [ ] Actions `<th>` has `w-1 text-center no-export`
- [ ] Dropdown clipping fixed (see Overflow section above)
