/**
 * ChurchCRM Event Checkin JavaScript
 * Person check-in with dual search: person (child) + supervisor (adult)
 *
 * Uses TomSelect AJAX for person search.
 */

$(() => {
  // Initialize DataTable for already checked-in people
  if ($("#checkedinTable").length > 0) {
    $("#checkedinTable").DataTable(window.CRM.plugin.dataTable);
  }

  // Initialize all person search TomSelect elements
  initializePersonSearchFields();
});

/**
 * Initialize TomSelect on all person search fields
 * Called on document ready and can be called again if new fields are added dynamically
 */
function initializePersonSearchFields() {
  // Initialize TomSelect on all person search fields that haven't been initialized yet
  $(".person-search").each(function () {
    const el = this;

    // Skip if already initialized
    if (el.tomselect) {
      return;
    }

    const placeholder = $(el).data("placeholder") || "";

    new TomSelect(el, {
      valueField: "objid",
      labelField: "text",
      searchField: "text",
      placeholder: placeholder,
      load: (query, callback) => {
        if (query.length < 2) return callback();
        fetch(`${window.CRM.root}/api/persons/search/${encodeURIComponent(query)}`)
          .then((response) => response.json())
          .then((data) => {
            callback(
              data.map((person) => ({
                objid: person.objid,
                text: person.text,
                uri: person.uri,
              })),
            );
          })
          .catch(() => {
            callback();
          });
      },
      render: {
        option: (data, escapeHtmlTs) => `<div>${escapeHtmlTs(data.text)}</div>`,
        item: (data, escapeHtmlTs) => `<div>${escapeHtmlTs(data.text)}</div>`,
      },
      onChange: function (value) {
        // Dispatch a custom event so bindPersonSearchEvents can react
        $(el).trigger("tomselect:change", [value, this]);
      },
    });
  });

  // Bind event handlers (use .off first to prevent duplicate bindings)
  bindPersonSearchEvents();
}

/**
 * Bind event handlers for person search fields
 */
function bindPersonSearchEvents() {
  // TomSelect keeps the underlying <select>'s value attribute in sync with the
  // selected option key (which is the person id). Read it via $("#child").val()
  // — no separate hidden input needed.

  // Handle person selection for child field — show details
  $("#child")
    .off("tomselect:change")
    .on("tomselect:change", (_e, value, tsInstance) => {
      if (value) {
        displayPersonDetails($("#childDetails"), tsInstance.options[value]);
      } else {
        displayPersonDetails($("#childDetails"), null);
      }
    });

  // Handle person selection for adult field
  $("#adult")
    .off("tomselect:change")
    .on("tomselect:change", (_e, value, tsInstance) => {
      if (value) {
        displayPersonDetails($("#adultDetails"), tsInstance.options[value]);
      } else {
        displayPersonDetails($("#adultDetails"), null);
      }
    });

  // When form is reset, clear all selections and details
  $("#AddAttendees")
    .off("reset")
    .on("reset", () => {
      // Use setTimeout to allow the form reset to complete first
      setTimeout(() => {
        ["#child", "#adult"].forEach((sel) => {
          const el = document.querySelector(sel);
          if (el?.tomselect) {
            el.tomselect.clear(true);
            el.tomselect.clearOptions();
          }
        });
        displayPersonDetails($("#childDetails"), null);
        displayPersonDetails($("#adultDetails"), null);
      }, 0);
    });
}

/**
 * Display person details as a compact inline confirmation
 *
 * @param {jQuery} element - The container element to render into
 * @param {Object|null} person - Person data object or null to clear
 */
function displayPersonDetails(element, person) {
  if (!element?.length) {
    return;
  }

  if (person?.objid) {
    const personViewUrl = `PersonView.php?PersonID=${person.objid}`;

    // Compact inline display with name and check icon
    const html =
      '<div class="d-inline-flex align-items-center p-2 border border-success rounded" style="background-color: #d4edda;">' +
      '<i class="fa-solid fa-circle-check text-success me-2"></i>' +
      '<a href="' +
      personViewUrl +
      '" class="text-dark fw-bold" target="_blank" rel="noopener noreferrer">' +
      escapeHtml(person.text) +
      "</a>" +
      "</div>";

    element.html(html).show();
  } else {
    element.html("").hide();
  }
}

/**
 * Escape HTML entities to prevent XSS
 *
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// =============================================================================
// Roster-Based Check-in (for group-linked events)
// =============================================================================

$(() => {
  const $rosterContainer = $("#rosterCheckin");
  if ($rosterContainer.length === 0) return;

  const eventId = $rosterContainer.data("event-id");
  if (!eventId) return;

  loadRoster(eventId);

  // Batch check-in all
  $("#checkinAllBtn").on("click", function () {
    const $btn = $(this);
    $btn.prop("disabled", true);
    fetch(`${window.CRM.root}/api/events/${eventId}/checkin-all`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(() => {
        loadRoster(eventId);
      })
      .catch(() => {
        window.CRM.notify(i18next.t("Check-in failed. Please try again."), { type: "danger", delay: 5000 });
      })
      .finally(() => {
        $btn.prop("disabled", false);
      });
  });

  // Batch check-out all
  $("#checkoutAllBtn").on("click", function () {
    const $btn = $(this);
    $btn.prop("disabled", true);
    fetch(`${window.CRM.root}/api/events/${eventId}/checkout-all`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(() => {
        loadRoster(eventId);
      })
      .catch(() => {
        window.CRM.notify(i18next.t("Check-out failed. Please try again."), { type: "danger", delay: 5000 });
      })
      .finally(() => {
        $btn.prop("disabled", false);
      });
  });
});

/**
 * Load the event roster from the API and render the two-column UI
 * @param {number} eventId
 */
function loadRoster(eventId) {
  fetch(`${window.CRM.root}/api/events/${eventId}/roster`)
    .then((res) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then((data) => {
      const $container = $("#rosterCheckin");

      // If no group members, hide roster and keep the walk-in form as primary
      if (!data.members || data.members.length === 0) {
        $container.addClass("d-none");
        return;
      }

      // Show roster, relabel walk-in card
      $container.removeClass("d-none");
      $("#walkinCardTitle").text(i18next.t("Add Walk-in / Visitor"));

      // Update group name
      const groupNames = data.groups.map((g) => g.name);
      $("#rosterGroupName").text(groupNames.length > 0 ? `— ${groupNames.join(", ")}` : "");

      // Update stats
      const stats = data.stats;
      $("#rosterStats").text(`${stats.checkedIn} / ${stats.total} ${i18next.t("checked in")}`);

      // Render member lists
      const $notCheckedIn = $("#notCheckedInList").empty();
      const $checkedIn = $("#checkedInList").empty();
      let notCheckedInCount = 0;
      let checkedInCount = 0;

      data.members.forEach((member) => {
        const card = buildMemberCard(member, eventId);
        if (member.status === "checked_in") {
          $checkedIn.append(card);
          checkedInCount++;
        } else {
          $notCheckedIn.append(card);
          notCheckedInCount++;
        }
      });

      // Update counts
      $("#notCheckedInCount").text(notCheckedInCount);
      $("#checkedInCount").text(checkedInCount);

      // Toggle empty states
      $("#notCheckedInEmpty").toggleClass("d-none", notCheckedInCount > 0);
      $("#checkedInEmpty").toggleClass("d-none", checkedInCount > 0);

      // Show the grid, hide loading
      $("#rosterLoading").addClass("d-none");
      $("#rosterGrid").removeClass("d-none");
    })
    .catch(() => {
      $("#rosterCheckin").addClass("d-none");
    });
}

/**
 * Build a member card element for the roster
 * @param {Object} member - Member data from the roster API
 * @param {number} eventId
 * @returns {string} HTML string
 */
function buildMemberCard(member, eventId) {
  const isCheckedIn = member.status === "checked_in";
  const btnClass = isCheckedIn ? "btn-outline-secondary" : "btn-success";
  const btnIcon = isCheckedIn ? "ti-door-exit" : "ti-check";
  const btnText = isCheckedIn ? i18next.t("Check Out") : i18next.t("Check In");
  const action = isCheckedIn ? "checkout" : "checkin";

  const roleBadge = member.role ? `<span class="badge bg-blue-lt ms-2">${escapeHtml(member.role)}</span>` : "";

  const photoHtml = member.hasPhoto
    ? '<span class="avatar avatar-sm me-2" style="background-image: url(' +
      window.CRM.root +
      "/api/person/" +
      member.personId +
      '/photo)"></span>'
    : '<span class="avatar avatar-sm me-2 bg-primary-lt"><i class="ti ti-user"></i></span>';

  let timeInfo = "";
  if (isCheckedIn && member.checkinTime) {
    timeInfo = `<small class="text-secondary ms-2">${escapeHtml(member.checkinTime)}</small>`;
  }

  const html =
    '<div class="d-flex align-items-center justify-content-between p-2 border rounded roster-member" data-person-id="' +
    member.personId +
    '">' +
    '<div class="d-flex align-items-center">' +
    photoHtml +
    '<span class="fw-medium">' +
    escapeHtml(`${member.firstName} ${member.lastName}`) +
    "</span>" +
    roleBadge +
    timeInfo +
    "</div>" +
    '<button type="button" class="btn btn-sm ' +
    btnClass +
    ' roster-action-btn" ' +
    'data-action="' +
    action +
    '" data-person-id="' +
    member.personId +
    '" data-event-id="' +
    eventId +
    '">' +
    '<i class="ti ' +
    btnIcon +
    ' me-1"></i>' +
    btnText +
    "</button>" +
    "</div>";

  return html;
}

// Delegate click handler for roster action buttons
$(document).on("click", ".roster-action-btn", function () {
  const $btn = $(this);
  const action = $btn.data("action");
  const personId = $btn.data("person-id");
  const eventId = $btn.data("event-id");

  $btn.prop("disabled", true);

  fetch(`${window.CRM.root}/api/events/${eventId}/${action}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ personId: personId }),
  })
    .then((res) => {
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return res.json();
    })
    .then(() => {
      // Reload roster to reflect changes
      loadRoster(eventId);
    })
    .catch(() => {
      $btn.prop("disabled", false);
    });
});

// =============================================================================
// API-Driven Walk-in Check-in, Check-out, Delete
// =============================================================================

$(() => {
  // These selectors live on the "select an event" landing page (when no
  // eventId is set), so they must be bound BEFORE the eventId guard below.

  // Event selector navigation
  $("#EventSelector").on("change", function () {
    const id = $(this).val();
    if (id) {
      window.location.href = `${window.CRM.root}/event/checkin/${id}`;
    }
  });

  // Event type filter navigation
  $("#EventTypeFilter").on("change", function () {
    window.location.href = `${window.CRM.root}/event/checkin?EventTypeID=${this.value}`;
  });

  // Everything below requires an active event context
  const eventId = window.CRM.checkinEventId;
  if (!eventId) return;

  // Walk-in check-in button (API call, no form POST)
  $("#checkinBtn").on("click", () => {
    const personId = $("#child").val();
    const checkedInById = $("#adult").val() || null;

    if (!personId) {
      window.CRM.notify(i18next.t("Please select a person to check in."), { type: "warning", delay: 5000 });
      return;
    }

    const payload = { personId: parseInt(personId, 10) };
    if (checkedInById) {
      payload.checkedInById = parseInt(checkedInById, 10);
    }

    fetch(`${window.CRM.root}/api/events/${eventId}/checkin`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((res) => {
        if (!res.ok) return res.json().then((d) => Promise.reject(d));
        return res.json();
      })
      .then(() => {
        window.location.reload();
      })
      .catch((err) => {
        const msg = err?.message || i18next.t("Check-in failed. Please try again.");
        window.CRM.notify(msg, { type: "danger", delay: 5000 });
      });
  });

  // Clear button
  $("#clearBtn").on("click", () => {
    ["#child", "#adult"].forEach((sel) => {
      const el = document.querySelector(sel);
      if (el?.tomselect) {
        el.tomselect.clear(true);
        el.tomselect.clearOptions();
      }
    });
    $("#childDetails, #adultDetails").html("").hide();
  });

  // Inline check-out via dropdown action — opens a "checked out by" prompt
  // so the user can record who is checking the person out (e.g. parent
  // picking up a child) or skip and check out without a supervisor.
  $(document).on("click", ".checkout-btn", function () {
    const personId = parseInt($(this).data("person-id"), 10);
    const personName = $(this).data("person-name") || "";
    if (!personId) return;
    openCheckoutByDialog(personId, personName);
  });

  function openCheckoutByDialog(personId, personName) {
    const safeName = window.CRM.escapeHtml(String(personName));
    const dialog = bootbox.dialog({
      title: i18next.t("Check out") + ": " + safeName,
      message:
        '<p class="mb-2">' +
        i18next.t("Optional — record who is checking this person out (e.g. a parent picking up a child).") +
        "</p>" +
        '<select class="form-select" id="checkoutBySelect" placeholder="' +
        i18next.t("Search for supervisor...") +
        '"></select>' +
        '<small class="text-muted mt-2 d-block">' +
        i18next.t("Leave blank to check out without recording the supervisor.") +
        "</small>",
      buttons: {
        cancel: {
          label: '<i class="ti ti-x"></i> ' + i18next.t("Cancel"),
          className: "btn-link",
        },
        skip: {
          label: '<i class="ti ti-check"></i> ' + i18next.t("Skip & Check Out"),
          className: "btn-outline-warning",
          callback: function () {
            performCheckout(personId, null);
          },
        },
        confirm: {
          label: '<i class="ti ti-user-check"></i> ' + i18next.t("Confirm Check Out"),
          className: "btn-primary",
          callback: function () {
            const val = $("#checkoutBySelect").val();
            const supervisorId = val ? parseInt(val, 10) : null;
            performCheckout(personId, supervisorId);
          },
        },
      },
    });

    // Initialize TomSelect on the supervisor search field once the modal is shown
    dialog.on("shown.bs.modal", function () {
      const el = document.getElementById("checkoutBySelect");
      if (!el || el.tomselect) return;
      new TomSelect(el, {
        valueField: "objid",
        labelField: "text",
        searchField: "text",
        placeholder: i18next.t("Search for supervisor..."),
        load: (query, callback) => {
          if (query.length < 2) return callback();
          fetch(`${window.CRM.root}/api/persons/search/${encodeURIComponent(query)}`)
            .then((res) => res.json())
            .then((data) => callback(data.map((p) => ({ objid: p.objid, text: p.text }))))
            .catch(() => callback());
        },
      });
    });
  }

  function performCheckout(personId, checkedOutById) {
    const payload = { personId: personId };
    if (checkedOutById) {
      payload.checkedOutById = checkedOutById;
    }
    fetch(`${window.CRM.root}/api/events/${eventId}/checkout`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((res) => {
        if (!res.ok) return res.json().then((d) => Promise.reject(d));
        return res.json();
      })
      .then((data) => {
        // Update the row in-place
        const $row = $(`tr[data-person-id="${personId}"]`);
        $row.find(".checkout-date").text(data.checkoutTime || i18next.t("Just now"));
        $row
          .find(".checkout-btn")
          .replaceWith(
            `<span class="dropdown-item disabled text-success"><i class="ti ti-check me-2"></i>${i18next.t("Checked Out")}</span>`,
          );
        window.CRM.notify(i18next.t("Person checked out."), { type: "success", delay: 3000 });

        // Refresh roster if visible
        if ($("#rosterCheckin").length > 0) {
          loadRoster(eventId);
        }
      })
      .catch((err) => {
        const msg = err?.message || i18next.t("Check-out failed. Please try again.");
        window.CRM.notify(msg, { type: "danger", delay: 5000 });
      });
  }

  // Delete attendance via dropdown action (bootbox confirm first)
  $(document).on("click", ".delete-attendance-btn", function () {
    const personId = $(this).data("person-id");
    const personName = $(this).data("person-name");

    bootbox.confirm({
      title: i18next.t("Delete attendance record?"),
      message: `${i18next.t("Delete check-in record for")} <strong>${window.CRM.escapeHtml(String(personName || ""))}</strong>?`,
      buttons: {
        cancel: { label: '<i class="ti ti-x"></i> ' + i18next.t("Cancel") },
        confirm: { label: '<i class="ti ti-trash"></i> ' + i18next.t("Delete"), className: "btn-danger" },
      },
      callback: (confirmed) => {
        if (!confirmed) return;
        deleteAttendanceRecord(personId);
      },
    });
  });

  function deleteAttendanceRecord(personId) {
    fetch(`${window.CRM.root}/api/events/${eventId}/attendance/${personId}`, {
      method: "DELETE",
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(() => {
        $(`tr[data-person-id="${personId}"]`).fadeOut(300, function () {
          $(this).remove();
        });
        window.CRM.notify(i18next.t("Attendance record deleted."), { type: "success", delay: 3000 });

        if ($("#rosterCheckin").length > 0) {
          loadRoster(eventId);
        }
      })
      .catch(() => {
        window.CRM.notify(i18next.t("Failed to delete. Please try again."), { type: "danger", delay: 5000 });
      });
  }
});
