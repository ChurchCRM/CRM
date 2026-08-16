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

    // Fallback timeout: ensure cleanup happens if hidden.bs.modal doesn't fire
    setTimeout(() => {
      if (wrapper.parentNode) {
        cleanup();
      }
    }, 2000);

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
};

/**
 * Render a standard person action dropdown menu.
 * Standard order: View → Edit → [divider] → Cart → [divider] → Delete
 * @param {number} personId
 * @param {string} personName - Used in delete confirmation
 * @param {Object} [options]
 * @param {boolean} [options.inCart=false] - Whether person is already in cart
 * @returns {string} HTML string
 */
window.CRM.renderPersonActionMenu = (personId, personName, options) => {
  options = options || {};
  const inCart = options.inCart || false;
  const familyId = options.familyId || null;
  const root = window.CRM.root;
  // GHSA-hm7v-jrhm-fmfx: use escapeAttribute (encodes quotes) for data-* attribute context
  const escapedName = window.CRM.escapeAttribute(personName || "");
  const familyItem = familyId
    ? '<a class="dropdown-item" href="' +
      root +
      "/people/family/" +
      familyId +
      '">' +
      '<i class="fa-solid fa-users me-2"></i>' +
      i18next.t("View Family") +
      "</a>"
    : "";
  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="fa-solid fa-ellipsis-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/people/view/" +
    personId +
    '">' +
    '<i class="fa-solid fa-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    (window.CRM.permissions && window.CRM.permissions.editRecords
      ? '<a class="dropdown-item" href="' +
        root +
        "/PersonEditor.php?PersonID=" +
        personId +
        '">' +
        '<i class="fa-solid fa-pencil me-2"></i>' +
        i18next.t("Edit") +
        "</a>"
      : "") +
    familyItem +
    '<div class="dropdown-divider"></div>' +
    '<button class="dropdown-item ' +
    (inCart ? "RemoveFromCart text-danger" : "AddToCart") +
    '" type="button"' +
    ' data-cart-id="' +
    personId +
    '" data-cart-type="person"' +
    ' data-label-add="' +
    i18next.t("Add to Cart") +
    '" data-label-remove="' +
    i18next.t("Remove from Cart") +
    '">' +
    '<i class="' +
    (inCart ? "fa-solid fa-trash" : "fa-solid fa-cart-shopping") +
    ' me-2"></i>' +
    '<span class="cart-label">' +
    (inCart ? i18next.t("Remove from Cart") : i18next.t("Add to Cart")) +
    "</span>" +
    "</button>" +
    '<div class="dropdown-divider"></div>' +
    '<button type="button" class="dropdown-item text-danger delete-person"' +
    ' data-person_id="' +
    personId +
    '" data-person_name="' +
    escapedName +
    '">' +
    '<i class="fa-solid fa-trash me-2"></i>' +
    i18next.t("Delete") +
    "</button>" +
    "</div></div>"
  );
};

/**
 * Render a standard family action dropdown menu.
 * Standard order: View → Edit → [divider] → Cart → [divider] → Delete
 * @param {number} familyId
 * @param {string} familyName - Used in delete confirmation (unused currently but kept for parity)
 * @param {Object} [options]
 * @param {boolean} [options.inCart=false] - Whether family is already in cart
 * @returns {string} HTML string
 */
window.CRM.renderFamilyActionMenu = (familyId, _familyName, options) => {
  options = options || {};
  const inCart = options.inCart || false;
  const root = window.CRM.root;
  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="fa-solid fa-ellipsis-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/people/family/" +
    familyId +
    '">' +
    '<i class="fa-solid fa-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    (window.CRM.permissions && window.CRM.permissions.editRecords
      ? '<a class="dropdown-item" href="' +
        root +
        "/FamilyEditor.php?FamilyID=" +
        familyId +
        '">' +
        '<i class="fa-solid fa-pencil me-2"></i>' +
        i18next.t("Edit") +
        "</a>"
      : "") +
    '<div class="dropdown-divider"></div>' +
    '<button class="dropdown-item ' +
    (inCart ? "RemoveFromCart text-danger" : "AddToCart") +
    '" type="button"' +
    ' data-cart-id="' +
    familyId +
    '" data-cart-type="family"' +
    ' data-label-add="' +
    i18next.t("Add to Cart") +
    '" data-label-remove="' +
    i18next.t("Remove from Cart") +
    '">' +
    '<i class="' +
    (inCart ? "fa-solid fa-trash" : "fa-solid fa-cart-shopping") +
    ' me-2"></i>' +
    '<span class="cart-label">' +
    (inCart ? i18next.t("Remove from Cart") : i18next.t("Add to Cart")) +
    "</span>" +
    "</button>" +
    '<div class="dropdown-divider"></div>' +
    '<button type="button" class="dropdown-item text-danger delete-family"' +
    ' data-family_id="' +
    familyId +
    '">' +
    '<i class="fa-solid fa-trash me-2"></i>' +
    i18next.t("Delete") +
    "</button>" +
    "</div></div>"
  );
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
  const inactive = options.inactive || false;
  const root = window.CRM.root;
  const escapedTitle = window.CRM.escapeHtml(eventTitle || "");

  const statusButton = inactive
    ? '<button type="button" class="dropdown-item activate-event" data-event_id="' +
      eventId +
      '">' +
      '<i class="fa-solid fa-circle-check me-2"></i>' +
      i18next.t("Activate") +
      "</button>"
    : '<button type="button" class="dropdown-item deactivate-event" data-event_id="' +
      eventId +
      '">' +
      '<i class="fa-solid fa-circle-xmark me-2"></i>' +
      i18next.t("Deactivate") +
      "</button>";

  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="fa-solid fa-ellipsis-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/event/view/" +
    eventId +
    '">' +
    '<i class="fa-solid fa-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/event/editor/" +
    eventId +
    '">' +
    '<i class="fa-solid fa-pencil me-2"></i>' +
    i18next.t("Edit") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/event/checkin/" +
    eventId +
    '">' +
    '<i class="fa-solid fa-clipboard-check me-2"></i>' +
    i18next.t("Check-in") +
    "</a>" +
    '<div class="dropdown-divider"></div>' +
    statusButton +
    '<div class="dropdown-divider"></div>' +
    '<button type="button" class="dropdown-item text-danger delete-event"' +
    ' data-event_id="' +
    eventId +
    '" data-event_title="' +
    escapedTitle +
    '">' +
    '<i class="fa-solid fa-trash me-2"></i>' +
    i18next.t("Delete") +
    "</button>" +
    "</div></div>"
  );
};

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
      // escaping applied by renderEventActionMenu() is undone here. Re-escape
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
