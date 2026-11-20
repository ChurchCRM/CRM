/**
 * ChurchCRM Event Checkin JavaScript
 * Person check-in with dual search: person (child) + supervisor (adult)
 *
 * Uses TomSelect AJAX for person search.
 */

import { confirmAction } from "./common/confirm-action";
import { attachToModal, initAllPersonSelects } from "./common/person-select";
import { escapeHtml } from "./utils/escape-html";

// A failed request rejects with the API's `message` as `serverMessage`, so the
// toast can say why instead of a generic "Please try again".
function jsonUnlessFailed(res) {
  if (res.ok) return res.json();
  return res
    .json()
    .catch(() => ({}))
    .then((body) => {
      throw Object.assign(new Error(`HTTP ${res.status}`), { serverMessage: body.message });
    });
}

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
  // Shared person-search TomSelect (#9819). The valueField/labelField/searchField
  // triple, the 2-character-guarded load callback and the placeholder read from
  // data-placeholder all live in webpack/common/person-select.ts now; only the
  // check-in specific mapping, rendering and change handler stay here.
  initAllPersonSelects({
    mapResult: (person) => ({
      objid: person.objid,
      text: person.text,
      uri: person.uri,
    }),
    render: {
      option: (data, escapeHtmlTs) => `<div>${escapeHtmlTs(data.text)}</div>`,
      item: (data, escapeHtmlTs) => `<div>${escapeHtmlTs(data.text)}</div>`,
    },
    onChange: function (value, el) {
      // Dispatch a custom event so bindPersonSearchEvents can react
      $(el).trigger("tomselect:change", [value, this]);
    },
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
    const personViewUrl = `${window.CRM.root}/people/view/${person.objid}`;

    // Compact inline display with name and check icon
    const html =
      '<div class="d-inline-flex align-items-center p-2 border border-success rounded bg-success-subtle">' +
      '<i class="fa-solid fa-circle-check text-success me-2"></i>' +
      '<a href="' +
      personViewUrl +
      '" class="text-body fw-bold" target="_blank" rel="noopener noreferrer">' +
      escapeHtml(person.text) +
      "</a>" +
      "</div>";

    element.html(html).show();
  } else {
    element.html("").hide();
  }
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
      .then(jsonUnlessFailed)
      .then(() => {
        loadRoster(eventId);
      })
      .catch((err) => {
        window.CRM.notify(err.serverMessage || i18next.t("Check-in failed. Please try again."), {
          type: "danger",
          delay: 5000,
        });
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
      .then(jsonUnlessFailed)
      .then(() => {
        loadRoster(eventId);
      })
      .catch((err) => {
        window.CRM.notify(err.serverMessage || i18next.t("Check-out failed. Please try again."), {
          type: "danger",
          delay: 5000,
        });
      })
      .finally(() => {
        $btn.prop("disabled", false);
      });
  });
});

/**
 * Roster state. Rows update in place so the list never reflows while tapping
 * through it; only a full load (page open, bulk actions) rebuilds the left list.
 */
let rosterMembers = [];
let attendanceTableTimer = null;

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

      const groupNames = data.groups.map((g) => g.name);
      $("#rosterGroupName").text(groupNames.length > 0 ? `— ${groupNames.join(", ")}` : "");

      rosterMembers = data.members;
      renderRoster(eventId);

      $("#rosterLoading").addClass("d-none");
      $("#rosterGrid").removeClass("d-none");
    })
    .catch(() => {
      $("#rosterCheckin").addClass("d-none");
    });
}

function renderRoster(eventId) {
  const $everyone = $("#notCheckedInList").empty();
  rosterMembers.forEach((member) => {
    $everyone.append(buildMemberCard(member, eventId));
  });
  renderCheckedInColumn(eventId);
  updateRosterCounts();
  refreshAvatars();
}

function renderCheckedInColumn(eventId) {
  const $checkedIn = $("#checkedInList").empty();
  rosterMembers
    .filter((member) => member.status === "checked_in")
    .forEach((member) => {
      $checkedIn.append(buildCheckedInCard(member, eventId));
    });
}

function updateRosterCounts() {
  const total = rosterMembers.length;
  const checkedIn = rosterMembers.filter((m) => m.status === "checked_in").length;
  const waiting = total - checkedIn;

  $("#rosterStats").text(`${checkedIn} / ${total} ${i18next.t("checked in")}`);
  $("#notCheckedInCount").text(waiting);
  $("#checkedInCount").text(checkedIn);
  $("#notCheckedInEmpty").toggleClass("d-none", waiting > 0);
  $("#checkedInEmpty").toggleClass("d-none", checkedIn > 0);
}

// avatar-loader's IntersectionObserver was wired up at DOMContentLoaded, so
// freshly appended <img data-image-entity-type> elements need re-observing.
// init() is idempotent and keeps its info cache, so it is cheap to repeat.
function refreshAvatars() {
  window.CRM?.avatarLoader?.init();
}

/**
 * Re-sync the "People Checked In" table below the roster from the server-rendered
 * page, so its rows and action menus stay correct without a full reload.
 */
function scheduleAttendanceTableRefresh() {
  if (!$.fn.dataTable?.isDataTable("#checkedinTable")) return;
  clearTimeout(attendanceTableTimer);
  attendanceTableTimer = setTimeout(() => {
    fetch(window.location.href, { credentials: "same-origin" })
      .then((res) => (res.ok ? res.text() : Promise.reject(new Error(`HTTP ${res.status}`))))
      .then((html) => {
        const doc = new DOMParser().parseFromString(html, "text/html");
        const rows = Array.from(doc.querySelectorAll("#checkedinTable tbody tr")).map((row) =>
          document.importNode(row, true),
        );
        const table = $("#checkedinTable").DataTable();
        table.clear();
        table.rows.add(rows);
        table.draw(false);
        refreshAvatars();
      })
      .catch(() => {});
  }, 400);
}

function memberPhotoHtml(member) {
  // Standard avatar-loader contract (data-image-entity-type/-id): the shared
  // loader handles fallbacks (Gravatar, identicons, default placeholder).
  return (
    '<img data-image-entity-type="person" data-image-entity-id="' +
    member.personId +
    '" class="avatar avatar-sm rounded-circle me-2" alt="" />'
  );
}

function memberNameHtml(member) {
  // bg-blue-lt with no companion text-blue fails WCAG AA contrast in Tabler.
  const roleBadge = member.role
    ? `<span class="badge bg-blue-lt text-blue ms-2">${escapeHtml(member.role)}</span>`
    : "";
  const personUrl = `${window.CRM.root}/people/view/${member.personId}`;
  return (
    '<a class="fw-medium text-reset text-decoration-none" href="' +
    personUrl +
    '">' +
    escapeHtml(`${member.firstName} ${member.lastName}`) +
    "</a>" +
    roleBadge
  );
}

function actionButtonHtml(member, eventId, action, btnClass, icon, label) {
  return (
    '<button type="button" class="btn btn-sm ' +
    btnClass +
    ' roster-action-btn"' +
    (member.pending ? " disabled" : "") +
    ' data-action="' +
    action +
    '" data-person-id="' +
    member.personId +
    '" data-event-id="' +
    eventId +
    '">' +
    '<i class="fa-solid ' +
    icon +
    ' me-1"></i>' +
    label +
    "</button>"
  );
}

/**
 * Build a row for the left list. Every member keeps their row for the whole
 * session; a checked-in member is highlighted and offers Undo and Check Out.
 * @param {Object} member - Member data from the roster API
 * @param {number} eventId
 * @returns {string} HTML string
 */
function buildMemberCard(member, eventId) {
  const isCheckedIn = member.status === "checked_in";
  const isCheckedOut = member.status === "checked_out";

  let timeInfo = "";
  if (isCheckedIn && member.checkinTime) {
    timeInfo = `<small class="text-secondary ms-2">${escapeHtml(member.checkinTime)}</small>`;
  }
  const checkedOutBadge = isCheckedOut
    ? `<span class="badge bg-secondary-lt text-secondary ms-2">${i18next.t("Checked out")}</span>`
    : "";

  let buttons;
  if (isCheckedIn) {
    buttons =
      '<div class="btn-list flex-nowrap">' +
      actionButtonHtml(member, eventId, "undo", "btn-outline-secondary", "fa-rotate-left", i18next.t("Undo")) +
      actionButtonHtml(
        member,
        eventId,
        "checkout",
        "btn-outline-secondary",
        "fa-right-from-bracket",
        i18next.t("Check Out"),
      ) +
      "</div>";
  } else {
    buttons = actionButtonHtml(member, eventId, "checkin", "btn-success", "fa-check", i18next.t("Check In"));
  }

  const stateClass = isCheckedIn ? " border-success bg-success-subtle" : "";
  const checkMark = isCheckedIn ? '<i class="fa-solid fa-circle-check text-success me-2"></i>' : "";

  return (
    '<div class="d-flex align-items-center justify-content-between p-2 border rounded roster-member' +
    stateClass +
    '" data-person-id="' +
    member.personId +
    '">' +
    '<div class="d-flex align-items-center">' +
    checkMark +
    memberPhotoHtml(member) +
    memberNameHtml(member) +
    timeInfo +
    checkedOutBadge +
    "</div>" +
    buttons +
    "</div>"
  );
}

function buildCheckedInCard(member, eventId) {
  const timeInfo = member.checkinTime
    ? `<small class="text-secondary ms-2">${escapeHtml(member.checkinTime)}</small>`
    : "";
  return (
    '<div class="d-flex align-items-center justify-content-between p-2 border rounded checked-in-member" data-person-id="' +
    member.personId +
    '">' +
    '<div class="d-flex align-items-center">' +
    memberPhotoHtml(member) +
    memberNameHtml(member) +
    timeInfo +
    "</div>" +
    actionButtonHtml(
      member,
      eventId,
      "checkout",
      "btn-outline-secondary",
      "fa-right-from-bracket",
      i18next.t("Check Out"),
    ) +
    "</div>"
  );
}

function rerenderMember(member, eventId) {
  $(`#notCheckedInList .roster-member[data-person-id="${member.personId}"]`).replaceWith(
    buildMemberCard(member, eventId),
  );
  renderCheckedInColumn(eventId);
  updateRosterCounts();
  refreshAvatars();
}

function rosterRequest(action, eventId, personId) {
  if (action === "undo") {
    return fetch(`${window.CRM.root}/api/events/${eventId}/attendance/${personId}`, { method: "DELETE" });
  }
  return fetch(`${window.CRM.root}/api/events/${eventId}/${action}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ personId: personId }),
  });
}

// Delegate click handler for roster action buttons. The row changes at once
// (optimistic) and is restored if the request fails.
$(document).on("click", ".roster-action-btn", function () {
  const action = $(this).data("action");
  const personId = Number($(this).data("person-id"));
  const eventId = $(this).data("event-id");
  const member = rosterMembers.find((m) => m.personId === personId);
  if (!member || member.pending) return;

  const previous = { status: member.status, checkinTime: member.checkinTime };
  member.pending = true;
  if (action === "checkin") {
    member.status = "checked_in";
    member.checkinTime = null;
  } else if (action === "checkout") {
    member.status = "checked_out";
  } else {
    member.status = "not_checked_in";
    member.checkinTime = null;
  }
  rerenderMember(member, eventId);

  rosterRequest(action, eventId, personId)
    .then(jsonUnlessFailed)
    .then((data) => {
      if (action === "checkin" && data.checkinTime) {
        member.checkinTime = data.checkinTime;
      }
      member.pending = false;
      rerenderMember(member, eventId);
      scheduleAttendanceTableRefresh();
    })
    .catch((err) => {
      member.status = previous.status;
      member.checkinTime = previous.checkinTime;
      member.pending = false;
      rerenderMember(member, eventId);
      const fallbacks = {
        checkin: i18next.t("Check-in failed. Please try again."),
        checkout: i18next.t("Check-out failed. Please try again."),
        undo: i18next.t("Undo failed. Please try again."),
      };
      window.CRM.notify(err.serverMessage || fallbacks[action], { type: "danger", delay: 5000 });
    });
});

// =============================================================================
// API-Driven Walk-in Check-in, Check-out, Delete
// =============================================================================

$(() => {
  // These selectors live on the "select an event" landing page (when no
  // eventId is set), so they must be bound BEFORE the eventId guard below.

  // Event selector navigation. Coerce the dropdown value to a positive
  // integer before interpolating into the URL — even though the option
  // values come from server-rendered HTML we control, parseInt + bounds
  // check defends against any future change and silences the
  // js/xss-through-dom CodeQL alert.
  $("#EventSelector").on("change", function () {
    const id = parseInt($(this).val(), 10);
    if (Number.isInteger(id) && id > 0) {
      window.location.href = `${window.CRM.root}/event/checkin/${id}`;
    }
  });

  // Event type filter navigation — same coercion as above.
  $("#typeFilter").on("change", function () {
    const typeId = parseInt(this.value, 10);
    if (Number.isInteger(typeId) && typeId >= 0) {
      window.location.href = `${window.CRM.root}/event/checkin?typeId=${typeId}`;
    }
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

  // "Assign to me" button for check-in supervisor
  $("#assignMeCheckin").on("click", () => {
    const el = document.querySelector("#adult");
    if (el?.tomselect) {
      const userId = window.CRM.userId;
      const userName = window.CRM.userName;
      if (userId && userName) {
        el.tomselect.addOption({ objid: userId, text: userName });
        el.tomselect.setValue(userId);
      }
    }
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
    const checkinId = $(this).data("checkin-id") ? parseInt($(this).data("checkin-id"), 10) : null;
    const checkinName = $(this).data("checkin-name") || "";
    if (!personId) return;
    openCheckoutByDialog(personId, personName, checkinId, checkinName);
  });

  function openCheckoutByDialog(personId, personName, checkinId, checkinName) {
    const safeName = window.CRM.escapeHtml(String(personName));

    // Use a fixed modal ID so concurrent calls can find and properly dispose
    // any already-open instance before creating a new one. A Date.now()-based
    // ID is always unique, making the getElementById dedup guard below
    // unreachable dead code.
    const modalId = "crm-checkout-by-modal";

    const bodyHtml =
      '<p class="mb-2">' +
      i18next.t("Optional — record who is checking this person out (e.g. a parent picking up a child).") +
      "</p>" +
      '<div class="input-group">' +
      '<select class="form-select" id="checkoutBySelect" placeholder="' +
      i18next.t("Search for supervisor...") +
      '"></select>' +
      '<button type="button" class="btn btn-outline-secondary" id="assignMeCheckout" title="' +
      i18next.t("Assign to me") +
      '">' +
      '<i class="fa-solid fa-user-check"></i>' +
      "</button>" +
      "</div>" +
      '<small class="text-muted mt-2 d-block">' +
      i18next.t("Leave blank to check out without recording the supervisor.") +
      "</small>";

    const existing = document.getElementById(modalId);
    if (existing) {
      // Dispose the BS5 Modal instance before removing the element to avoid
      // event-listener and backdrop leaks.
      window.bootstrap.Modal.getInstance(existing)?.dispose();
      existing.remove();
    }

    const wrapper = document.createElement("div");
    wrapper.id = modalId;
    wrapper.className = "modal fade";
    wrapper.setAttribute("tabindex", "-1");
    wrapper.setAttribute("aria-modal", "true");
    wrapper.setAttribute("role", "dialog");
    wrapper.setAttribute("aria-labelledby", `${modalId}-title`);
    wrapper.innerHTML =
      '<div class="modal-dialog modal-dialog-centered">' +
      '<div class="modal-content">' +
      '<div class="modal-header">' +
      `<h5 class="modal-title" id="${modalId}-title">` +
      i18next.t("Check out") +
      ": " +
      safeName +
      "</h5>" +
      '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
      "</div>" +
      '<div class="modal-body">' +
      bodyHtml +
      "</div>" +
      '<div class="modal-footer">' +
      '<button type="button" class="btn btn-link" id="checkoutCancelBtn" data-bs-dismiss="modal">' +
      '<i class="fa-solid fa-xmark"></i> ' +
      i18next.t("Cancel") +
      "</button>" +
      '<button type="button" class="btn btn-outline-warning" id="checkoutSkipBtn">' +
      '<i class="fa-solid fa-check"></i> ' +
      i18next.t("Skip & Check Out") +
      "</button>" +
      '<button type="button" class="btn btn-primary" id="checkoutConfirmBtn">' +
      '<i class="fa-solid fa-user-check"></i> ' +
      i18next.t("Confirm Check Out") +
      "</button>" +
      "</div></div></div>";

    document.body.appendChild(wrapper);
    const bsModal = new window.bootstrap.Modal(wrapper, { backdrop: "static" });

    // Shared person-search TomSelect (#9819). attachToModal owns the
    // shown.bs.modal → init / hidden.bs.modal → destroy() pair: TomSelect needs
    // the modal's CSS transition to have finished before it can measure the
    // control. Registered BEFORE the wrapper's own hidden handler below so the
    // picker is torn down before the modal element leaves the DOM.
    attachToModal(wrapper, "#checkoutBySelect", {
      placeholder: i18next.t("Search for supervisor..."),
      mapResult: (p) => ({ objid: p.objid, text: p.text }),
      onInit: (tomSelectInstance) => {
        // Pre-populate with the person who checked them in (if available)
        if (checkinId && checkinName) {
          tomSelectInstance.addOption({ objid: checkinId, text: checkinName });
          tomSelectInstance.setValue(checkinId);
        }

        // "Assign to me" button: set the current logged-in user as supervisor
        const assignMeBtn = wrapper.querySelector("#assignMeCheckout");
        if (assignMeBtn) {
          assignMeBtn.addEventListener("click", () => {
            const userId = window.CRM.userId;
            const userName = window.CRM.userName;
            if (userId && userName) {
              tomSelectInstance.addOption({ objid: userId, text: userName });
              tomSelectInstance.setValue(userId);
            }
          });
        }
      },
    });

    // Remove the modal element once hidden
    wrapper.addEventListener(
      "hidden.bs.modal",
      () => {
        try {
          bsModal.dispose();
        } catch (_e) {
          // ignore
        }
        if (wrapper.parentNode) wrapper.remove();
      },
      { once: true },
    );

    // Skip: check out without recording a supervisor
    const skipBtn = wrapper.querySelector("#checkoutSkipBtn");
    skipBtn.addEventListener("click", () => {
      bsModal.hide();
      performCheckout(personId, null);
    });

    // Confirm: check out with the selected supervisor (read via TomSelect API)
    const confirmBtn = wrapper.querySelector("#checkoutConfirmBtn");
    confirmBtn.addEventListener("click", () => {
      const el = wrapper.querySelector("#checkoutBySelect");
      const val = el?.tomselect ? el.tomselect.getValue() : null;
      const supervisorId = val ? parseInt(val, 10) : null;
      bsModal.hide();
      performCheckout(personId, supervisorId);
    });

    bsModal.show();
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
            `<span class="dropdown-item disabled text-success"><i class="fa-solid fa-check me-2"></i>${i18next.t("Checked Out")}</span>`,
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

    confirmAction(
      i18next.t("Delete attendance record?"),
      `${i18next.t("Delete check-in record for")} <strong>${window.CRM.escapeHtml(String(personName || ""))}</strong>?`,
      () => {
        deleteAttendanceRecord(personId);
      },
      {
        confirmLabel: `<i class="fa-solid fa-trash"></i> ${i18next.t("Delete")}`,
        cancelLabel: `<i class="fa-solid fa-xmark"></i> ${i18next.t("Cancel")}`,
      },
    );
  });

  function deleteAttendanceRecord(personId) {
    fetch(`${window.CRM.root}/api/events/${eventId}/attendance/${personId}`, {
      method: "DELETE",
    })
      .then(jsonUnlessFailed)
      .then(() => {
        $(`tr[data-person-id="${personId}"]`).fadeOut(300, function () {
          $(this).remove();
        });
        window.CRM.notify(i18next.t("Attendance record deleted."), { type: "success", delay: 3000 });

        if ($("#rosterCheckin").length > 0) {
          loadRoster(eventId);
        }
      })
      .catch((err) => {
        window.CRM.notify(err.serverMessage || i18next.t("Failed to delete. Please try again."), {
          type: "danger",
          delay: 5000,
        });
      });
  }
});
