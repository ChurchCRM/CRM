/**
 * ChurchCRM Event Checkin JavaScript
 * Person check-in with dual search: person (child) + supervisor (adult)
 *
 * Uses Select2 AJAX following official documentation patterns:
 * https://select2.org/data-sources/ajax
 */

$(function () {
    // Initialize DataTable for already checked-in people
    if ($("#checkedinTable").length > 0) {
        $("#checkedinTable").DataTable(window.CRM.plugin.dataTable);
    }

    // Initialize all person search Select2 elements
    initializePersonSearchFields();
});

/**
 * Initialize Select2 on all person search fields
 * Called on document ready and can be called again if new fields are added dynamically
 */
function initializePersonSearchFields() {
    // Select2 AJAX configuration for person search
    // Following Select2 official documentation for AJAX data sources
    var personSearchConfig = {
        minimumInputLength: 2,
        language: window.CRM.shortLocale,
        allowClear: true,
        placeholder: "", // Will be overridden by data-placeholder attribute
        width: "100%",
        ajax: {
            // Use a URL function that returns the dynamic search URL
            // The API expects the search term in the URL path: /api/persons/search/{query}
            url: function (params) {
                return window.CRM.root + "/api/persons/search/" + encodeURIComponent(params.term);
            },
            dataType: "json",
            delay: 250,
            // The API returns results directly, no need to send additional query params
            data: function (_params) {
                return {}; // Empty object - search term is in URL
            },
            // Process the API response to match Select2's expected format
            // API returns: [{id: 1, objid: 123, text: "John Doe", uri: "..."}, ...]
            processResults: function (data) {
                return {
                    results: data.map(function (person) {
                        return {
                            id: person.objid, // Select2 uses 'id' for the value
                            text: person.text, // Select2 uses 'text' for display
                            objid: person.objid, // Keep original for hidden field
                            uri: person.uri, // Keep for linking
                        };
                    }),
                };
            },
            cache: true,
        },
        // Custom template for displaying results with better formatting
        templateResult: function (person) {
            if (person.loading) {
                return person.text;
            }
            return $("<span>").text(person.text);
        },
        // Template for selected item
        templateSelection: function (person) {
            return person.text || person.placeholder;
        },
    };

    // Initialize Select2 on all person search fields that haven't been initialized yet
    $(".person-search").each(function () {
        var $element = $(this);

        // Skip if already initialized
        if ($element.hasClass("select2-hidden-accessible")) {
            return;
        }

        var config = $.extend({}, personSearchConfig);

        // Use data-placeholder attribute if available
        if ($element.data("placeholder")) {
            config.placeholder = $element.data("placeholder");
        }

        $element.select2(config);
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
        .off("select2:select")
        .on("select2:select", function (e) {
            var selectedData = e.params.data;
            $("#child-id").val(selectedData.objid);
            displayPersonDetails($("#childDetails"), selectedData);
        });

    $("#child")
        .off("select2:clear")
        .on("select2:clear", function () {
            $("#child-id").val("");
            displayPersonDetails($("#childDetails"), null);
        });

    // Handle person selection for adult field
    $("#adult")
        .off("select2:select")
        .on("select2:select", function (e) {
            var selectedData = e.params.data;
            $("#adult-id").val(selectedData.objid);
            displayPersonDetails($("#adultDetails"), selectedData);
        });

    $("#adult")
        .off("select2:clear")
        .on("select2:clear", function () {
            $("#adult-id").val("");
            displayPersonDetails($("#adultDetails"), null);
        });

    // Handle person selection for adultout field (checkout form)
    // Only update hidden field, no details display needed for checkout
    $("#adultout")
        .off("select2:select")
        .on("select2:select", function (e) {
            var selectedData = e.params.data;
            $("#adultout-id").val(selectedData.objid);
        });

    $("#adultout")
        .off("select2:clear")
        .on("select2:clear", function () {
            $("#adultout-id").val("");
        });

    // When form is reset, clear all selections and details
    $("#AddAttendees")
        .off("reset")
        .on("reset", function () {
            // Use setTimeout to allow the form reset to complete first
            setTimeout(function () {
                $("#child").val(null).trigger("change");
                $("#adult").val(null).trigger("change");
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
        var photoUrl = window.CRM.root + "/api/person/" + person.objid + "/photo";
        var personViewUrl = "PersonView.php?PersonID=" + person.objid;

        // Compact inline display with photo and name
        var html =
            '<div class="d-inline-flex align-items-center p-2 border border-success rounded" style="background-color: #d4edda;">' +
            '<img src="' +
            photoUrl +
            '" class="rounded-circle mr-2" ' +
            'style="width: 36px; height: 36px; object-fit: cover;">' +
            '<i class="fa-solid fa-check-circle text-success mr-2"></i>' +
            '<a href="' +
            personViewUrl +
            '" class="text-dark font-weight-bold" target="_blank">' +
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
