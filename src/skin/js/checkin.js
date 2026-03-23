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
      '<i class="fa-solid fa-check-circle text-success mr-2"></i>' +
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
