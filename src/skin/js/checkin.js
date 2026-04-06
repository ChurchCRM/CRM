/**
 * ChurchCRM Event Checkin JavaScript
 * Person check-in with dual search: person (child) + supervisor (adult)
 *
 * Uses TomSelect AJAX for person search.
 */

$(function () {
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
    var el = this;

    // Skip if already initialized
    if (el.tomselect) {
      return;
    }

    var placeholder = $(el).data("placeholder") || "";

    new TomSelect(el, {
      valueField: "objid",
      labelField: "text",
      searchField: "text",
      placeholder: placeholder,
      load: function (query, callback) {
        if (query.length < 2) return callback();
        fetch(window.CRM.root + "/api/persons/search/" + encodeURIComponent(query))
          .then(function (response) {
            return response.json();
          })
          .then(function (data) {
            callback(
              data.map(function (person) {
                return {
                  objid: person.objid,
                  text: person.text,
                  uri: person.uri,
                };
              }),
            );
          })
          .catch(function () {
            callback();
          });
      },
      render: {
        option: function (data, escape) {
          return "<div>" + escape(data.text) + "</div>";
        },
        item: function (data, escape) {
          return "<div>" + escape(data.text) + "</div>";
        },
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
  // Handle person selection for child field - update hidden field and show details
  $("#child")
    .off("tomselect:change")
    .on("tomselect:change", function (e, value, tsInstance) {
      if (value) {
        var selectedData = tsInstance.options[value];
        $("#child-id").val(selectedData.objid);
        displayPersonDetails($("#childDetails"), selectedData);
      } else {
        $("#child-id").val("");
        displayPersonDetails($("#childDetails"), null);
      }
    });

  // Handle person selection for adult field
  $("#adult")
    .off("tomselect:change")
    .on("tomselect:change", function (e, value, tsInstance) {
      if (value) {
        var selectedData = tsInstance.options[value];
        $("#adult-id").val(selectedData.objid);
        displayPersonDetails($("#adultDetails"), selectedData);
      } else {
        $("#adult-id").val("");
        displayPersonDetails($("#adultDetails"), null);
      }
    });

  // Handle person selection for adultout field (checkout form)
  $("#adultout")
    .off("tomselect:change")
    .on("tomselect:change", function (e, value, tsInstance) {
      if (value) {
        var selectedData = tsInstance.options[value];
        $("#adultout-id").val(selectedData.objid);
      } else {
        $("#adultout-id").val("");
      }
    });

  // When form is reset, clear all selections and details
  $("#AddAttendees")
    .off("reset")
    .on("reset", function () {
      // Use setTimeout to allow the form reset to complete first
      setTimeout(function () {
        ["#child", "#adult"].forEach(function (sel) {
          var el = document.querySelector(sel);
          if (el && el.tomselect) {
            el.tomselect.clear(true);
            el.tomselect.clearOptions();
          }
        });
        $("#child-id").val("");
        $("#adult-id").val("");
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
  if (!element || !element.length) {
    return;
  }

  if (person && person.objid) {
    var personViewUrl = "PersonView.php?PersonID=" + person.objid;

    // Compact inline display with name and check icon
    var html =
      '<div class="d-inline-flex align-items-center p-2 border border-success rounded" style="background-color: #d4edda;">' +
      '<i class="fa-solid fa-circle-check text-success mr-2"></i>' +
      '<a href="' +
      personViewUrl +
      '" class="text-dark fw-bold" target="_blank">' +
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
  var div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// =============================================================================
// Roster-Based Check-in (for group-linked events)
// =============================================================================

$(function () {
  var $rosterContainer = $("#rosterCheckin");
  if ($rosterContainer.length === 0) return;

  var eventId = $rosterContainer.data("event-id");
  if (!eventId) return;

  loadRoster(eventId);

  // Batch check-in all
  $("#checkinAllBtn").on("click", function () {
    var $btn = $(this);
    $btn.prop("disabled", true);
    fetch(window.CRM.root + "/api/events/" + eventId + "/checkin-all", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function () {
        loadRoster(eventId);
      })
      .finally(function () {
        $btn.prop("disabled", false);
      });
  });

  // Batch check-out all
  $("#checkoutAllBtn").on("click", function () {
    var $btn = $(this);
    $btn.prop("disabled", true);
    fetch(window.CRM.root + "/api/events/" + eventId + "/checkout-all", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
    })
      .then(function (res) {
        return res.json();
      })
      .then(function () {
        loadRoster(eventId);
      })
      .finally(function () {
        $btn.prop("disabled", false);
      });
  });
});

/**
 * Load the event roster from the API and render the two-column UI
 * @param {number} eventId
 */
function loadRoster(eventId) {
  fetch(window.CRM.root + "/api/events/" + eventId + "/roster")
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      var $container = $("#rosterCheckin");

      // If no group members, hide roster and keep the walk-in form as primary
      if (!data.members || data.members.length === 0) {
        $container.addClass("d-none");
        return;
      }

      // Show roster, relabel walk-in card
      $container.removeClass("d-none");
      $("#walkinCardTitle").text(i18next.t("Add Walk-in / Visitor"));

      // Update group name
      var groupNames = data.groups.map(function (g) {
        return g.name;
      });
      $("#rosterGroupName").text(groupNames.length > 0 ? "— " + groupNames.join(", ") : "");

      // Update stats
      var stats = data.stats;
      $("#rosterStats").text(stats.checkedIn + " / " + stats.total + " " + i18next.t("checked in"));

      // Render member lists
      var $notCheckedIn = $("#notCheckedInList").empty();
      var $checkedIn = $("#checkedInList").empty();
      var notCheckedInCount = 0;
      var checkedInCount = 0;

      data.members.forEach(function (member) {
        var card = buildMemberCard(member, eventId);
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
    .catch(function (err) {
      console.error("Failed to load roster:", err);
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
  var isCheckedIn = member.status === "checked_in";
  var btnClass = isCheckedIn ? "btn-outline-secondary" : "btn-success";
  var btnIcon = isCheckedIn ? "ti-door-exit" : "ti-check";
  var btnText = isCheckedIn ? i18next.t("Check Out") : i18next.t("Check In");
  var action = isCheckedIn ? "checkout" : "checkin";

  var roleBadge = member.role ? '<span class="badge bg-blue-lt ms-2">' + escapeHtml(member.role) + "</span>" : "";

  var photoHtml = member.hasPhoto
    ? '<span class="avatar avatar-sm me-2" style="background-image: url(' +
      window.CRM.root +
      "/api/person/" +
      member.personId +
      '/photo)"></span>'
    : '<span class="avatar avatar-sm me-2 bg-primary-lt"><i class="ti ti-user"></i></span>';

  var timeInfo = "";
  if (isCheckedIn && member.checkinTime) {
    timeInfo = '<small class="text-secondary ms-2">' + escapeHtml(member.checkinTime) + "</small>";
  }

  var html =
    '<div class="d-flex align-items-center justify-content-between p-2 border rounded roster-member" data-person-id="' +
    member.personId +
    '">' +
    '<div class="d-flex align-items-center">' +
    photoHtml +
    '<span class="fw-medium">' +
    escapeHtml(member.firstName + " " + member.lastName) +
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
  var $btn = $(this);
  var action = $btn.data("action");
  var personId = $btn.data("person-id");
  var eventId = $btn.data("event-id");

  $btn.prop("disabled", true);

  fetch(window.CRM.root + "/api/events/" + eventId + "/" + action, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ personId: personId }),
  })
    .then(function (res) {
      return res.json();
    })
    .then(function () {
      // Reload roster to reflect changes
      loadRoster(eventId);
    })
    .catch(function (err) {
      console.error("Check-in/out failed:", err);
      $btn.prop("disabled", false);
    });
});
