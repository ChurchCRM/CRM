# Cart System Analysis & Improvement Recommendations

## Current State Analysis

### Cart API Structure (CRMJSOM.js)
**Location:** `src/skin/js/CRMJSOM.js` lines 74-268

**Current Methods:**
- `window.CRM.cart.empty()` - Empty entire cart
- `window.CRM.cart.emptyToGroup()` - Move cart to group
- `window.CRM.cart.addPerson(Persons, callback)` - Add person(s)
- `window.CRM.cart.removePerson(Persons, callback)` - Remove person(s)
- `window.CRM.cart.addFamily(FamilyID, callback)` - Add family members
- `window.CRM.cart.addGroup(GroupID, callback)` - Add group members
- `window.CRM.cart.removeGroup(GroupID, callback)` - Remove group
- `window.CRM.cart.refresh()` - Refresh cart UI
- `window.CRM.cart.updatePage(cartPeople)` - Update button states

### Current Cart Button Implementations

**Files with cart buttons:**
1. `src/v2/templates/people/person-list.php` - Person list table
2. `src/PersonView.php` - Person details page
3. `src/v2/templates/people/family-view.php` - Family members list
4. `src/QueryView.php` - Query results
5. `src/v2/templates/cart/cartview.php` - Cart page itself
6. `src/skin/js/GeoPage.js` - Geographic map page
7. `src/skin/js/GroupView.js` - Group view
8. `src/skin/js/FamilyView.js` - Family view

**Button Classes:**
- `.AddToPeopleCart` - Add button
- `.RemoveFromPeopleCart` - Remove button
- Data attribute: `data-cartpersonid="{personId}"`

### Current Issues & Gaps

1. **❌ No User Feedback**
   - No success/error notifications when adding/removing
   - No confirmation dialogs for deletions
   - User must look at cart icon to see changes

2. **❌ Inconsistent Button Handling**
   - Some pages reload (`location.reload()`)
   - Some toggle classes manually
   - Footer.js and CRMJSOM.js both manipulate buttons
   - Duplicate logic across files

3. **❌ No Button Hiding After Success**
   - Buttons toggle between Add/Remove states
   - Never completely removed from page

4. **❌ Mixed Scroll Behavior**
   - `cart.refresh()` includes `window.scrollTo(0, 0)` - unexpected
   - Jumps page to top on every cart change

5. **❌ Inconsistent Icon Manipulation**
   - Some code uses `.fa.fa-inverse`
   - Some uses `i:nth-child(2)`
   - Different selectors in different files

## Recommended Improvements

### 1. Create Unified Cart Module (Webpack)

**New File:** `src/skin/js/cart.js`

```javascript
/**
 * ChurchCRM Cart Management Module
 * Centralized cart operations with user feedback
 */

import $ from 'jquery';
import i18next from 'i18next';

export class CartManager {
    constructor() {
        this.initializeEventHandlers();
    }

    /**
     * Add person(s) to cart with notification
     */
    addPerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        
        return window.CRM.APIRequest({
            method: 'POST',
            path: 'cart/',
            data: JSON.stringify({ Persons: ids })
        }).done((data) => {
            // Show success notification
            this.showNotification('success', i18next.t('Added to cart successfully'));
            
            // Update cart count
            this.refreshCartCount();
            
            // Hide or toggle buttons
            if (options.hideButton) {
                ids.forEach(id => this.hideCartButton(id));
            } else {
                ids.forEach(id => this.updateButtonState(id, true));
            }
            
            // Call custom callback if provided
            if (options.callback) {
                options.callback(data);
            }
        }).fail((error) => {
            this.showNotification('danger', i18next.t('Failed to add to cart'));
        });
    }

    /**
     * Remove person(s) from cart with confirmation
     */
    removePerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        
        // Show confirmation dialog
        if (options.confirm !== false) {
            const confirmMsg = ids.length === 1 
                ? i18next.t('Remove this person from cart?')
                : i18next.t('Remove {count} people from cart?', { count: ids.length });
                
            if (!confirm(confirmMsg)) {
                return Promise.reject('User cancelled');
            }
        }
        
        return window.CRM.APIRequest({
            method: 'DELETE',
            path: 'cart/',
            data: JSON.stringify({ Persons: ids })
        }).done((data) => {
            // Show success notification
            this.showNotification('success', i18next.t('Removed from cart successfully'));
            
            // Update cart count
            this.refreshCartCount();
            
            // Update button states
            ids.forEach(id => this.updateButtonState(id, false));
            
            // Call custom callback
            if (options.callback) {
                options.callback(data);
            }
        }).fail((error) => {
            this.showNotification('danger', i18next.t('Failed to remove from cart'));
        });
    }

    /**
     * Empty entire cart with confirmation
     */
    emptyCart(options = {}) {
        if (options.confirm !== false) {
            if (!confirm(i18next.t('Empty your entire cart?'))) {
                return Promise.reject('User cancelled');
            }
        }
        
        return window.CRM.APIRequest({
            method: 'DELETE',
            path: 'cart/'
        }).done((data) => {
            this.showNotification('success', i18next.t('Cart emptied successfully'));
            this.refreshCartCount();
            
            // Reset all buttons on page
            this.resetAllButtons();
            
            if (options.callback) {
                options.callback(data);
            }
        });
    }

    /**
     * Show bootstrap-notify notification
     */
    showNotification(type, message) {
        $.notify({
            icon: type === 'success' ? 'fa fa-check' : 'fa fa-exclamation-triangle',
            message: message
        }, {
            type: type,
            delay: 3000,
            placement: {
                from: 'top',
                align: 'right'
            },
            offset: {
                x: 15,
                y: 60
            },
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            }
        });
    }

    /**
     * Update button state (in cart vs not in cart)
     */
    updateButtonState(personId, inCart) {
        const $button = $(`[data-cartpersonid="${personId}"]`);
        
        if (inCart) {
            $button
                .removeClass('AddToPeopleCart')
                .addClass('RemoveFromPeopleCart')
                .find('i.fa-cart-plus')
                .removeClass('fa-cart-plus')
                .addClass('fa-remove');
                
            $button.find('.cartActionDescription').text(i18next.t('Remove from Cart'));
        } else {
            $button
                .removeClass('RemoveFromPeopleCart')
                .addClass('AddToPeopleCart')
                .find('i.fa-remove')
                .removeClass('fa-remove')
                .addClass('fa-cart-plus');
                
            $button.find('.cartActionDescription').text(i18next.t('Add to Cart'));
        }
    }

    /**
     * Completely hide cart button (option for when person added)
     */
    hideCartButton(personId) {
        const $button = $(`[data-cartpersonid="${personId}"]`);
        $button.fadeOut(300, function() {
            $(this).remove();
        });
    }

    /**
     * Reset all cart buttons on page to "Add" state
     */
    resetAllButtons() {
        $('.RemoveFromPeopleCart').each((index, button) => {
            const personId = $(button).data('cartpersonid');
            this.updateButtonState(personId, false);
        });
    }

    /**
     * Refresh cart count in header WITHOUT scrolling
     */
    refreshCartCount() {
        return window.CRM.APIRequest({
            method: 'GET',
            path: 'cart/',
            suppressErrorDialog: true
        }).done((data) => {
            const count = data.PeopleCart.length;
            $('#iconCount').text(count);
            
            // Update dropdown menu
            this.updateCartDropdown(data.PeopleCart);
            
            // Animate cart icon (but don't scroll!)
            this.animateCartIcon();
        });
    }

    /**
     * Animate cart icon to show change
     */
    animateCartIcon() {
        const $cart = $('.fa-shopping-cart').parent();
        $cart.addClass('cart-pulse');
        setTimeout(() => $cart.removeClass('cart-pulse'), 600);
    }

    /**
     * Update cart dropdown menu
     */
    updateCartDropdown(cartPeople) {
        let menuHtml;
        
        if (cartPeople.length > 0) {
            menuHtml = `
                <li>
                    <a class="dropdown-item" href="${window.CRM.root}/v2/cart">
                        <i class="fa-solid fa-shopping-cart text-green"></i> ${i18next.t('View Cart')}
                    </a>
                    <a class="dropdown-item emptyCart">
                        <i class="fa-solid fa-trash text-danger"></i> ${i18next.t('Empty Cart')}
                    </a>
                    <a id="emptyCartToGroup" class="dropdown-item">
                        <i class="fa-solid fa-object-ungroup text-info"></i> ${i18next.t('Empty Cart to Group')}
                    </a>
                    <a href="${window.CRM.root}/CartToFamily.php" class="dropdown-item">
                        <i class="fa-solid fa-users text-info"></i> ${i18next.t('Empty Cart to Family')}
                    </a>
                    <a href="${window.CRM.root}/CartToEvent.php" class="dropdown-item">
                        <i class="fa-solid fa-clipboard-list text-info"></i> ${i18next.t('Empty Cart to Event')}
                    </a>
                    <a href="${window.CRM.root}/MapUsingGoogle.php?GroupID=0" class="dropdown-item">
                        <i class="fa-solid fa-map-marker text-info"></i> ${i18next.t('Map Cart')}
                    </a>
                </li>`;
        } else {
            menuHtml = `<a class="dropdown-item">${i18next.t('Your Cart is Empty')}</a>`;
        }
        
        $('#cart-dropdown-menu').html(menuHtml);
    }

    /**
     * Initialize event handlers
     */
    initializeEventHandlers() {
        // Add to cart button click
        $(document).on('click', '.AddToPeopleCart', (e) => {
            e.preventDefault();
            const personId = $(e.currentTarget).data('cartpersonid');
            this.addPerson(personId);
        });

        // Remove from cart button click
        $(document).on('click', '.RemoveFromPeopleCart', (e) => {
            e.preventDefault();
            const personId = $(e.currentTarget).data('cartpersonid');
            this.removePerson(personId);
        });

        // Empty cart button click
        $(document).on('click', '.emptyCart', (e) => {
            e.preventDefault();
            this.emptyCart();
        });

        // Empty cart to group
        $(document).on('click', '#emptyCartToGroup', (e) => {
            e.preventDefault();
            this.emptyToGroup();
        });
    }

    /**
     * Empty cart to group (existing functionality)
     */
    emptyToGroup() {
        window.CRM.groups.promptSelection({
            Type: window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role
        }, (selectedRole) => {
            window.CRM.APIRequest({
                method: 'POST',
                path: 'cart/emptyToGroup',
                data: JSON.stringify({
                    groupID: selectedRole.GroupID,
                    groupRoleID: selectedRole.RoleID
                })
            }).done((data) => {
                this.showNotification('success', i18next.t('Cart emptied to group successfully'));
                this.refreshCartCount();
                this.resetAllButtons();
            });
        });
    }
}

// Initialize cart manager on page load
$(document).ready(() => {
    window.CRM.cart = new CartManager();
});
```

### 2. Add Cart Pulse Animation (SCSS)

**File:** `src/skin/scss/_cart.scss`

```scss
// Cart icon pulse animation
@keyframes cart-pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
}

.cart-pulse {
    animation: cart-pulse 0.6s ease-in-out;
}

// Cart button states
.AddToPeopleCart {
    cursor: pointer;
    transition: opacity 0.3s ease;
    
    &:hover {
        opacity: 0.7;
    }
}

.RemoveFromPeopleCart {
    cursor: pointer;
    transition: opacity 0.3s ease;
    
    &:hover {
        opacity: 0.7;
    }
}
```

### 3. Update Webpack Configuration

**File:** `webpack.config.js`

Add cart.js to entry points:
```javascript
entry: {
    churchcrm: './src/skin/churchcrm.js',
    cart: './src/skin/js/cart.js',  // NEW
    // ... other entries
}
```

### 4. Migration Strategy

**Phase 1: Create new cart.js module**
- Create `src/skin/js/cart.js` with CartManager class
- Add to webpack build
- Add cart.scss styles
- Test in isolation

**Phase 2: Update CRMJSOM.js**
- Keep existing `window.CRM.cart` methods for backward compatibility
- Have them delegate to new CartManager
- Add deprecation warnings

**Phase 3: Update individual pages**
1. Start with `person-list.php` - remove inline handlers
2. Update `PersonView.php` - simplify cart buttons
3. Update `family-view.php`
4. Update other files one by one

**Phase 4: Update Footer.js**
- Remove duplicate cart event handlers
- Keep only initialization code

**Phase 5: Clean up**
- Remove deprecated methods
- Remove duplicate code
- Update tests

## Summary of Changes Needed

### Files to Create:
- ✅ `src/skin/js/cart.js` - New unified cart module
- ✅ `src/skin/scss/_cart.scss` - Cart-specific styles

### Files to Modify:
1. `webpack.config.js` - Add cart entry point
2. `src/skin/js/CRMJSOM.js` - Simplify, delegate to new module
3. `src/skin/js/Footer.js` - Remove duplicate handlers
4. `src/v2/templates/people/person-list.php` - Remove inline cart code
5. `src/PersonView.php` - Simplify cart button
6. `src/v2/templates/people/family-view.php` - Simplify cart buttons
7. `src/QueryView.php` - Update cart button handling
8. `src/skin/js/GeoPage.js` - Use new cart module
9. `src/skin/js/GroupView.js` - Use new cart module
10. `src/skin/js/FamilyView.js` - Use new cart module

### Features to Add:
- ✅ Bootstrap-notify notifications for all cart operations
- ✅ Confirmation dialogs for deletions
- ✅ Cart icon pulse animation on change
- ✅ Option to hide buttons after adding (not just toggle)
- ✅ Remove unwanted scroll-to-top behavior
- ✅ Consistent error handling
- ✅ Unified button state management

### Benefits:
1. **DRY Principle** - Single source of truth for cart logic
2. **Better UX** - Clear feedback via notifications
3. **Maintainability** - One place to fix bugs/add features
4. **Consistency** - Same behavior across all pages
5. **Modern** - ES6 classes, webpack bundling
6. **Testable** - Isolated cart logic

## Next Steps

1. Review and approve this design
2. Create cart.js module
3. Test with one page (person-list.php)
4. Gradually migrate other pages
5. Update Cypress tests
6. Deploy and monitor
