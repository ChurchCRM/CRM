/**
 * ChurchCRM Cart Management Module
 * Centralized cart operations with user feedback
 */

import $ from "jquery";
import { notify } from "./notifier";

/**
 * Cart Manager Class
 * Handles all cart operations with notifications and consistent UX
 */
export class CartManager {
    constructor() {
        // Wait for locales to be ready before initializing event handlers
        if (window.CRM && window.CRM.localesLoaded) {
            this.initializeEventHandlers();
        } else {
            window.addEventListener("CRM.localesReady", () => {
                this.initializeEventHandlers();
            });
        }
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
                if (showNotification) {
                    const addedCount = data.added ? data.added.length : 0;
                    const duplicateCount = data.duplicate ? data.duplicate.length : 0;

                    if (addedCount > 0 && duplicateCount === 0) {
                        const message =
                            addedCount === 1
                                ? i18next.t("Added to cart successfully")
                                : `${addedCount} ${i18next.t("people added to cart")}`;
                        this.showNotification("success", message);
                    } else if (addedCount === 0 && duplicateCount > 0) {
                        const message =
                            duplicateCount === 1
                                ? i18next.t("Person already in cart")
                                : `${duplicateCount} ${i18next.t("people already in cart")}`;
                        this.showNotification("warning", message);
                    } else if (addedCount > 0 && duplicateCount > 0) {
                        const message = `${addedCount} ${i18next.t("added")}, ${duplicateCount} ${i18next.t("already in cart")}`;
                        this.showNotification("warning", message);
                    }
                }

                this.refreshCartCount();

                if (options.hideButton) {
                    ids.forEach((id) => this.hideCartButton(id));
                } else {
                    ids.forEach((id) => this.updateButtonState(id, true));
                }

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
                if (showNotification) {
                    this.showNotification("danger", i18next.t("Failed to add to cart"));
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
        const showConfirm = options.confirm !== false;
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
                if (showNotification) {
                    const message =
                        ids.length === 1
                            ? i18next.t("Removed from cart successfully")
                            : `${ids.length} ${i18next.t("people removed from cart")}`;
                    this.showNotification("success", message);
                }

                this.refreshCartCount();

                ids.forEach((id) => this.updateButtonState(id, false));

                if (reloadPage) {
                    setTimeout(() => {
                        window.location.reload();
                    }, reloadDelay);
                }

                if (options && options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification("danger", i18next.t("Failed to remove from cart"));
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
                const duplicateCount = data.duplicate ? data.duplicate.length : 0;
                const addedCount = data.added ? data.added.length : 0;

                if (duplicateCount > 0 && addedCount > 0) {
                    this.showNotification(
                        "warning",
                        i18next.t("Added") +
                            ` ${addedCount} ` +
                            i18next.t("members. Already had") +
                            ` ${duplicateCount} ` +
                            i18next.t("in cart"),
                    );
                } else if (duplicateCount > 0) {
                    this.showNotification("warning", i18next.t("All members already in cart"));
                } else {
                    this.showNotification("success", i18next.t("Family added to cart"));
                }

                this.refreshCartCount();

                this.updateButtonState(familyId, true, "family");

                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                this.showNotification("danger", i18next.t("Failed to add family to cart"));
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
                if (showNotification) {
                    this.showNotification("success", i18next.t("Family removed from cart"));
                }

                this.refreshCartCount();

                this.updateButtonState(familyId, false, "family");

                if (options && options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                if (showNotification) {
                    this.showNotification("danger", i18next.t("Failed to remove family from cart"));
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
                this.showNotification("success", i18next.t("Group added to cart"));
                this.refreshCartCount();

                this.updateButtonState(groupId, true, "group");

                if (options.callback) {
                    options.callback(data);
                }
            })
            .fail((error) => {
                this.showNotification("danger", i18next.t("Failed to add group to cart"));
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
                            this.showNotification("success", i18next.t("Group removed from cart"));
                            this.refreshCartCount();

                            this.updateButtonState(groupId, false, "group");

                            if (options.callback) {
                                options.callback(data);
                            }
                        })
                        .fail((error) => {
                            this.showNotification("danger", i18next.t("Failed to remove group"));
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
        const reloadPage = options.reloadPage !== false && window.location.pathname.includes("/v2/cart");
        const reloadDelay = options.reloadDelay || 1500;

        return window.CRM.APIRequest({
            method: "DELETE",
            path: "cart/",
        })
            .done((data) => {
                this.showNotification("success", i18next.t("Cart emptied successfully"));
                this.refreshCartCount();

                this.resetAllButtons();

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
                this.showNotification("danger", i18next.t("Failed to empty cart"));
            });
    }

    emptyToGroup() {
        window.CRM.groups.promptSelection(
            {
                Type: window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role,
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
                        this.showNotification("success", i18next.t("Cart emptied to group successfully"));
                        this.refreshCartCount();
                        this.resetAllButtons();
                    })
                    .fail((error) => {
                        this.showNotification("danger", i18next.t("Failed to empty cart to group"));
                    });
            },
        );
    }

    /**
     * Show notification using Notyf
     * @param {string} type - Notification type (success, danger, warning, info)
     * @param {string} message - Message to display
     */
    showNotification(type, message) {
        notify(message, {
            type: type,
            delay: 3000,
        });
    }

    /**
     * Update button state (in cart vs not in cart)
     * @param {number} personId - Person ID
     * @param {boolean} inCart - Whether person is in cart
     */
    updateButtonState(cartId, inCart, cartType = "person") {
        // First try to find the container with the data attributes
        let $element = $(`[data-cart-id="${cartId}"][data-cart-type="${cartType}"]`);

        if (!$element.length) return;

        // If the element is a div wrapper, find the button inside; otherwise it's the button itself
        const $button = $element.is("button") ? $element : $element.find("button");

        if (!$button.length) return;

        if (inCart) {
            $element.removeClass("AddToCart").addClass("RemoveFromCart");
            $button.removeClass("btn-primary").addClass("btn-danger");

            const $icon = $button.find("i");
            $icon.removeClass("fa-cart-plus").addClass("fa-shopping-cart");
        } else {
            $element.removeClass("RemoveFromCart").addClass("AddToCart");
            $button.removeClass("btn-danger").addClass("btn-primary");

            const $icon = $button.find("i");
            $icon.removeClass("fa-shopping-cart").addClass("fa-cart-plus");
        }
    }

    hideCartButton(personId) {
        const $button = $(`[data-cart-id="${personId}"]`);
        $button.fadeOut(300, function () {
            $(this).remove();
        });
    }

    resetAllButtons() {
        $(".RemoveFromCart").each((index, button) => {
            const cartId = $(button).data("cart-id");
            if (cartId) {
                this.updateButtonState(cartId, false);
            }
        });
    }

    /**
     * Synchronize all cart buttons on the page with current cart state
     * Scans the DOM for all buttons with data-cart-id and data-cart-type attributes
     * and updates their visual state based on what's in the session cart
     * @param {Array} peopleCart - Array of person IDs currently in cart
     * @param {Array} familiesInCart - Array of family IDs with members in cart
     * @param {Array} groupsInCart - Array of group IDs in cart
     */
    syncButtonStates(peopleCart = [], familiesInCart = [], groupsInCart = []) {
        // Update all person buttons
        $('[data-cart-type="person"]').each((index, element) => {
            const personId = $(element).data("cart-id");
            if (personId) {
                const inCart = peopleCart.includes(personId);
                this.updateButtonState(personId, inCart, "person");
            }
        });

        // Update all family buttons
        $('[data-cart-type="family"]').each((index, element) => {
            const familyId = $(element).data("cart-id");
            if (familyId) {
                const inCart = familiesInCart.includes(familyId);
                this.updateButtonState(familyId, inCart, "family");
            }
        });

        // Update all group buttons
        $('[data-cart-type="group"]').each((index, element) => {
            const groupId = $(element).data("cart-id");
            if (groupId) {
                const inCart = groupsInCart.includes(groupId);
                this.updateButtonState(groupId, inCart, "group");
            }
        });
    }

    refreshCartCount() {
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

            this.updateCartDropdown(data.PeopleCart);

            this.animateCartIcon();
        });
    }

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
                        <i class="fa-solid fa-clipboard-list text-info"></i> ${i18next.t("Check In to Event")}
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

    initializeEventHandlers() {
        $(document).on("click", ".AddToCart", (e) => {
            e.preventDefault();
            let $element = $(e.currentTarget);

            // If clicked element is a button inside wrapper, find the wrapper
            if ($element.is("button")) {
                $element = $element.closest("[data-cart-id][data-cart-type]");
            }

            const cartId = $element.data("cart-id");
            const cartType = $element.data("cart-type");

            if (cartId && cartType) {
                if (cartType === "family") {
                    this.addFamily(cartId);
                } else if (cartType === "group") {
                    this.addGroup(cartId);
                } else {
                    this.addPerson(cartId);
                }
            }
        });

        $(document).on("click", ".RemoveFromCart", (e) => {
            e.preventDefault();
            let $element = $(e.currentTarget);

            // If clicked element is a button inside wrapper, find the wrapper
            if ($element.is("button")) {
                $element = $element.closest("[data-cart-id][data-cart-type]");
            }

            const cartId = $element.data("cart-id");
            const cartType = $element.data("cart-type");

            if (cartId && cartType) {
                if (cartType === "family") {
                    this.removeFamily(cartId);
                } else if (cartType === "group") {
                    this.removeGroup(cartId);
                } else {
                    this.removePerson(cartId);
                }
            }
        });

        $(document).on("click", ".emptyCart", (e) => {
            e.preventDefault();
            this.emptyCart();
        });

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

    // Wait for locales to be ready before refreshing cart count
    // (because refreshCartCount calls updateCartDropdown which uses i18next.t)
    const initializeCart = () => {
        window.CRM.cartManager.refreshCartCount().catch(() => {
            // APIRequest not available yet, skip initialization
        });
    };

    if (window.CRM && window.CRM.localesLoaded) {
        initializeCart();
    } else {
        window.addEventListener("CRM.localesReady", initializeCart);
    }
});
