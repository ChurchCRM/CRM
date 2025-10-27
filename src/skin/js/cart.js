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
     * @param {Function} options.callback - Callback function after success
     */
    addPerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        const showNotification = options.showNotification !== false;

        return window.CRM.APIRequest({
            method: "POST",
            path: "cart/",
            data: JSON.stringify({ Persons: ids }),
        })
            .done((data) => {
                // Show success notification
                if (showNotification) {
                    const message =
                        ids.length === 1
                            ? i18next.t("Added to cart successfully")
                            : i18next.t("{count} people added to cart", {
                                  count: ids.length,
                              });
                    this.showNotification("success", message);
                }

                // Update cart count
                this.refreshCartCount();

                // Hide or toggle buttons
                if (options.hideButton) {
                    ids.forEach((id) => this.hideCartButton(id));
                } else {
                    ids.forEach((id) => this.updateButtonState(id, true));
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
                console.error("Cart add failed:", error);
            });
    }

    /**
     * Remove person(s) from cart with confirmation
     * @param {number|number[]} personIds - Person ID or array of IDs
     * @param {Object} options - Configuration options
     * @param {boolean} options.confirm - Show confirmation dialog (default: true)
     * @param {boolean} options.showNotification - Show notification (default: true)
     * @param {Function} options.callback - Callback function after success
     */
    removePerson(personIds, options = {}) {
        const ids = Array.isArray(personIds) ? personIds : [personIds];
        const showConfirm = options.confirm !== false;
        const showNotification = options.showNotification !== false;

        // Show confirmation dialog using bootbox
        if (showConfirm) {
            const confirmMsg =
                ids.length === 1
                    ? i18next.t("Remove this person from cart?")
                    : i18next.t("Remove {count} people from cart?", {
                          count: ids.length,
                      });

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
                        this.performRemovePerson(
                            ids,
                            showNotification,
                            options.callback,
                        );
                    }
                },
            });
        } else {
            this.performRemovePerson(ids, showNotification, options.callback);
        }
    }

    /**
     * Internal method to perform person removal
     * @private
     */
    performRemovePerson(ids, showNotification, callback) {
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
                            : i18next.t("{count} people removed from cart", {
                                  count: ids.length,
                              });
                    this.showNotification("success", message);
                }

                // Update cart count
                this.refreshCartCount();

                // Update button states
                ids.forEach((id) => this.updateButtonState(id, false));

                // Call custom callback
                if (callback) {
                    callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification(
                        "danger",
                        i18next.t("Failed to remove from cart"),
                    );
                }
                console.error("Cart remove failed:", error);
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
                this.showNotification(
                    "success",
                    i18next.t("Family added to cart"),
                );
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
                        this.performEmptyCart(options.callback);
                    }
                },
            });
        } else {
            this.performEmptyCart(options.callback);
        }
    }

    /**
     * Internal method to perform cart emptying
     * @private
     */
    performEmptyCart(callback) {
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

                if (callback) {
                    callback(data);
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
            $button
                .removeClass("AddToPeopleCart")
                .addClass("RemoveFromPeopleCart");

            // Update icon
            $button
                .find("i.fa-cart-plus, i.fa.fa-cart-plus")
                .removeClass("fa-cart-plus")
                .addClass("fa-remove");

            // Update text if present
            $button
                .find(".cartActionDescription")
                .text(i18next.t("Remove from Cart"));
        } else {
            $button
                .removeClass("RemoveFromPeopleCart")
                .addClass("AddToPeopleCart");

            // Update icon
            $button
                .find("i.fa-remove, i.fa.fa-remove")
                .removeClass("fa-remove")
                .addClass("fa-cart-plus");

            // Update text if present
            $button
                .find(".cartActionDescription")
                .text(i18next.t("Add to Cart"));
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
                        <i class="fa-solid fa-shopping-cart text-green"></i> ${i18next.t("View Cart")}
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
            const personId = $(e.currentTarget).data("cartpersonid");
            if (personId) {
                this.addPerson(personId);
            }
        });

        // Remove from cart button click
        $(document).on("click", ".RemoveFromPeopleCart", (e) => {
            e.preventDefault();
            const personId = $(e.currentTarget).data("cartpersonid");
            if (personId) {
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

    // Initialize cart count on page load
    window.CRM.cartManager.refreshCartCount();
});
