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
window.CRM.escapeHtml = function (text) {
  if (text === null || text === undefined) {
    return "";
  }
  var div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
};

window.CRM.APIRequest = function (options) {
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
  options.beforeSend = function (jqXHR, settings) {
    jqXHR.url = settings.url;
  };
  options.error = function (jqXHR, textStatus, errorThrown) {
    window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
  };
  return window.jQuery.ajax(options);
};

/**
 * Admin-only API Request wrapper
 * Used for endpoints in /admin/api/* - does NOT add /api prefix
 * Endpoint paths should be like "upgrade/download-latest-release" which becomes "/admin/api/upgrade/download-latest-release"
 */
window.CRM.AdminAPIRequest = function (options) {
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
  options.beforeSend = function (jqXHR, settings) {
    jqXHR.url = settings.url;
  };
  options.error = function (jqXHR, textStatus, errorThrown) {
    window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, options.suppressErrorDialog);
  };
  return window.jQuery.ajax(options);
};

window.CRM.DisplayErrorMessage = function (endpoint, error) {
  // Handle different error response formats (message, error, msg)
  var errorText =
    error && (error.message || error.error || error.msg)
      ? error.message || error.error || error.msg
      : i18next.t("Unknown error");

  var message =
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

window.CRM.VerifyThenLoadAPIContent = function (url) {
  var error = i18next.t("There was a problem retrieving the requested object");

  // Helper function to fetch error message from JSON response
  function fetchErrorMessage(targetUrl, fallbackError, callback) {
    try {
      if (!window.jQuery) {
        callback(fallbackError);
        return;
      }
      window.jQuery.ajax({
        method: "GET",
        url: targetUrl,
        async: false,
        dataType: "json",
        success: function (data) {
          var msg = data && data.message ? data.message : fallbackError;
          callback(msg);
        },
        error: function () {
          callback(fallbackError);
        },
      });
    } catch (e) {
      callback(fallbackError);
    }
  }

  if (!window.jQuery) {
    window.CRM.DisplayErrorMessage(url, { message: error });
    return;
  }

  window.jQuery.ajax({
    method: "HEAD",
    url: url,
    async: false,
    statusCode: {
      200: function () {
        window.open(url);
      },
      404: function () {
        fetchErrorMessage(url, error, function (msg) {
          window.CRM.DisplayErrorMessage(url, { message: msg });
        });
      },
      500: function () {
        fetchErrorMessage(url, error, function (msg) {
          window.CRM.DisplayErrorMessage(url, { message: msg });
        });
      },
    },
  });
};

window.CRM.kiosks = {
  assignmentTypes: {
    1: "Event Attendance",
    2: "Self Registration",
    3: "Self Checkin",
    4: "General Attendance",
  },
  reload: function (id) {
    window.CRM.APIRequest({
      path: "kiosks/" + id + "/reloadKiosk",
      method: "POST",
    }).done(function (data) {});
  },
  enableRegistration: function () {
    return window.CRM.APIRequest({
      path: "kiosks/allowRegistration",
      method: "POST",
    });
  },
  accept: function (id) {
    window.CRM.APIRequest({
      path: "kiosks/" + id + "/acceptKiosk",
      method: "POST",
    }).done(function (data) {
      window.CRM.kioskDataTable.ajax.reload();
    });
  },
  identify: function (id) {
    window.CRM.APIRequest({
      path: "kiosks/" + id + "/identifyKiosk",
      method: "POST",
    }).done(function (data) {
      //do nothing...
    });
  },
  setAssignment: function (id, assignmentId) {
    let assignmentSplit = assignmentId.split("-");
    let assignmentType, eventId;
    if (assignmentSplit.length > 0) {
      assignmentType = assignmentSplit[0];
      eventId = assignmentSplit[1];
    } else {
      assignmentType = assignmentId;
    }

    window.CRM.APIRequest({
      path: "kiosks/" + id + "/setAssignment",
      method: "POST",
      data: JSON.stringify({
        assignmentType: assignmentType,
        eventId: eventId,
      }),
    }).done(function (data) {});
  },
};

window.CRM.groups = {
  get: function () {
    return window.CRM.APIRequest({
      path: "groups/",
      method: "GET",
    });
  },
  getRoles: function (GroupID) {
    return window.CRM.APIRequest({
      path: "groups/" + GroupID + "/roles",
      method: "GET",
    });
  },
  selectTypes: {
    Group: 1,
    Role: 2,
  },
  promptSelection: function (selectOptions, selectionCallback) {
    var options = {
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
    let initFunction = function () {};

    if (selectOptions.Type & window.CRM.groups.selectTypes.Group) {
      options.title = i18next.t("Select Group");
      options.message +=
        '<span style="color: red">' +
        i18next.t("Please select target group for members") +
        ':</span>\
                  <select name="targetGroupSelection" id="targetGroupSelection" class="form-control"></select>';
      options.buttons.confirm.callback = function () {
        selectionCallback({
          GroupID: window.jQuery("#targetGroupSelection option:selected").val(),
        });
      };
    }
    if (selectOptions.Type & window.CRM.groups.selectTypes.Role) {
      options.title = i18next.t("Select Role");
      options.message +=
        '<span style="color: red">' +
        i18next.t("Please select target Role for members") +
        ':</span>\
                  <select name="targetRoleSelection" id="targetRoleSelection" class="form-control"></select>';
      options.buttons.confirm.callback = function () {
        selectionCallback({
          RoleID: window.jQuery("#targetRoleSelection option:selected").val(),
        });
      };
    }

    if (selectOptions.Type === window.CRM.groups.selectTypes.Role) {
      if (!selectOptions.GroupID) {
        throw i18next.t("GroupID required for role selection prompt");
      }
      initFunction = function () {
        // Remove tabindex from bootbox so TomSelect dropdown can receive focus
        window.jQuery(".bootbox").removeAttr("tabindex");
        window.CRM.groups.getRoles(selectOptions.GroupID).done(function (rdata) {
          let roleEl = document.getElementById("targetRoleSelection");
          if (roleEl && roleEl.tomselect) roleEl.tomselect.destroy();
          new TomSelect(roleEl, {
            valueField: "id",
            labelField: "text",
            searchField: "text",
            options: rdata.map(function (item) {
              return {
                // i18next-disable-next-line
                text: i18next.t(item.OptionName),
                id: String(item.OptionId),
              };
            }),
            dropdownParent: document.querySelector(".bootbox"),
          });
        });
      };
    }
    if (selectOptions.Type === (window.CRM.groups.selectTypes.Group | window.CRM.groups.selectTypes.Role)) {
      options.title = i18next.t("Select Group and Role");
      options.buttons.confirm.callback = function () {
        selection = {
          RoleID: window.jQuery("#targetRoleSelection option:selected").val(),
          GroupID: window.jQuery("#targetGroupSelection option:selected").val(),
        };
        selectionCallback(selection);
      };
    }
    options.message += "</div>";
    bootbox.dialog(options).init(initFunction).show();

    window.CRM.groups.get().done(function (rdata) {
      var groupsList = rdata.map(function (item) {
        return {
          text: item.Name,
          id: String(item.Id),
        };
      });
      window.jQuery("#targetGroupSelection").parents(".bootbox").removeAttr("tabindex");
      var groupEl = document.getElementById("targetGroupSelection");
      if (groupEl && groupEl.tomselect) groupEl.tomselect.destroy();
      var groupTS = new TomSelect(groupEl, {
        valueField: "id",
        labelField: "text",
        searchField: "text",
        options: groupsList,
        dropdownParent: document.querySelector(".bootbox"),
        onChange: function (value) {
          if (!value) return;
          var roleEl = document.getElementById("targetRoleSelection");
          if (roleEl && roleEl.tomselect) roleEl.tomselect.destroy();
          // Clear existing options
          while (roleEl && roleEl.options.length) roleEl.remove(0);
          window.CRM.groups.getRoles(value).done(function (rdata) {
            var rolesList = rdata.map(function (item) {
              return {
                // i18next-disable-next-line
                text: i18next.t(item.OptionName),
                id: String(item.OptionId),
              };
            });
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
  addPerson: function (GroupID, PersonID, RoleID) {
    params = {
      method: "POST", // define the type of HTTP verb we want to use (POST for our form)
      path: "groups/" + GroupID + "/addperson/" + PersonID,
    };
    if (RoleID) {
      params.data = JSON.stringify({
        RoleID: RoleID,
      });
    }
    return window.CRM.APIRequest(params);
  },
  removePerson: function (GroupID, PersonID) {
    return window.CRM.APIRequest({
      method: "DELETE", // define the type of HTTP verb we want to use (POST for our form)
      path: "groups/" + GroupID + "/removeperson/" + PersonID,
    });
  },
  addGroup: function (callbackM) {
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
      callback: function (result) {
        if (result) {
          var newGroup = { groupName: result };

          if (!window.jQuery) {
            return;
          }

          window.jQuery
            .ajax({
              method: "POST",
              url: window.CRM.root + "/api/groups/", //call the groups api handler located at window.CRM.root
              data: JSON.stringify(newGroup), // stringify the object we created earlier, and add it to the data payload
              contentType: "application/json; charset=utf-8",
              dataType: "json",
            })
            .done(function (data) {
              //yippie, we got something good back from the server
              window.CRM.cartManager.refreshCartCount();
              if (callbackM) {
                callbackM(data);
              }
            });
        }
      },
    });
  },
};

window.CRM.system = {
  runTimerJobs: function () {
    window.CRM.APIRequest({
      method: "POST",
      path: "background/timerjobs",
      suppressErrorDialog: true,
    });
  },
  handlejQAJAXError: function (jqXHR, textStatus, errorThrown, suppressErrorDialog) {
    if (jqXHR.status === 401) {
      window.location = window.CRM.root + "/session/begin?location=" + window.location.pathname;
    }
    try {
      var CRMResponse = JSON.parse(jqXHR.responseText);
    } catch (err) {
      var errortext = textStatus + " " + errorThrown;
    }

    if (!(textStatus === "abort" || suppressErrorDialog)) {
      if (CRMResponse) {
        window.CRM.DisplayErrorMessage(jqXHR.url, CRMResponse);
      } else {
        window.CRM.DisplayErrorMessage(jqXHR.url, {
          message: errortext,
        });
      }
    }
  },
};

window.CRM.dashboard = {
  /**
   * Load event counters once on page load (birthdays, anniversaries, events today)
   */
  loadEventCounters: function () {
    window.CRM.APIRequest({
      method: "GET",
      path: "calendar/events-counters",
      suppressErrorDialog: true,
    }).done(function (data) {
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
window.CRM.renderPersonActionMenu = function (personId, personName, options) {
  options = options || {};
  var inCart = options.inCart || false;
  var familyId = options.familyId || null;
  var root = window.CRM.root;
  var escapedName = window.CRM.escapeHtml(personName || "");
  var familyItem = familyId
    ? '<a class="dropdown-item" href="' +
      root +
      "/v2/family/" +
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
window.CRM.renderFamilyActionMenu = function (familyId, _familyName, options) {
  options = options || {};
  var inCart = options.inCart || false;
  var root = window.CRM.root;
  return (
    '<div class="dropdown">' +
    '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">' +
    '<i class="ti ti-dots-vertical"></i>' +
    "</button>" +
    '<div class="dropdown-menu dropdown-menu-end">' +
    '<a class="dropdown-item" href="' +
    root +
    "/v2/family/" +
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

// Global delegated handler for .delete-person buttons (rendered in DataTables or PHP templates).
// Set up after locales are ready so i18next.t() is available in the confirmation dialog.
(function setupPersonDeleteHandler() {
  function register() {
    if (!window.jQuery) return;
    window.jQuery(document).on("click", ".delete-person", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var $btn = window.jQuery(this);
      var personId = $btn.data("person_id");
      var personName = $btn.data("person_name");
      bootbox.confirm({
        title: i18next.t("Delete this person?"),
        message:
          i18next.t("Do you want to delete this person?  This cannot be undone.") +
          " <b>" +
          window.CRM.escapeHtml(String(personName || "")) +
          "</b>",
        buttons: {
          cancel: { label: '<i class="ti ti-x"></i> ' + i18next.t("Cancel") },
          confirm: { label: '<i class="ti ti-trash"></i> ' + i18next.t("Delete"), className: "btn-danger" },
        },
        callback: function (result) {
          if (result) {
            window.CRM.APIRequest({ method: "DELETE", path: "person/" + personId }).done(function () {
              location.reload();
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
window.CRM.copyToClipboard = function (text, successMsg) {
  var msg = successMsg || i18next.t("Copied to clipboard");
  if (navigator.clipboard) {
    return navigator.clipboard
      .writeText(text)
      .then(function () {
        window.CRM.notify(msg, { type: "success", delay: 3000 });
      })
      .catch(function () {
        prompt(i18next.t("Press CTRL + C to copy"), text);
      });
  }
  prompt(i18next.t("Press CTRL + C to copy"), text);
  return Promise.resolve();
};

function LimitTextSize(theTextArea, size) {
  if (theTextArea.value.length > size) {
    theTextArea.value = theTextArea.value.substr(0, size);
  }
}

function popUp(URL) {
  window.open(
    URL,
    "popup-window",
    "toolbar=0,scrollbars=yes,location=0,statusbar=0,menubar=0,resizable=yes,width=600,height=400,left=100,top=50,noopener,noreferrer",
  );
}
