---
name: table-action-menu
description: Standard pattern for per-row action menus in all ChurchCRM tables. Read this whenever adding, editing, or reviewing a table that has row-level actions (edit, delete, move, cart, etc.).
tags: ["frontend", "tabler", "tables", "dropdowns", "cart", "ux"]
---

# Skill: Table Action Menu

This file owns row action menus: trigger markup, trigger icon, and the global behaviour that keeps the dropdown from clipping. `responsive-design-guidelines.md`, `frontend-development.md` and `icon-management.md` defer to it.

## Rule

Every table row with per-row actions uses the standard Tabler action dropdown, in PHP templates and JS-rendered DataTables columns alike.

## Standard HTML Pattern (PHP)

```html
<td class="w-1">
    <div class="dropdown">
        <button class="btn btn-sm btn-ghost-secondary" type="button"
                data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
            <i class="fa-solid fa-ellipsis-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="Editor.php?ID=<?= $id ?>">
                <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
            </a>
            <div class="dropdown-divider"></div>
            <button type="submit" class="dropdown-item text-danger">
                <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
            </button>
        </div>
    </div>
</td>
```

`w-1` on the `<th>` and `<td>` shrinks the column to the icon button.

## Shared JS Renderers

DataTable `render:` functions call the shared renderers on `window.CRM` (`src/skin/js/CRMJSOM.js`):

```javascript
// View → Edit → [View Family?] → divider → Cart → divider → Delete
window.CRM.renderPersonActionMenu(personId, fullName, { familyId, inCart })
// View → Edit → divider → Cart → divider → Delete
window.CRM.renderFamilyActionMenu(familyId, familyName, { inCart })
// View → Edit → Check-in → divider → Activate/Deactivate → divider → Delete
window.CRM.renderEventActionMenu(eventId, eventTitle, { inactive })
```

- `familyId` adds "View Family" after Edit; `inCart` flips the cart button to RemoveFromCart.
- `extraItems` adds page-specific items after Cart; `omitDelete` drops Delete for lists that manage a relationship (the ministry Volunteers grid adds *Remove Volunteer* this way).
- The `.delete-person` handler is delegated in `CRMJSOM.js`; pages add no copy.
- The renderers call `i18next.t()` at render time, after locales load.

```javascript
{
    title: i18next.t('Actions'),
    data: null,
    orderable: false,
    searchable: false,
    className: 'text-end w-1 no-export',
    render: (data, type, row) =>
        window.CRM.renderPersonActionMenu(row.PersonId, row.FullName, { familyId: row.FamilyId }),
}
```

### `window.CRM.buildActionMenu(items, opts)`

New entity menus go through the builder. It owns the wrapper, trigger, menu container and all escaping. Pass raw strings.

```javascript
window.CRM.buildActionMenu([
    { type: "link", href: root + "/thing/view/" + id, icon: "fa-solid fa-eye", label: i18next.t("View") },
    canEdit && { type: "link", href: editUrl, icon: "fa-solid fa-pencil", label: i18next.t("Edit") },
    { type: "divider" },
    {
        type: "button",
        danger: true,                               // adds text-danger
        className: "delete-thing",                  // the delegated handler's hook
        icon: "fa-solid fa-trash",
        label: i18next.t("Delete"),
        data: { thing_id: id, thing_name: name },   // -> data-thing_id, data-thing_name
    },
]);
```

- Falsy entries are skipped, so `condition && item` works inline.
- `labelClass` wraps the label in a `<span>` (the cart item uses `cart-label`).
- `opts` accepts `wrapperClass` and `menuClass` for a non-default container.
- Encode user strings placed in hand-written attributes with `window.CRM.escapeAttribute()` (encodes quotes); `window.CRM.escapeHtml()` is for HTML text.

PHP-rendered tables write the standard HTML pattern directly.

## Person / Family Menu Order

| # | Item | Condition |
|---|------|-----------|
| 1 | View (`fa-solid fa-eye`) | Always |
| 2 | Edit (`fa-solid fa-pencil`) | Always |
| 2b | View Family (`fa-solid fa-users`) | When `familyId` is available |
| — | `dropdown-divider` | Always |
| 3 | Cart (`.AddToCart` / `.RemoveFromCart`) | Always |
| — | `dropdown-divider` | Always |
| 4 | Delete (`fa-solid fa-trash`, `text-danger`) | Always |

Person Delete is a `.delete-person` button with `data-person_id` and `data-person_name`, handled globally by `CRMJSOM.js`. Family Delete links to `SelectDelete.php?FamilyID={id}`.

Any table that lists people includes this standard menu. Domain actions (Check Out, Assign Role) sit between View/Edit/View Family and Cart, each group separated by dividers.

## Markup Rules

| Element | Use |
|---------|-----|
| Trigger class | `btn-ghost-secondary` |
| Trigger icon | `fa-solid fa-ellipsis-vertical` |
| Menu alignment | `dropdown-menu-end` |
| Aria | `aria-expanded="false"` |
| Styling | Bootstrap/Tabler classes; no inline `z-index` or `position` on trigger, menu, `<td>`, `.dropdown` |
| Click handling | Let events bubble so delegated handlers (`cart.js`) fire |
| Icon spacing | `me-2` on every icon |
| Dividers | Before destructive actions |
| Destructive items | `dropdown-item text-danger` |

## Overflow / Dropdown Clipping

`.table-responsive` and any `overflow: auto|scroll|hidden` ancestor clip an absolutely-positioned dropdown menu. `webpack/common/dropdown-overflow.ts`, loaded by `skin-core.js` into `churchcrm.min.js` (admin shell and Member Portal), handles every dropdown menu automatically.

- On `shown.bs.dropdown`, a toggle with an ancestor whose computed `overflow-x` or `overflow-y` is not `visible` (up to `body`) gets `.dropdown-menu-fixed` on its menu (`position: fixed`, defined in `_tabler-bridge.scss`).
- `top`/`left` come from the toggle's `getBoundingClientRect()`: right-aligned for `dropdown-menu-end`, flipped above the toggle when the menu would pass the viewport bottom.
- The menu is re-placed on `scroll` (capture) and `resize` while open, and released on `hide.bs.dropdown`.
- The DOM does not change, so Bootstrap focus handling and delegated click handlers keep working. No ancestor may set `transform`, `filter` or `perspective`.

Pages add nothing: wrap tables in plain `.table-responsive`, keep `data-bs-display="static"` on each trigger, and put no inline `overflow` styles on wrappers. A table that scrolls sideways (the Volunteer grids use `.volunteer-scroll-x`) works the same way.

Cypress check: at `cy.viewport(900, 800)` open a row menu in a scrolling wrapper and assert the menu has `dropdown-menu-fixed`, its rectangle is inside the viewport, and `document.elementFromPoint()` at its centre lands inside the menu.

## Cart Button in a Dropdown Item

`cart.js` delegates `.AddToCart` / `.RemoveFromCart` clicks and `updateButtonState` swaps the icon and `.cart-label` text for `.dropdown-item` buttons.

```php
<?php $inCart = isset($_SESSION['aPeopleCart']) && in_array($item->getId(), $_SESSION['aPeopleCart'], false); ?>
<button type="button"
    class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
    data-cart-id="<?= $item->getId() ?>"
    data-cart-type="person"
    data-label-add="<?= gettext('Add to Cart') ?>"
    data-label-remove="<?= gettext('Remove from Cart') ?>">
    <i class="<?= $inCart ? 'fa-solid fa-trash' : 'fa-solid fa-cart-shopping' ?> me-2"></i>
    <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
</button>
```

On the cart page (`/people/cart`) every person is in the cart: render the button in `RemoveFromCart text-danger` state with the trash icon, show View, Edit, View Family, divider, Remove from Cart, and omit Delete. The global `CartManager` handles the click.

## Order / Sort Actions

For reorderable rows, render the divider only when at least one move action is present.

```php
echo '<div class="dropdown-menu dropdown-menu-end">';
if ($row !== 1) {
    echo '<a class="dropdown-item" href="Editor.php?act=up&row_num=' . $row . '">
            <i class="fa-solid fa-arrow-up me-2"></i>' . gettext('Move up') . '</a>';
}
if ($row !== $numRows) {
    echo '<a class="dropdown-item" href="Editor.php?act=down&row_num=' . $row . '">
            <i class="fa-solid fa-arrow-down me-2"></i>' . gettext('Move down') . '</a>';
}
if ($row !== 1 || $row !== $numRows) {
    echo '<div class="dropdown-divider"></div>';
}
echo '<a class="dropdown-item text-danger" href="Editor.php?act=delete&ID=' . $id . '">
        <i class="fa-solid fa-trash me-2"></i>' . gettext('Delete') . '</a>';
echo '</div>';
```

## Table Header

```html
<th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
```

`w-1` collapses the column, `no-export` excludes it from DataTables CSV/Excel export, `text-center` centers it over the icon button.

## Checklist

- [ ] Trigger uses `btn-ghost-secondary`, `fa-solid fa-ellipsis-vertical`, and `data-bs-display="static"`
- [ ] Menu uses `dropdown-menu-end`
- [ ] Trigger carries `aria-expanded="false"` only
- [ ] No inline styles on trigger, menu, `<td>`, or `.dropdown`
- [ ] Row and dropdown let click events bubble
- [ ] Divider separates primary from destructive actions; destructive items use `text-danger`
- [ ] Icons have `me-2`
- [ ] Actions `<th>` has `w-1 text-center no-export`
