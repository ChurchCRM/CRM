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

## Standard JS Pattern (DataTables / MainDashboard.js)

```javascript
function renderActionColumn(id, editUrl) {
    return `
        <div class="dropdown">
            <button class="btn btn-sm btn-ghost-secondary" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ti ti-dots-vertical"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="${editUrl}">
                    <i class="ti ti-pencil me-2"></i>${i18next.t('Edit')}
                </a>
                <div class="dropdown-divider"></div>
                <button type="button" class="dropdown-item text-danger"
                        data-id="${id}">
                    <i class="ti ti-trash me-2"></i>${i18next.t('Delete')}
                </button>
            </div>
        </div>`;
}
```

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

## Overflow / Dropdown Clipping

Bootstrap's `.table-responsive` sets `overflow-x: auto`, which clips dropdown menus.

**Choose the right fix:**

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

## Order / Sort Actions

When rows support reordering (move up / move down):

```php
echo '<div class="dropdown-menu dropdown-menu-end">';
echo '<a class="dropdown-item" href="Editor.php?act=delete&ID=' . $id . '">
        <i class="ti ti-trash me-2"></i>' . gettext('Delete') . '</a>';
if ($row !== 1) {
    echo '<div class="dropdown-divider"></div>';
    echo '<a class="dropdown-item" href="Editor.php?act=up&row_num=' . $row . '">
            <i class="ti ti-arrow-up me-2"></i>' . gettext('Move up') . '</a>';
}
if ($row !== $numRows) {
    echo '<a class="dropdown-item" href="Editor.php?act=down&row_num=' . $row . '">
            <i class="ti ti-arrow-down me-2"></i>' . gettext('Move down') . '</a>';
}
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
