/**
 * ChurchCRM Cart Management Module
 * Centralized cart operations with user feedback
 */

import $ from "jquery";
import "bootstrap-notify";

/**
 * Cart Manager Class
 * Handles all cart operations with notifications and consistent UX
 */
export class CartManager {
    constructor() {
        this.initializeEventHandlers();
    }

    /**
     * Add person(s) to cart with notification
     * @param {number|number[]} personIds - Person ID or array of IDs
     * @param {Object} options - Configuration options
     * @param {boolean} options.hideButton - Hide button after adding (default: false)
     * @param {boolean} options.showNotification - Show notification (default: true)
     * @param {boolean} options.reloadPage - Reload page after operation (default: false)
     * @param {number} options.reloadDelay - Delay before reload in ms (default: 1500)
     * @param {Function} options.callback - Callback function after success
     */
    addPerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        const showNotification = options.showNotification !== false;
        const reloadPage = options.reloadPage === true;
        const reloadDelay = options.reloadDelay || 1500;

        return window.CRM.APIRequest({
            method: "POST",
            path: "cart/",
            data: JSON.stringify({ Persons: ids }),
        })
            .done((data) => {
                // Show appropriate notification based on results
                if (showNotification) {
                    const addedCount = data.added ? data.added.length : 0;
                    const duplicateCount = data.duplicate
                        ? data.duplicate.length
                        : 0;

                    if (addedCount > 0 && duplicateCount === 0) {
                        // All were added successfully
                        const message =
                            addedCount === 1
                                ? i18next.t("Added to cart successfully")
                                : `${addedCount} ${i18next.t("people added to cart")}`;
                        this.showNotification("success", message);
                    } else if (addedCount === 0 && duplicateCount > 0) {
                        // All were duplicates
                        const message =
                            duplicateCount === 1
                                ? i18next.t("Person already in cart")
                                : `${duplicateCount} ${i18next.t("people already in cart")}`;
                        this.showNotification("warning", message);
                    } else if (addedCount > 0 && duplicateCount > 0) {
                        // Mixed results
                        const message = `${addedCount} ${i18next.t("added")}, ${duplicateCount} ${i18next.t("already in cart")}`;
                        this.showNotification("warning", message);
                    }
                }

                // Update cart count
                this.refreshCartCount();

                // Hide or toggle buttons
                if (options.hideButton) {
                    ids.forEach((id) => this.hideCartButton(id));
                } else {
                    ids.forEach((id) => this.updateButtonState(id, true));
                }

                // Reload page if requested (useful for bulk operations)
                if (reloadPage) {
                    setTimeout(() => {
                        window.location.reload();
                    }, reloadDelay);
                }

                // Call custom callback if provided
                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification(
                        "danger",
                        i18next.t("Failed to add to cart"),
                    );
                }
            });
    }

    /**
     * Remove person(s) from cart with confirmation
     * @param {number|number[]} personIds - Person ID or array of IDs
     * @param {Object} options - Configuration options
     * @param {boolean} options.confirm - Show confirmation dialog (default: true)
     * @param {boolean} options.showNotification - Show notification (default: true)
     * @param {boolean} options.reloadPage - Reload page after operation (default: false)
     * @param {number} options.reloadDelay - Delay before reload in ms (default: 1500)
     * @param {Function} options.callback - Callback function after success
     */
    removePerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        const showConfirm = options.confirm !== false; // Fixed: reverted to original logic
        const showNotification = options.showNotification !== false;

        // Show confirmation dialog using bootbox
        if (showConfirm) {
            const confirmMsg =
                ids.length === 1
                    ? i18next.t("Remove this person from cart?")
                    : `${i18next.t("Remove")} ${ids.length} ${i18next.t("people from cart")}?`;

            bootbox.confirm({
                message: confirmMsg,
                buttons: {
                    confirm: {
                        label: i18next.t("Yes, Remove"),
                        className: "btn-danger",
                    },
                    cancel: {
                        label: i18next.t("Cancel"),
                        className: "btn-secondary",
                    },
                },
                callback: (result) => {
                    if (result) {
                        this.performRemovePerson(ids, options);
                    }
                },
            });
        } else {
            this.performRemovePerson(ids, options);
        }
    }

    /**
     * Internal method to perform person removal
     * @private
     */
    performRemovePerson(ids, options = {}) {
        const showNotification = options.showNotification !== false;
        const reloadPage = options.reloadPage === true;
        const reloadDelay = options.reloadDelay || 1500;

        return window.CRM.APIRequest({
            method: "DELETE",
            path: "cart/",
            data: JSON.stringify({ Persons: ids }),
        })
            .done((data) => {
                // Show success notification
                if (showNotification) {
                    const message =
                        ids.length === 1
                            ? i18next.t("Removed from cart successfully")
                            : `${ids.length} ${i18next.t("people removed from cart")}`;
                    this.showNotification("success", message);
                }

                // Update cart count
                this.refreshCartCount();

                // Update button states
                ids.forEach((id) => this.updateButtonState(id, false));

                // Reload page if requested (useful for bulk operations)
                if (reloadPage) {
                    setTimeout(() => {
                        window.location.reload();
                    }, reloadDelay);
                }

                // Call custom callback
                if (options && options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification(
                        "danger",
                        i18next.t("Failed to remove from cart"),
                    );
                }
            });
    }

    /**
     * Add entire family to cart
     * @param {number} familyId - Family ID
     * @param {Object} options - Configuration options
     */
    addFamily(familyId, options = {}) {
        return window.CRM.APIRequest({
            method: "POST",
            path: "cart/",
            data: JSON.stringify({ Family: familyId }),
        })
            .done((data) => {
                // Handle duplicate detection
                const duplicateCount = data.duplicate
                    ? data.duplicate.length
                    : 0;
                const addedCount = data.added ? data.added.length : 0;

                if (duplicateCount > 0 && addedCount > 0) {
                    // Mix of added and duplicates
                    this.showNotification(
                        "warning",
                        i18next.t("Added") +
                            ` ${addedCount} ` +
                            i18next.t("members. Already had") +
                            ` ${duplicateCount} ` +
                            i18next.t("in cart"),
                    );
                } else if (duplicateCount > 0) {
                    // All duplicates
                    this.showNotification(
                        "warning",
                        i18next.t("All members already in cart"),
                    );
                } else {
                    // All new additions
                    this.showNotification(
                        "success",
                        i18next.t("Family added to cart"),
                    );
                }

                this.refreshCartCount();

                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                this.showNotification(
                    "danger",
                    i18next.t("Failed to add family to cart"),
                );
            });
    }

    /**
     * Remove entire family from cart
     * @param {number} familyId - Family ID
     * @param {Object} options - Configuration options
     * @param {boolean} options.confirm - Show confirmation dialog (default: true)
     * @param {boolean} options.showNotification - Show notification (default: true)
     * @param {Function} options.callback - Callback function after success
     */
    removeFamily(familyId, options = {}) {
        const showConfirm = options.confirm !== false;
        const showNotification = options.showNotification !== false;

        // Show confirmation dialog using bootbox
        if (showConfirm) {
            const confirmMsg = i18next.t("Remove this family from cart?");

            bootbox.confirm({
                message: confirmMsg,
                buttons: {
                    confirm: {
                        label: i18next.t("Yes, Remove"),
                        className: "btn-danger",
                    },
                    cancel: {
                        label: i18next.t("Cancel"),
                        className: "btn-secondary",
                    },
                },
                callback: (result) => {
                    if (result) {
                        this.performRemoveFamily(familyId, options);
                    }
                },
            });
        } else {
            this.performRemoveFamily(familyId, options);
        }
    }

    /**
     * Internal method to perform family removal
     * @private
     */
    performRemoveFamily(familyId, options = {}) {
        const showNotification = options.showNotification !== false;

        return window.CRM.APIRequest({
            method: "DELETE",
            path: "cart/",
            data: JSON.stringify({ Family: familyId }),
        })
            .done((data) => {
                // Show success notification
                if (showNotification) {
                    this.showNotification(
                        "success",
                        i18next.t("Family removed from cart"),
                    );
                }

                // Update cart count
                this.refreshCartCount();

                // Call custom callback
                if (options && options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification(
                        "danger",
                        i18next.t("Failed to remove family from cart"),
                    );
                }
            });
    }

    /**
     * Add entire group to cart
     * @param {number} groupId - Group ID
     * @param {Object} options - Configuration options
     */
    addGroup(groupId, options = {}) {
        return window.CRM.APIRequest({
            method: "POST",
            path: "cart/",
            data: JSON.stringify({ Group: groupId }),
        })
            .done((data) => {
                this.showNotification(
                    "success",
                    i18next.t("Group added to cart"),
                );
                this.refreshCartCount();

                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                this.showNotification(
                    "danger",
                    i18next.t("Failed to add group to cart"),
                );
            });
    }

    /**
     * Remove entire group from cart
     * @param {number} groupId - Group ID
     * @param {Object} options - Configuration options
     */
    removeGroup(groupId, options = {}) {
        bootbox.confirm({
            message: i18next.t("Remove this group from cart?"),
            buttons: {
                confirm: {
                    label: i18next.t("Yes, Remove"),
                    className: "btn-danger",
                },
                cancel: {
                    label: i18next.t("Cancel"),
                    className: "btn-secondary",
                },
            },
            callback: (result) => {
                if (result) {
                    window.CRM.APIRequest({
                        method: "POST",
                        path: "cart/removeGroup",
                        data: JSON.stringify({ Group: groupId }),
                    })
                        .done((data) => {
                            this.showNotification(
                                "success",
                                i18next.t("Group removed from cart"),
                            );
                            this.refreshCartCount();

                            if (options.callback) {
                                options.callback(data);
                            }
                        })
                        .fail((error) => {
                            this.showNotification(
                                "danger",
                                i18next.t("Failed to remove group"),
                            );
                        });
                }
            },
        });
    }

    /**
     * Empty entire cart with confirmation
     * @param {Object} options - Configuration options
     * @param {boolean} options.confirm - Show confirmation dialog (default: true)
     * @param {boolean} options.reloadPage - Reload page after operation (default: true if on cart page)
     * @param {number} options.reloadDelay - Delay before reload in ms (default: 1500)
     * @param {Function} options.callback - Callback function after success
     */
    emptyCart(options = {}) {
        const showConfirm = options.confirm !== false;

        if (showConfirm) {
            bootbox.confirm({
                message: i18next.t("Empty your entire cart?"),
                buttons: {
                    confirm: {
                        label: i18next.t("Yes, Empty Cart"),
                        className: "btn-danger",
                    },
                    cancel: {
                        label: i18next.t("Cancel"),
                        className: "btn-secondary",
                    },
                },
                callback: (result) => {
                    if (result) {
                        this.performEmptyCart(options);
                    }
                },
            });
        } else {
            this.performEmptyCart(options);
        }
    }

    /**
     * Internal method to perform cart emptying
     * @private
     */
    performEmptyCart(options) {
        const reloadPage =
            options.reloadPage !== false &&
            window.location.pathname.includes("/v2/cart");
        const reloadDelay = options.reloadDelay || 1500;

        return window.CRM.APIRequest({
            method: "DELETE",
            path: "cart/",
        })
            .done((data) => {
                this.showNotification(
                    "success",
                    i18next.t("Cart emptied successfully"),
                );
                this.refreshCartCount();

                // Reset all buttons on page
                this.resetAllButtons();

                // Reload page if on cart page
                if (reloadPage) {
                    setTimeout(() => {
                        window.location.reload();
                    }, reloadDelay);
                }

                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                this.showNotification(
                    "danger",
                    i18next.t("Failed to empty cart"),
                );
            });
    }

    /**
     * Empty cart to group with group selection
     */
    emptyToGroup() {
        window.CRM.groups.promptSelection(
            {
                Type:
                    window.CRM.groups.selectTypes.Group |
                    window.CRM.groups.selectTypes.Role,
            },
            (selectedRole) => {
                window.CRM.APIRequest({
                    method: "POST",
                    path: "cart/emptyToGroup",
                    data: JSON.stringify({
                        groupID: selectedRole.GroupID,
                        groupRoleID: selectedRole.RoleID,
                    }),
                })
                    .done((data) => {
                        this.showNotification(
                            "success",
                            i18next.t("Cart emptied to group successfully"),
                        );
                        this.refreshCartCount();
                        this.resetAllButtons();
                    })
                    .fail((error) => {
                        this.showNotification(
                            "danger",
                            i18next.t("Failed to empty cart to group"),
                        );
                    });
            },
        );
    }

    /**
     * Show bootstrap-notify notification
     * @param {string} type - Notification type (success, danger, warning, info)
     * @param {string} message - Message to display
     */
    showNotification(type, message) {
        $.notify(
            {
                icon:
                    type === "success"
                        ? "fa fa-check"
                        : "fa fa-exclamation-triangle",
                message: message,
            },
            {
                type: type,
                delay: 3000,
                placement: {
                    from: "top",
                    align: "right",
                },
                offset: {
                    x: 15,
                    y: 60,
                },
                animate: {
                    enter: "animated fadeInDown",
                    exit: "animated fadeOutUp",
                },
            },
        );
    }

    /**
     * Update button state (in cart vs not in cart)
     * @param {number} personId - Person ID
     * @param {boolean} inCart - Whether person is in cart
     */
    updateButtonState(personId, inCart) {
        const $button = $(`[data-cartpersonid="${personId}"]`);

        if (!$button.length) return;

        if (inCart) {
            // Change to Remove state
            $button
                .removeClass("AddToPeopleCart")
                .addClass("RemoveFromPeopleCart");

            // Update the inner button
            const $innerBtn = $button.find("button");
            $innerBtn.removeClass("btn-primary").addClass("btn-danger");

            // Update icon
            const $icon = $innerBtn.find("i");
            $icon.removeClass("fa-cart-plus").addClass("fa-shopping-cart");
        } else {
            // Change to Add state
            $button
                .removeClass("RemoveFromPeopleCart")
                .addClass("AddToPeopleCart");

            // Update the inner button
            const $innerBtn = $button.find("button");
            $innerBtn.removeClass("btn-danger").addClass("btn-primary");

            // Update icon
            const $icon = $innerBtn.find("i");
            $icon.removeClass("fa-shopping-cart").addClass("fa-cart-plus");
        }
    }

    /**
     * Completely hide cart button (option for when person added)
     * @param {number} personId - Person ID
     */
    hideCartButton(personId) {
        const $button = $(`[data-cartpersonid="${personId}"]`);
        $button.fadeOut(300, function () {
            $(this).remove();
        });
    }

    /**
     * Reset all cart buttons on page to "Add" state
     */
    resetAllButtons() {
        $(".RemoveFromPeopleCart").each((index, button) => {
            const personId = $(button).data("cartpersonid");
            if (personId) {
                this.updateButtonState(personId, false);
            }
        });
    }

    /**
     * Refresh cart count in header WITHOUT scrolling
     */
    refreshCartCount() {
        // Safety check for APIRequest availability
        if (!window.CRM || typeof window.CRM.APIRequest !== "function") {
            return Promise.reject("APIRequest not available");
        }

        return window.CRM.APIRequest({
            method: "GET",
            path: "cart/",
            suppressErrorDialog: true,
        }).done((data) => {
            const count = data.PeopleCart.length;
            $("#iconCount").text(count);

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
        const $cartIcon = $(".fa-shopping-cart").parent();
        $cartIcon.addClass("cart-pulse");
        setTimeout(() => $cartIcon.removeClass("cart-pulse"), 600);
    }

    /**
     * Update cart dropdown menu
     * @param {number[]} cartPeople - Array of person IDs in cart
     */
    updateCartDropdown(cartPeople) {
        let menuHtml;

        if (cartPeople.length > 0) {
            menuHtml = `
                <li>
                    <a class="dropdown-item" href="${window.CRM.root}/v2/cart">
                        <i class="fa-solid fa-eye text-primary"></i> ${i18next.t("View Cart")}
                    </a>
                    <a class="dropdown-item emptyCart">
                        <i class="fa-solid fa-trash text-danger"></i> ${i18next.t("Empty Cart")}
                    </a>
                    <a id="emptyCartToGroup" class="dropdown-item">
                        <i class="fa-solid fa-object-ungroup text-info"></i> ${i18next.t("Empty Cart to Group")}
                    </a>
                    <a href="${window.CRM.root}/CartToFamily.php" class="dropdown-item">
                        <i class="fa-solid fa-users text-info"></i> ${i18next.t("Empty Cart to Family")}
                    </a>
                    <a href="${window.CRM.root}/CartToEvent.php" class="dropdown-item">
                        <i class="fa-solid fa-clipboard-list text-info"></i> ${i18next.t("Empty Cart to Event")}
                    </a>
                    <a href="${window.CRM.root}/MapUsingGoogle.php?GroupID=0" class="dropdown-item">
                        <i class="fa-solid fa-map-marker text-info"></i> ${i18next.t("Map Cart")}
                    </a>
                </li>`;
        } else {
            menuHtml = `<a class="dropdown-item">${i18next.t("Your Cart is Empty")}</a>`;
        }

        $("#cart-dropdown-menu").html(menuHtml);
    }

    /**
     * Initialize event handlers for cart buttons
     */
    initializeEventHandlers() {
        // Add to cart button click
        $(document).on("click", ".AddToPeopleCart", (e) => {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const familyId = $button.data("familyid");
            const personId = $button.data("cartpersonid");

            if (familyId) {
                // Add family to cart
                this.addFamily(familyId);
            } else if (personId) {
                // Add person to cart
                this.addPerson(personId);
            }
        });

        // Remove from cart button click
        $(document).on("click", ".RemoveFromPeopleCart", (e) => {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const familyId = $button.data("familyid");
            const personId = $button.data("cartpersonid");

            if (familyId) {
                // For families, we need to get the family members and remove them
                // We can't remove by family ID alone, so we just reload the page
                // or we need to get all members first. For now, just don't do anything
                // and let the family-list.php handler take care of it.
                // Actually, we should just call removePerson with personId which has "-fam"
                // No wait, that won't work because it's not numeric.
                // The best approach is to NOT handle family removes here.
                // Just handle person removes.
                if (personId && !personId.includes("-fam")) {
                    this.removePerson(personId);
                }
            } else if (personId) {
                // Remove person from cart
                this.removePerson(personId);
            }
        });

        // Empty cart button click
        $(document).on("click", ".emptyCart", (e) => {
            e.preventDefault();
            this.emptyCart();
        });

        // Empty cart to group
        $(document).on("click", "#emptyCartToGroup", (e) => {
            e.preventDefault();
            this.emptyToGroup();
        });
    }
}

// Initialize cart manager on page load and expose to window.CRM
$(document).ready(() => {
    if (!window.CRM) {
        window.CRM = {};
    }
    window.CRM.cartManager = new CartManager();

    // Initialize cart count on page load - handle promise rejection gracefully
    window.CRM.cartManager.refreshCartCount().catch(() => {
        // APIRequest not available yet, skip initialization
        // This can happen during testing before the API is ready
    });
});
