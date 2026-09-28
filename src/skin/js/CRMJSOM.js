/*
 * ChurchCRM JavaScript Object Model Initialization Script
 */

// Ensure jQuery is available — churchcrm.min.js sets window.jQuery globally
if (!window.jQuery) {
  console.warn("[CRMJSOM] jQuery not available at script load time");
}

/**
 * Escape HTML special characters to prevent XSS when inserting user data into DOM
 * GHSA-8r36-fvxj-26qv: Used to sanitize property values before rendering
 * @param {string} text - The text to escape
 * @returns {string} - HTML-escaped text safe for DOM insertion
 */
window.CRM.escapeHtml = (text) => {
  if (text === null || text === undefined) {
    return "";
  }
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
};
/**
 * Escape text for safe insertion into HTML attribute values (e.g. title="...", value="...").
 * Extends escapeHtml to also encode double and single quotes so attackers cannot
 * break out of a quoted attribute context.
 * GHSA-369j-c5w2-48m4: attribute-context escaping for dashboard DataTables render callbacks
 * @param {string} text - The text to escape
 * @returns {string} - Text safe for use inside HTML attribute values
 */
window.CRM.escapeAttribute = (text) => {
  if (text === null || text === undefined) {
    return "";
  }
  return window.CRM.escapeHtml(text).replace(/"/g, "&quot;").replace(/'/g, "&#39;");
};

window.CRM.APIRequest = (options) => {
  // Guard against jQuery not being available
  if (!window.jQuery || typeof window.jQuery.ajax !== "function") {
    console.error("[CRMJSOM.APIRequest] jQuery.ajax not available");
    return Promise.reject(new Error("jQuery not available - cannot make API request"));
  }

  if (!options.method) {
    options.method = "GET";
  }
  options.dataType = "json";
  options.url = window.CRM.root + "/api/" + options.path;
  options.contentType = "application/json";
  options.beforeSend = (jqXHR, settings) => {
    jqXHR.url = settings.url;
  };
  // Only install the default error handler if the caller did not supply one —
  // otherwise the caller's handler was silently discarded.
  if (typeof options.error !== "function") {
    options.error = (jqXHR, textStatus, errorThrown) => {
      window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
    };
  }
  return window.jQuery.ajax(options);
};

/**
 * Admin-only API Request wrapper
 * Used for endpoints in /admin/api/* - does NOT add /api prefix
 * Endpoint paths should be like "upgrade/download-latest-release" which becomes "/admin/api/upgrade/download-latest-release"
 */
window.CRM.AdminAPIRequest = (options) => {
  // Guard against jQuery not being available
  if (!window.jQuery || typeof window.jQuery.ajax !== "function") {
    console.error("[CRMJSOM.AdminAPIRequest] jQuery.ajax not available");
    return Promise.reject(new Error("jQuery not available - cannot make API request"));
  }

  if (!options.method) {
    options.method = "GET";
  }
  options.dataType = "json";
  options.url = window.CRM.root + "/admin/api/" + options.path;
  options.contentType = "application/json";
  options.beforeSend = (jqXHR, settings) => {
    jqXHR.url = settings.url;
  };
  // Only install the default error handler if the caller did not supply one —
  // otherwise the caller's handler was silently discarded.
  if (typeof options.error !== "function") {
    options.error = (jqXHR, textStatus, errorThrown) => {
      window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
    };
  }
  return window.jQuery.ajax(options);
};

window.CRM.VerifyThenLoadAPIContent = (url) => {
  const fallbackError = i18next.t("There was a problem retrieving the requested object");

  const showError = (msg) => {
    if (window.CRM && typeof window.CRM.notify === "function") {
      window.CRM.notify(msg, { type: "danger", delay: 6000 });
    } else if (typeof alert === "function") {
      alert(msg);
    }
  };

  if (!window.jQuery) {
    showError(fallbackError);
    return;
  }

  // Pre-open a blank tab synchronously so browsers tie it to the originating
  // user gesture — popup blockers reject `window.open` from async callbacks.
  // We navigate it on success, or close it on failure. Setting `opener = null`
  // prevents reverse-tabnabbing; we can't pass `noopener` here because that
  // would force `window.open` to return null and leave us nothing to navigate.
  const pendingWindow = window.open("", "_blank");
  if (pendingWindow) {
    pendingWindow.opener = null;
  }

  // HEAD the URL first: if 2xx, navigate the pre-opened tab. Otherwise GET
  // the JSON body so we can surface the server's error message. Both requests
  // are async (Chrome deprecates `async: false`).
  window.jQuery
    .ajax({ method: "HEAD", url: url })
    .done(() => {
      if (pendingWindow && !pendingWindow.closed) {
        pendingWindow.location = url;
      } else {
        window.open(url, "_blank", "noopener,noreferrer");
      }
    })
    .fail(() => {
      if (pendingWindow && !pendingWindow.closed) {
        pendingWindow.close();
      }
      window.jQuery
        .ajax({ method: "GET", url: url, dataType: "json" })
        .done((data) => {
          const msg = data && data.message ? data.message : fallbackError;
          showError(msg);
        })
        .fail(() => {
          showError(fallbackError);
        });
    });
};

window.CRM.groups = {
  get: () =>
    window.CRM.APIRequest({
      path: "groups/",
      method: "GET",
    }),
  getRoles: (GroupID) =>
    window.CRM.APIRequest({
      path: "groups/" + GroupID + "/roles",
      method: "GET",
    }),
  selectTypes: {
    Group: 1,
    Role: 2,
  },
  promptSelection: (selectOptions, selectionCallback) => {
    // Determine the dialog title based on what the caller wants to select
    const isGroupAndRole =
      selectOptions.Type === (window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role);
    const isGroupOnly = selectOptions.Type === window.CRM.groups.selectTypes.Group;
    const isRoleOnly = selectOptions.Type === window.CRM.groups.selectTypes.Role;

    if (isRoleOnly && !selectOptions.GroupID) {
      console.error("[CRM.groups.promptSelection] GroupID is required for role-only selection");
      return;
    }

    // Build a unique modal ID so multiple calls don't conflict
    const modalId = "crm-group-select-modal-" + Date.now();

    // Build the modal body HTML
    let bodyHtml = "";
    if (isGroupOnly || isGroupAndRole) {
      bodyHtml +=
        '<div class="mb-3">' +
        '<label class="form-label fw-semibold">' +
        i18next.t("Select Group") +
        "</label>" +
        '<select id="crm-gs-group" class="form-select"></select>' +
        "</div>";
    }
    if (isRoleOnly || isGroupAndRole) {
      bodyHtml +=
        '<div class="mb-3' +
        (isGroupAndRole ? " d-none" : "") +
        '" id="crm-gs-role-wrapper">' +
        '<label class="form-label fw-semibold">' +
        i18next.t("Select Role") +
        "</label>" +
        '<select id="crm-gs-role" class="form-select"></select>' +
        "</div>";
    }

    // Determine dialog title
    let modalTitle = i18next.t("Select Group");
    if (isRoleOnly) modalTitle = i18next.t("Select Role");
    if (isGroupAndRole) modalTitle = i18next.t("Select Group and Role");

    // Create a Bootstrap 5 modal programmatically
    // (avoids bootbox v6 / TomSelect incompatibilities with .init() and dropdownParent)
    const existing = document.getElementById(modalId);
    if (existing) existing.remove();

    const wrapper = document.createElement("div");
    wrapper.id = modalId;
    wrapper.className = "modal fade";
    wrapper.setAttribute("tabindex", "-1");
    wrapper.setAttribute("aria-modal", "true");
    wrapper.setAttribute("role", "dialog");
    wrapper.innerHTML =
      '<div class="modal-dialog modal-dialog-centered">' +
      '<div class="modal-content">' +
      '<div class="modal-header">' +
      '<h5 class="modal-title">' +
      window.CRM.escapeHtml(modalTitle) +
      "</h5>" +
      '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' +
      i18next.t("Cancel") +
      '"></button>' +
      "</div>" +
      '<div class="modal-body">' +
      bodyHtml +
      "</div>" +
      '<div class="modal-footer">' +
      '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="crm-gs-cancel">' +
      i18next.t("Cancel") +
      "</button>" +
      '<button type="button" class="btn btn-primary" id="crm-gs-confirm" disabled>' +
      i18next.t("OK") +
      "</button>" +
      "</div>" +
      "</div></div>";

    document.body.appendChild(wrapper);
    const bsModal = new window.bootstrap.Modal(wrapper, { backdrop: "static" });

    // Clean up DOM when the modal is fully hidden
    const cleanup = () => {
      try {
        if (groupSelectInstance) {
          groupSelectInstance.destroy();
          groupSelectInstance = null;
        }
        if (roleSelectInstance) {
          roleSelectInstance.destroy();
          roleSelectInstance = null;
        }
      } catch (err) {
        console.error("[promptSelection] Error destroying TomSelect instances:", err);
      }
      try {
        bsModal.dispose();
      } catch (err) {
        console.error("[promptSelection] Error disposing modal:", err);
      }
      // Always remove the wrapper, even if cleanup fails
      if (wrapper.parentNode) {
        wrapper.remove();
      }
    };

    wrapper.addEventListener("hidden.bs.modal", cleanup, { once: true });

    const confirmBtn = wrapper.querySelector("#crm-gs-confirm");
    const roleWrapper = wrapper.querySelector("#crm-gs-role-wrapper");

    let selectedGroupId = null;
    let selectedRoleId = null;
    let groupSelectInstance = null;
    let roleSelectInstance = null;
    let noRolesAvailable = false;

    // Helper: enable/disable the confirm button based on current selection state
    const updateConfirmState = () => {
      if (isGroupOnly) {
        confirmBtn.disabled = !selectedGroupId;
      } else if (isRoleOnly) {
        confirmBtn.disabled = !selectedRoleId;
      } else {
        // Group + Role: require a group; role is optional if the group has no roles
        confirmBtn.disabled = !selectedGroupId;
      }
    };

    // Initialize TomSelect controls once the modal is fully visible.
    // shown.bs.modal fires after the CSS transition so elements are measured correctly.
    wrapper.addEventListener(
      "shown.bs.modal",
      () => {
        if (isGroupOnly || isGroupAndRole) {
          const groupEl = wrapper.querySelector("#crm-gs-group");

          // Fetch all groups and populate the TomSelect
          window.CRM.groups.get().done((rdata) => {
            const groupOptions = rdata.map((item) => ({
              text: item.Name,
              id: String(item.Id),
            }));

            groupSelectInstance = new TomSelect(groupEl, {
              valueField: "id",
              labelField: "text",
              searchField: "text",
              options: groupOptions,
              placeholder: i18next.t("Search groups..."),
              items: [],
              dropdownParent: "body",
              onChange: (value) => {
                selectedGroupId = value || null;
                updateConfirmState();

                if (!isGroupAndRole) return;

                // Disable confirm while roles are loading to prevent premature submit
                confirmBtn.disabled = true;

                // When the user selects a group, load its roles
                const roleEl = wrapper.querySelector("#crm-gs-role");
                if (roleEl && roleEl.tomselect) {
                  roleEl.tomselect.destroy();
                  roleSelectInstance = null;
                }
                selectedRoleId = null;

                if (!value) {
                  if (roleWrapper) roleWrapper.classList.add("d-none");
                  return;
                }

                window.CRM.groups.getRoles(value).done((roles) => {
                  if (!roles || roles.length === 0) {
                    // Group has no roles — hide the role picker and allow confirm
                    if (roleWrapper) roleWrapper.classList.add("d-none");
                    confirmBtn.disabled = false;
                    return;
                  }

                  // Auto-select the only role when there is exactly one
                  if (roles.length === 1) {
                    selectedRoleId = String(roles[0].OptionId);
                    if (roleWrapper) roleWrapper.classList.add("d-none");
                    confirmBtn.disabled = false;
                    return;
                  }

                  // Multiple roles — show the role picker
                  if (roleWrapper) roleWrapper.classList.remove("d-none");

                  const roleOptions = roles.map((r) => ({
                    // i18next-disable-next-line
                    text: i18next.t(r.OptionName),
                    id: String(r.OptionId),
                  }));
                  selectedRoleId = roleOptions[0].id; // default to first role
                  roleSelectInstance = new TomSelect(roleEl, {
                    valueField: "id",
                    labelField: "text",
                    searchField: "text",
                    options: roleOptions,
                    items: [selectedRoleId],
                    dropdownParent: "body",
                    onChange: (v) => {
                      selectedRoleId = v || null;
                      updateConfirmState();
                    },
                  });
                  confirmBtn.disabled = false;
                });
              },
            });
          });
        }

        if (isRoleOnly) {
          // Role-only: load roles for the pre-supplied GroupID
          const roleEl = wrapper.querySelector("#crm-gs-role");
          window.CRM.groups.getRoles(selectOptions.GroupID).done((roles) => {
            if (!roles || roles.length === 0) {
              // No roles configured — allow proceed; caller receives RoleID: null
              noRolesAvailable = true;
              confirmBtn.disabled = false;
              return;
            }
            const roleOptions = roles.map((r) => ({
              // i18next-disable-next-line
              text: i18next.t(r.OptionName),
              id: String(r.OptionId),
            }));
            selectedRoleId = roleOptions[0].id;
            roleSelectInstance = new TomSelect(roleEl, {
              valueField: "id",
              labelField: "text",
              searchField: "text",
              options: roleOptions,
              items: [selectedRoleId],
              dropdownParent: "body",
              onChange: (v) => {
                selectedRoleId = v || null;
                updateConfirmState();
              },
            });
            confirmBtn.disabled = false;
          });
        }
      },
      { once: true },
    );

    // Confirm button handler
    confirmBtn.addEventListener("click", () => {
      if (isGroupOnly) {
        if (!selectedGroupId) {
          window.CRM.notify(i18next.t("Please select a group."), { type: "warning", delay: 3000 });
          return;
        }
        bsModal.hide();
        selectionCallback({ GroupID: selectedGroupId });
      } else if (isRoleOnly) {
        if (!selectedRoleId && !noRolesAvailable) {
          window.CRM.notify(i18next.t("Please select a role."), { type: "warning", delay: 3000 });
          return;
        }
        bsModal.hide();
        selectionCallback({ RoleID: selectedRoleId });
      } else {
        // Group + Role
        if (!selectedGroupId) {
          window.CRM.notify(i18next.t("Please select a group."), { type: "warning", delay: 3000 });
          return;
        }
        bsModal.hide();
        selectionCallback({ GroupID: selectedGroupId, RoleID: selectedRoleId });
      }
    });

    bsModal.show();
  },
  addPerson: (GroupID, PersonID, RoleID) => {
    const params = {
      method: "POST",
      path: "groups/" + GroupID + "/addperson/" + PersonID,
    };
    if (RoleID) {
      params.data = JSON.stringify({
        RoleID: RoleID,
      });
    }
    return window.CRM.APIRequest(params);
  },
  removePerson: (GroupID, PersonID) =>
    window.CRM.APIRequest({
      method: "DELETE", // define the type of HTTP verb we want to use (POST for our form)
      path: "groups/" + GroupID + "/removeperson/" + PersonID,
    }),
  addGroup: (callbackM) => {
    bootbox.prompt({
      title: i18next.t("Add A Group Name"),
      value: i18next.t("Default Name Group"),
      onEscape: true,
      closeButton: true,
      buttons: {
        confirm: {
          label: i18next.t("Yes"),
          className: "btn-success",
        },
        cancel: {
          label: i18next.t("No"),
          className: "btn-danger",
        },
      },
      callback: (result) => {
        if (!result) {
          return;
        }
        window.CRM.APIRequest({
          method: "POST",
          path: "groups/",
          data: JSON.stringify({ groupName: result }),
        }).done((data) => {
          if (window.CRM.cartManager && typeof window.CRM.cartManager.refreshCartCount === "function") {
            window.CRM.cartManager.refreshCartCount();
          }
          if (callbackM) {
            callbackM(data);
          }
        });
      },
    });
  },
};

window.CRM.system = {
  runTimerJobs: () => {
    window.CRM.APIRequest({
      method: "POST",
      path: "background/timerjobs",
      suppressErrorDialog: true,
    });
  },
  handlejQAJAXError: (jqXHR, textStatus, errorThrown, suppressErrorDialog) => {
    if (jqXHR.status === 401) {
      window.location = window.CRM.root + "/session/begin?location=" + window.location.pathname;
    }
    if (textStatus === "abort" || suppressErrorDialog) {
      return;
    }
    let parsedResponse = null;
    try {
      parsedResponse = JSON.parse(jqXHR.responseText);
    } catch (_err) {
      parsedResponse = null;
    }
    const message =
      (parsedResponse && (parsedResponse.message || parsedResponse.error || parsedResponse.msg)) ||
      `${textStatus || i18next.t("Error")} ${errorThrown || ""}`.trim() ||
      i18next.t("Unknown error");
    if (window.CRM && typeof window.CRM.notify === "function") {
      window.CRM.notify(message, { type: "danger", delay: 6000 });
    }
  },
};

window.CRM.dashboard = {
  /**
   * Load event counters once on page load (birthdays, anniversaries, events today)
   */
  loadEventCounters: () => {
    // Pass the browser's local date so the counter matches the calendar's "today" cell.
    // FullCalendar highlights today using the browser local date, not the server timezone.
    const today = new Date().toLocaleDateString("en-CA"); // yields YYYY-MM-DD
    window.CRM.APIRequest({
      method: "GET",
      path: "calendar/events-counters?date=" + today,
      suppressErrorDialog: true,
    }).done((data) => {
      document.getElementById("BirthdateNumber").innerText = data.Birthdays;
      document.getElementById("AnniversaryNumber").innerText = data.Anniversaries;
      document.getElementById("EventsNumber").innerText = data.Events;
    });
  },

  /**
   * Load open deposit count once on page load
   * Used by Finance menu badge to show real-time count of open deposits
   */
  loadOpenDepositCount: () => {
    const el = document.getElementById("openDeposits");
    if (!el) return; // Finance menu not present for this user
    window.CRM.APIRequest({
      method: "GET",
      path: "deposits/open-count",
      suppressErrorDialog: true,
    }).done((data) => {
      el.innerText = data.count;
    });
  },

  /**
   * Load active fundraiser count once on page load for menu badge.
   * Replaces session-cached count, ensuring always fresh data.
   */
  loadFundraiserCount: () => {
    const el = document.getElementById("activeFundraisers");
    if (!el) return; // Fundraiser menu badge not present for this user (feature disabled or no permission)
    window.CRM.APIRequest({
      method: "GET",
      path: "fundraisers/active-count",
      suppressErrorDialog: true,
    }).done((data) => {
      el.innerText = data.count;
    });
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Row action menus.
//
// `buildActionMenu()` owns the dropdown scaffold and all escaping; the three
// entity renderers below only describe their item lists. Wrapped in an IIFE so
// the shared cart-item helper stays private to this block.
// `.agents/skills/churchcrm/table-action-menu.md` documents the markup emitted here.
// ─────────────────────────────────────────────────────────────────────────────
(function setupActionMenuBuilders() {
  /**
   * One entry in the item list `window.CRM.buildActionMenu()` accepts.
   *
   * @typedef {Object} CRMActionMenuItem
   * @property {"link"|"button"|"divider"} type
   * @property {string} [href] - `link` only.
   * @property {string} [icon] - Font Awesome classes, e.g. "fa-solid fa-eye"; `me-2` is appended.
   * @property {string} [label] - Visible text.
   * @property {string} [className] - Extra classes appended to `dropdown-item`.
   * @property {boolean} [danger] - Prefixes `text-danger`; use for destructive items.
   * @property {string} [labelClass] - Wraps the label in a `<span>` carrying this class.
   * @property {Object} [data] - `data-*` attributes, keyed without the `data-` prefix.
   * @property {boolean} [classBeforeType] - `button` only; emit `class=` before `type=`.
   */

  /**
   * Build the canonical Tabler row-action dropdown.
   *
   * This is the single place the scaffold is written: the wrapper, the
   * `btn-ghost-secondary` trigger (including `data-bs-display="static"`, which is
   * load-bearing — without it the menu is clipped inside a scrolling table
   * container, see #9373), the `fa-ellipsis-vertical` icon and the
   * `dropdown-menu dropdown-menu-end` container.
   *
   * It is also the single place menu content is escaped: everything landing in an
   * attribute (`href`, classes, every `data-*` value) goes through
   * `window.CRM.escapeAttribute()`, and every label goes through
   * `window.CRM.escapeHtml()`. Callers pass raw strings and must not pre-escape.
   *
   * @param {Array<CRMActionMenuItem|null|false|undefined>} items - Falsy entries are
   *   skipped, so callers can write `condition && item` inline.
   * @param {Object} [opts]
   * @param {string} [opts.wrapperClass="dropdown"]
   * @param {string} [opts.menuClass="dropdown-menu dropdown-menu-end"]
   * @returns {string} HTML string
   */
  window.CRM.buildActionMenu = (items, opts) => {
    const options = opts || {};
    const escapeAttribute = window.CRM.escapeAttribute;
    const escapeHtml = window.CRM.escapeHtml;

    // ` data-foo="a" data-bar="b"` — the one place data-* values are escaped.
    // GHSA-hm7v-jrhm-fmfx: escapeAttribute (encodes quotes) for data-* attribute context.
    const dataAttributes = (data) =>
      data
        ? Object.keys(data)
            .map((key) => " data-" + key + '="' + escapeAttribute(data[key]) + '"')
            .join("")
        : "";

    const classAttribute = (item) => {
      const extra = ((item.danger ? "text-danger " : "") + (item.className || "")).trim();
      return 'class="dropdown-item' + (extra ? " " + escapeAttribute(extra) : "") + '"';
    };

    const itemBody = (item) => {
      const icon = item.icon ? '<i class="' + escapeAttribute(item.icon) + ' me-2"></i>' : "";
      const label = escapeHtml(item.label || "");
      return (
        icon + (item.labelClass ? '<span class="' + escapeAttribute(item.labelClass) + '">' + label + "</span>" : label)
      );
    };

    const renderItem = (item) => {
      if (!item) {
        return "";
      }
      if (item.type === "divider") {
        return '<div class="dropdown-divider"></div>';
      }
      if (item.type === "link") {
        return (
          "<a " +
          classAttribute(item) +
          ' href="' +
          escapeAttribute(item.href || "") +
          '"' +
          dataAttributes(item.data) +
          ">" +
          itemBody(item) +
          "</a>"
        );
      }
      // classBeforeType keeps the cart button's historical attribute order, so the
      // markup is unchanged from the hand-written renderers this replaced.
      const leading = item.classBeforeType
        ? classAttribute(item) + ' type="button"'
        : 'type="button" ' + classAttribute(item);
      return "<button " + leading + dataAttributes(item.data) + ">" + itemBody(item) + "</button>";
    };

    return (
      '<div class="' +
      escapeAttribute(options.wrapperClass || "dropdown") +
      '">' +
      '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
      '<i class="fa-solid fa-ellipsis-vertical"></i>' +
      "</button>" +
      '<div class="' +
      escapeAttribute(options.menuClass || "dropdown-menu dropdown-menu-end") +
      '">' +
      items.map(renderItem).join("") +
      "</div></div>"
    );
  };

  /**
   * The Add/Remove-from-Cart item the person and family menus share verbatim.
   * cart.js's delegated `.AddToCart` / `.RemoveFromCart` handlers and its
   * `updateButtonState()` depend on this exact class / data / `.cart-label` shape.
   *
   * @param {number} id
   * @param {"person"|"family"} cartType
   * @param {boolean} inCart
   * @returns {CRMActionMenuItem}
   */
  const cartItem = (id, cartType, inCart) => ({
    type: "button",
    classBeforeType: true,
    className: inCart ? "RemoveFromCart text-danger" : "AddToCart",
    icon: inCart ? "fa-solid fa-box-open" : "fa-solid fa-cart-shopping",
    label: inCart ? i18next.t("Remove from Cart") : i18next.t("Add to Cart"),
    labelClass: "cart-label",
    data: {
      "cart-id": id,
      "cart-type": cartType,
      "label-add": i18next.t("Add to Cart"),
      "label-remove": i18next.t("Remove from Cart"),
    },
  });

  const canEditRecords = () => Boolean(window.CRM.permissions?.editRecords);

  /**
   * Render a standard person action dropdown menu.
   * Standard order: View → Edit → [View Family] → [divider] → Cart → [divider] → Delete
   * @param {number} personId
   * @param {string} personName - Used in delete confirmation
   * @param {Object} [options]
   * @param {boolean} [options.inCart=false] - Whether person is already in cart
   * @param {number} [options.familyId] - When set, adds a "View Family" item after Edit
   * @returns {string} HTML string
   */
  window.CRM.renderPersonActionMenu = (personId, personName, options) => {
    options = options || {};
    const root = window.CRM.root;
    return window.CRM.buildActionMenu([
      {
        type: "link",
        href: `${root}/people/view/${personId}`,
        icon: "fa-solid fa-eye",
        label: i18next.t("View"),
      },
      canEditRecords() && {
        type: "link",
        href: `${root}/PersonEditor.php?PersonID=${personId}`,
        icon: "fa-solid fa-pencil",
        label: i18next.t("Edit"),
      },
      options.familyId && {
        type: "link",
        href: `${root}/people/family/${options.familyId}`,
        icon: "fa-solid fa-users",
        label: i18next.t("View Family"),
      },
      { type: "divider" },
      cartItem(personId, "person", options.inCart || false),
      { type: "divider" },
      {
        type: "button",
        danger: true,
        className: "delete-person",
        icon: "fa-solid fa-trash",
        label: i18next.t("Delete"),
        data: { person_id: personId, person_name: personName || "" },
      },
    ]);
  };

  /**
   * Render a standard family action dropdown menu.
   * Standard order: View → Edit → [divider] → Cart → [divider] → Delete
   * @param {number} familyId
   * @param {string} _familyName - Unused; kept for parity with the person renderer
   * @param {Object} [options]
   * @param {boolean} [options.inCart=false] - Whether family is already in cart
   * @returns {string} HTML string
   */
  window.CRM.renderFamilyActionMenu = (familyId, _familyName, options) => {
    options = options || {};
    const root = window.CRM.root;
    return window.CRM.buildActionMenu([
      {
        type: "link",
        href: `${root}/people/family/${familyId}`,
        icon: "fa-solid fa-eye",
        label: i18next.t("View"),
      },
      canEditRecords() && {
        type: "link",
        href: `${root}/FamilyEditor.php?FamilyID=${familyId}`,
        icon: "fa-solid fa-pencil",
        label: i18next.t("Edit"),
      },
      { type: "divider" },
      cartItem(familyId, "family", options.inCart || false),
      { type: "divider" },
      {
        type: "button",
        danger: true,
        className: "delete-family",
        icon: "fa-solid fa-trash",
        label: i18next.t("Delete"),
        data: { family_id: familyId },
      },
    ]);
  };

  /**
   * Render a standard event action dropdown menu.
   * Standard order: View → Edit → Check-in → [divider] → Activate/Deactivate → [divider] → Delete
   *
   * @param {number} eventId
   * @param {string} eventTitle - Used in delete confirmation
   * @param {Object} [options]
   * @param {boolean} [options.inactive=false] - Current event status (controls Activate vs Deactivate)
   * @returns {string} HTML string
   */
  window.CRM.renderEventActionMenu = (eventId, eventTitle, options) => {
    options = options || {};
    const root = window.CRM.root;
    const inactive = options.inactive || false;
    return window.CRM.buildActionMenu([
      {
        type: "link",
        href: `${root}/event/view/${eventId}`,
        icon: "fa-solid fa-eye",
        label: i18next.t("View"),
      },
      {
        type: "link",
        href: `${root}/event/editor/${eventId}`,
        icon: "fa-solid fa-pencil",
        label: i18next.t("Edit"),
      },
      {
        type: "link",
        href: `${root}/event/checkin/${eventId}`,
        icon: "fa-solid fa-clipboard-check",
        label: i18next.t("Check-in"),
      },
      { type: "divider" },
      inactive
        ? {
            type: "button",
            className: "activate-event",
            icon: "fa-solid fa-circle-check",
            label: i18next.t("Activate"),
            data: { event_id: eventId },
          }
        : {
            type: "button",
            className: "deactivate-event",
            icon: "fa-solid fa-circle-xmark",
            label: i18next.t("Deactivate"),
            data: { event_id: eventId },
          },
      { type: "divider" },
      {
        type: "button",
        danger: true,
        className: "delete-event",
        icon: "fa-solid fa-trash",
        label: i18next.t("Delete"),
        data: { event_id: eventId, event_title: eventTitle || "" },
      },
    ]);
  };
})();

// Global delegated handlers for .delete-event / .activate-event / .deactivate-event
// rendered by renderEventActionMenu in DataTables and PHP templates.
(function setupEventActionHandlers() {
  function register() {
    if (!window.jQuery) return;
    const $ = window.jQuery;

    $(document).on("click", ".delete-event", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = $(this);
      const eventId = $btn.data("event_id");
      // jQuery's .data() returns the browser-decoded attribute value, so the
      // attribute escaping buildActionMenu() applied is undone here. Re-escape
      // before embedding into the bootbox HTML message to prevent XSS.
      const eventTitle = window.CRM.escapeHtml(String($btn.data("event_title") || ""));
      bootbox.confirm({
        title: i18next.t("Delete this event?"),
        message:
          i18next.t("Deleting an event will also delete all attendance counts. This cannot be undone.") +
          " <b>" +
          eventTitle +
          "</b>",
        buttons: {
          cancel: { label: '<i class="fa-solid fa-xmark"></i>' + i18next.t("Cancel") },
          confirm: { label: '<i class="fa-solid fa-trash"></i>' + i18next.t("Delete"), className: "btn-danger" },
        },
        callback: (result) => {
          if (result) {
            window.CRM.APIRequest({ method: "DELETE", path: "events/" + eventId }).done(() => {
              location.reload();
            });
          }
        },
      });
    });

    function setEventStatus(eventId, active) {
      window.CRM.APIRequest({
        method: "POST",
        path: "events/" + eventId + "/status",
        data: JSON.stringify({ active: active }),
      }).done(() => {
        location.reload();
      });
    }

    $(document).on("click", ".activate-event", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setEventStatus($(this).data("event_id"), true);
    });

    $(document).on("click", ".deactivate-event", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setEventStatus($(this).data("event_id"), false);
    });
  }
  if (window.CRM && window.CRM.localesLoaded) {
    register();
  } else {
    window.addEventListener("CRM.localesReady", register, { once: true });
  }
})();

// Global delegated handler for .delete-person buttons (rendered in DataTables or PHP templates).
// Set up after locales are ready so i18next.t() is available in the confirmation dialog.
(function setupPersonDeleteHandler() {
  function register() {
    if (!window.jQuery) return;
    window.jQuery(document).on("click", ".delete-person", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = window.jQuery(this);
      const personId = $btn.data("person_id");
      const personName = $btn.data("person_name");
      bootbox.confirm({
        title: i18next.t("Delete this person?"),
        message:
          i18next.t("Do you want to delete this person?  This cannot be undone.") +
          " <b>" +
          window.CRM.escapeHtml(String(personName || "")) +
          "</b>",
        buttons: {
          cancel: { label: '<i class="fa-solid fa-xmark"></i>' + i18next.t("Cancel") },
          confirm: { label: '<i class="fa-solid fa-trash"></i>' + i18next.t("Delete"), className: "btn-danger" },
        },
        callback: (result) => {
          if (result) {
            window.CRM.APIRequest({ method: "DELETE", path: "person/" + personId }).done(() => {
              window.location.href = window.CRM.root + "/people/list";
            });
          }
        },
      });
    });
  }
  if (window.CRM && window.CRM.localesLoaded) {
    register();
  } else {
    window.addEventListener("CRM.localesReady", register, { once: true });
  }
})();

// Global delegated handler for .delete-family buttons (rendered in DataTables or PHP templates).
// Set up after locales are ready so i18next.t() is available in the confirmation dialog.
(function setupFamilyDeleteHandler() {
  function register() {
    if (!window.jQuery) return;
    window.jQuery(document).on("click", ".delete-family", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = window.jQuery(this);
      const familyId = $btn.data("family_id");
      bootbox.confirm({
        title: i18next.t("Delete this family?"),
        message: i18next.t(
          "Do you want to delete this family? You'll be taken to a page to choose what to delete. This cannot be undone.",
        ),
        buttons: {
          cancel: { label: '<i class="fa-solid fa-xmark"></i>' + i18next.t("Cancel") },
          confirm: { label: '<i class="fa-solid fa-trash"></i>' + i18next.t("Delete"), className: "btn-danger" },
        },
        callback: (result) => {
          if (result) {
            window.location.href = window.CRM.root + "/SelectDelete.php?FamilyID=" + familyId;
          }
        },
      });
    });
  }
  if (window.CRM && window.CRM.localesLoaded) {
    register();
  } else {
    window.addEventListener("CRM.localesReady", register, { once: true });
  }
})();

/**
 * Copy text to the clipboard with a success toast, falling back to a prompt dialog.
 * @param {string} text - The text to copy
 * @param {string} [successMsg] - Optional toast message on success
 */
window.CRM.copyToClipboard = (text, successMsg) => {
  const msg = successMsg || i18next.t("Copied to clipboard");
  if (navigator.clipboard) {
    return navigator.clipboard
      .writeText(text)
      .then(() => {
        window.CRM.notify(msg, { type: "success", delay: 3000 });
      })
      .catch(() => {
        prompt(i18next.t("Press CTRL + C to copy"), text);
      });
  }
  prompt(i18next.t("Press CTRL + C to copy"), text);
  return Promise.resolve();
};
