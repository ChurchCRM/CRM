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

window.CRM.DisplayErrorMessage = (endpoint, error) => {
  // Handle different error response formats (message, error, msg)
  const errorText =
    error && (error.message || error.error || error.msg)
      ? error.message || error.error || error.msg
      : i18next.t("Unknown error");

  const message =
    "<p>" +
    i18next.t("Error making API Call to") +
    ": " +
    endpoint +
    "</p>" +
    "<p>" +
    i18next.t("Error text") +
    ": " +
    errorText +
    "</p>";

  // Never include server side traces in the UI
  bootbox.alert({
    title: i18next.t("ERROR"),
    message: message,
  });
};

window.CRM.VerifyThenLoadAPIContent = (url) => {
  const fallbackError = i18next.t("There was a problem retrieving the requested object");

  if (!window.jQuery) {
    window.CRM.DisplayErrorMessage(url, { message: fallbackError });
    return;
  }

  // HEAD the URL first: if 2xx, open it. Otherwise GET the JSON body so we can
  // surface the server's error message. Both requests are async (no synchronous
  // XHR — Chrome deprecates `async: false`).
  window.jQuery
    .ajax({ method: "HEAD", url: url })
    .done(() => {
      window.open(url, "_blank", "noopener,noreferrer");
    })
    .fail(() => {
      window.jQuery
        .ajax({ method: "GET", url: url, dataType: "json" })
        .done((data) => {
          const msg = data && data.message ? data.message : fallbackError;
          window.CRM.DisplayErrorMessage(url, { message: msg });
        })
        .fail(() => {
          window.CRM.DisplayErrorMessage(url, { message: fallbackError });
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
    const options = {
      message:
        '<div class="modal-body">\
                  <input type="hidden" id="targetGroupAction">',
      buttons: {
        confirm: {
          label: i18next.t("OK"),
          className: "btn-success",
        },
        cancel: {
          label: i18next.t("Cancel"),
          className: "btn-danger",
        },
      },
    };
    let initFunction = () => {};

    // Read a value from a TomSelect-wrapped (or plain) select. TomSelect does
    // not always mirror its current selection back onto the underlying
    // <option selected> attribute, so `option:selected` is unreliable here —
    // always prefer the TomSelect instance API when available. Returns "" if
    // nothing is selected.
    const readSelectValue = (id) => {
      const el = document.getElementById(id);
      if (!el) return "";
      if (el.tomselect) {
        const v = el.tomselect.getValue();
        return v == null ? "" : String(v);
      }
      const jqVal = window.jQuery(el).val();
      return jqVal == null ? "" : String(jqVal);
    };

    if (selectOptions.Type & window.CRM.groups.selectTypes.Group) {
      options.title = i18next.t("Select Group");
      options.message +=
        '<span style="color: red">' +
        i18next.t("Please select target group for members") +
        ':</span>\
                  <select name="targetGroupSelection" id="targetGroupSelection" class="form-control"></select>';
      options.buttons.confirm.callback = () => {
        const groupId = readSelectValue("targetGroupSelection");
        if (!groupId) {
          bootbox.alert(i18next.t("Please select a group."));
          return false;
        }
        selectionCallback({ GroupID: groupId });
      };
    }
    if (selectOptions.Type & window.CRM.groups.selectTypes.Role) {
      options.title = i18next.t("Select Role");
      options.message +=
        '<span style="color: red">' +
        i18next.t("Please select target Role for members") +
        ':</span>\
                  <select name="targetRoleSelection" id="targetRoleSelection" class="form-control"></select>';
      options.buttons.confirm.callback = () => {
        const roleId = readSelectValue("targetRoleSelection");
        if (!roleId) {
          bootbox.alert(i18next.t("Please select a role."));
          return false;
        }
        selectionCallback({ RoleID: roleId });
      };
    }

    if (selectOptions.Type === window.CRM.groups.selectTypes.Role) {
      if (!selectOptions.GroupID) {
        throw i18next.t("GroupID required for role selection prompt");
      }
      initFunction = () => {
        // Remove tabindex from bootbox so TomSelect dropdown can receive focus
        window.jQuery(".bootbox").removeAttr("tabindex");
        window.CRM.groups.getRoles(selectOptions.GroupID).done((rdata) => {
          const roleEl = document.getElementById("targetRoleSelection");
          if (roleEl && roleEl.tomselect) roleEl.tomselect.destroy();
          new TomSelect(roleEl, {
            valueField: "id",
            labelField: "text",
            searchField: "text",
            options: rdata.map((item) => ({
              // i18next-disable-next-line
              text: i18next.t(item.OptionName),
              id: String(item.OptionId),
            })),
            dropdownParent: document.querySelector(".bootbox"),
          });
        });
      };
    }
    if (selectOptions.Type === (window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role)) {
      options.title = i18next.t("Select Group and Role");
      options.buttons.confirm.callback = () => {
        const groupId = readSelectValue("targetGroupSelection");
        const roleId = readSelectValue("targetRoleSelection");
        if (!groupId || !roleId) {
          bootbox.alert(i18next.t("Please select both a group and a role."));
          return false;
        }
        selectionCallback({ GroupID: groupId, RoleID: roleId });
      };
    }
    options.message += "</div>";
    bootbox.dialog(options).init(initFunction).show();

    window.CRM.groups.get().done((rdata) => {
      const groupsList = rdata.map((item) => ({
        text: item.Name,
        id: String(item.Id),
      }));
      window.jQuery("#targetGroupSelection").parents(".bootbox").removeAttr("tabindex");
      const groupEl = document.getElementById("targetGroupSelection");
      if (groupEl && groupEl.tomselect) groupEl.tomselect.destroy();
      new TomSelect(groupEl, {
        valueField: "id",
        labelField: "text",
        searchField: "text",
        options: groupsList,
        dropdownParent: document.querySelector(".bootbox"),
        onChange: (value) => {
          if (!value) return;
          const roleEl = document.getElementById("targetRoleSelection");
          if (roleEl && roleEl.tomselect) roleEl.tomselect.destroy();
          // Clear existing options
          while (roleEl && roleEl.options.length) roleEl.remove(0);
          window.CRM.groups.getRoles(value).done((rdata) => {
            const rolesList = rdata.map((item) => ({
              // i18next-disable-next-line
              text: i18next.t(item.OptionName),
              id: String(item.OptionId),
            }));
            new TomSelect(roleEl, {
              valueField: "id",
              labelField: "text",
              searchField: "text",
              options: rolesList,
              dropdownParent: document.querySelector(".bootbox"),
            });
          });
        },
      });
    });
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
    if (parsedResponse) {
      window.CRM.DisplayErrorMessage(jqXHR.url, parsedResponse);
    } else {
      window.CRM.DisplayErrorMessage(jqXHR.url, {
        message: textStatus + " " + errorThrown,
      });
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
  const escapedName = window.CRM.escapeHtml(personName || "");
  const familyItem = familyId
    ? '<a class="dropdown-item" href="' +
      root +
      "/people/family/" +
      familyId +
      '">' +
      '<i class="ti ti-users me-2"></i>' +
      i18next.t("View Family") +
      "</a>"
    : "";
  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="ti ti-dots-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/PersonView.php?PersonID=" +
    personId +
    '">' +
    '<i class="ti ti-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/PersonEditor.php?PersonID=" +
    personId +
    '">' +
    '<i class="ti ti-pencil me-2"></i>' +
    i18next.t("Edit") +
    "</a>" +
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
    (inCart ? "ti ti-shopping-cart-off" : "ti ti-shopping-cart-plus") +
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
    '<i class="ti ti-trash me-2"></i>' +
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
    '<i class="ti ti-dots-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/people/family/" +
    familyId +
    '">' +
    '<i class="ti ti-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/FamilyEditor.php?FamilyID=" +
    familyId +
    '">' +
    '<i class="ti ti-pencil me-2"></i>' +
    i18next.t("Edit") +
    "</a>" +
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
    (inCart ? "ti ti-shopping-cart-off" : "ti ti-shopping-cart-plus") +
    ' me-2"></i>' +
    '<span class="cart-label">' +
    (inCart ? i18next.t("Remove from Cart") : i18next.t("Add to Cart")) +
    "</span>" +
    "</button>" +
    '<div class="dropdown-divider"></div>' +
    '<a class="dropdown-item text-danger" href="' +
    root +
    "/SelectDelete.php?FamilyID=" +
    familyId +
    '">' +
    '<i class="ti ti-trash me-2"></i>' +
    i18next.t("Delete") +
    "</a>" +
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
      '<i class="ti ti-circle-check me-2"></i>' +
      i18next.t("Activate") +
      "</button>"
    : '<button type="button" class="dropdown-item deactivate-event" data-event_id="' +
      eventId +
      '">' +
      '<i class="ti ti-circle-x me-2"></i>' +
      i18next.t("Deactivate") +
      "</button>";

  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="ti ti-dots-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/event/view/" +
    eventId +
    '">' +
    '<i class="ti ti-eye me-2"></i>' +
    i18next.t("View") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/event/editor/" +
    eventId +
    '">' +
    '<i class="ti ti-pencil me-2"></i>' +
    i18next.t("Edit") +
    "</a>" +
    '<a class="dropdown-item" href="' +
    root +
    "/event/checkin/" +
    eventId +
    '">' +
    '<i class="ti ti-clipboard-check me-2"></i>' +
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
    '<i class="ti ti-trash me-2"></i>' +
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
          cancel: { label: '<i class="ti ti-x"></i>' + i18next.t("Cancel") },
          confirm: { label: '<i class="ti ti-trash"></i>' + i18next.t("Delete"), className: "btn-danger" },
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
          cancel: { label: '<i class="ti ti-x"></i>' + i18next.t("Cancel") },
          confirm: { label: '<i class="ti ti-trash"></i>' + i18next.t("Delete"), className: "btn-danger" },
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
