/* Shared Import Demo Data behavior for admin/system-maintenance and v2 dashboard
 * - Attaches to buttons with IDs: #importDemoData, #importDemoDataQuickBtn and #importDemoDataV2
 * - Shows a big confirmation overlay with reset instructions
 * - Displays full-screen spinner while importing to block all user interaction
 * - Uses optional status/result elements when present (#demoImportStatus, #demoImportResults, #demoImportResultsList)
 */
(function ($) {
  // Create and inject confirmation overlay HTML if not already present
  function ensureConfirmOverlay() {
    if ($("#demoImportConfirmOverlay").length === 0) {
      $("body").append(`
        <div id="demoImportConfirmOverlay" class="demo-import-overlay confirm">
          <div class="demo-import-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="demoImportTitle">
            <div class="demo-import-confirm-content">
              <h2 class="mb-4" id="demoImportTitle">
                <i class="fa fa-users me-2 text-success"></i>
                ${i18next.t("Import Demo Data")}
              </h2>
              
              <div class="alert alert-info mb-4">
                <strong>${i18next.t("This will add sample families, people, and groups to your database.")}</strong>
              </div>

              <div class="demo-import-options mb-4">
                <h4 class="mb-3">${i18next.t("Optional Data to Include")}</h4>
                <div class="form-check mb-2">
                  <input type="checkbox" class="form-check-input" id="includeDemoFinancial" checked>
                  <label class="form-check-label" for="includeDemoFinancial">
                    ${i18next.t("Include financial data (donation funds, pledges)")}
                  </label>
                </div>
                <div class="form-check mb-2">
                  <input type="checkbox" class="form-check-input" id="includeDemoEvents" checked>
                  <label class="form-check-label" for="includeDemoEvents">
                    ${i18next.t("Include events and calendars")}
                  </label>
                </div>
                <div class="form-check">
                  <input type="checkbox" class="form-check-input" id="includeDemoSundaySchool" checked>
                  <label class="form-check-label" for="includeDemoSundaySchool">
                    ${i18next.t("Include Sunday School classes and enrollments")}
                  </label>
                </div>
              </div>

              <div class="demo-import-instructions mb-4">
                <h4 class="mb-3">${i18next.t("Need to remove this data later?")}</h4>
                <p>${i18next.t("After importing, you can use Reset Database on the Admin Dashboard to clear all data and start fresh. Your application configuration will be preserved.")}</p>
              </div>

              <div class="demo-import-buttons">
                <button type="button" class="btn btn-success" id="demoImportConfirmBtn">
                  <i class="fa fa-users me-2"></i>${i18next.t("Import Demo Data")}
                </button>
                <button type="button" class="btn btn-secondary" id="demoImportCancelBtn">
                  ${i18next.t("Cancel")}
                </button>
              </div>
            </div>
          </div>
        </div>
      `);
      // Ensure Bootstrap utility class is applied to labels in the injected overlay
      $("#demoImportConfirmOverlay .form-check-label").addClass("user-select-none");
    }
  }

  // Create and inject loading spinner overlay
  function ensureSpinnerOverlay() {
    if ($("#demoImportSpinnerOverlay").length === 0) {
      $("body").append(`
        <div id="demoImportSpinnerOverlay" class="demo-import-overlay spinner">
          <div class="demo-import-spinner">
            <div class="spinner-border text-primary mb-3" role="status">
              <span class="visually-hidden">${i18next.t("Loading...")}</span>
            </div>
            <h3 class="text-white mb-0">${i18next.t("Importing demo data...")}</h3>
            <p class="text-white-50 mt-2">${i18next.t("Please do not refresh or navigate away")}</p>
          </div>
        </div>
      `);
    }
  }

  function showConfirmOverlay() {
    ensureConfirmOverlay();
    $("body").addClass("demo-import-open");
    $("#demoImportConfirmOverlay").addClass("show");
    $("#demoImportCancelBtn").trigger("focus");
  }

  function hideConfirmOverlay() {
    $("body").removeClass("demo-import-open");
    $("#demoImportConfirmOverlay").removeClass("show");
  }

  function showSpinnerOverlay() {
    ensureSpinnerOverlay();
    $("#demoImportSpinnerOverlay").addClass("show");
  }

  function hideSpinnerOverlay() {
    $("body").removeClass("demo-import-open");
    $("#demoImportSpinnerOverlay").removeClass("show");
  }

  // Track if we're in force import mode
  var forceImportMode = false;

  // Show an upfront warning (before any API call) when the database already
  // contains user data. The /api/demo/load endpoint only runs on a truly
  // fresh install, so we preemptively switch into "force import" mode and
  // tell the user what's about to happen instead of letting them discover
  // a 403 after clicking through the overlay.
  function setExistingDataWarning(personCount, familyCount) {
    $("#demoImportWarning").remove();

    var countsLine = "";
    if (personCount > 0 || familyCount > 0) {
      countsLine =
        '<div class="demo-import-count-line">' +
        i18next.t("Current database") +
        ": " +
        "<strong>" +
        personCount +
        "</strong> " +
        i18next.t("people") +
        ", " +
        "<strong>" +
        familyCount +
        "</strong> " +
        i18next.t("families") +
        "</div>";
    }

    var warningHtml =
      '<div id="demoImportWarning" class="alert alert-warning mb-4 demo-import-warning">' +
      '<div class="demo-import-warning-title">' +
      '<i class="fa fa-triangle-exclamation me-2"></i>' +
      i18next.t("Your database already contains data") +
      "</div>" +
      '<div class="demo-import-warning-body">' +
      i18next.t(
        "Demo data is normally only loaded on fresh installations. Importing now will add sample records alongside your existing data and may create duplicates.",
      ) +
      "</div>" +
      countsLine +
      '<div class="demo-import-warning-hint">' +
      '<i class="fa fa-circle-info me-1"></i>' +
      i18next.t(
        "To start over with a clean database, use Reset Database from the Admin Dashboard before importing demo data.",
      ) +
      "</div></div>";
    $(".demo-import-options").before(warningHtml);
    $(".demo-import-instructions").addClass("d-none");

    $("#demoImportConfirmBtn")
      .removeClass("btn-success")
      .addClass("btn-warning")
      .html('<i class="fa fa-triangle-exclamation me-2"></i>' + i18next.t("Import demo data anyway"));
  }

  function doImport($button, includeFinancial, includeEvents, includeSundaySchool, forceImport) {
    var $status = $("#demoImportStatus");
    var $results = $("#demoImportResults");
    var $resultsList = $("#demoImportResultsList");

    hideConfirmOverlay();
    showSpinnerOverlay();

    if ($status.length) {
      $status.show();
    }
    if ($results.length) {
      $results.hide();
    }

    $button.prop("disabled", true);

    window.CRM.AdminAPIRequest({
      path: "demo/load",
      method: "POST",
      data: JSON.stringify({
        includeFinancial: includeFinancial,
        includeEvents: includeEvents,
        includeSundaySchool: includeSundaySchool,
        force: forceImport || false,
      }),
    })
      .done(function (data) {
        hideSpinnerOverlay();

        if ($status.length) {
          $status.hide();
        }
        $button.prop("disabled", false);

        if (data && data.success) {
          // Reset force import mode on success
          forceImportMode = false;
          resetImportButton();

          if ($resultsList.length) {
            $resultsList.empty();
            var imported = data.imported || {};
            for (var key in imported) {
              if (Object.prototype.hasOwnProperty.call(imported, key) && imported[key] > 0) {
                var label = key.replace(/_/g, " ").replace(/\b\w/g, function (l) {
                  return l.toUpperCase();
                });
                $resultsList.append("<li>" + label + ": <strong>" + imported[key] + "</strong></li>");
              }
            }
            if (data.warnings && data.warnings.length > 0) {
              $resultsList.append(
                '<li class="text-warning">' + i18next.t("Warnings") + ": " + data.warnings.length + "</li>",
              );
            }
            $results.show();
          }

          window.CRM.notify(i18next.t("Demo data imported successfully"), { type: "success", delay: 3000 });
        } else {
          // Enable force import mode on failure
          forceImportMode = true;

          var errorMsg = data && data.message ? data.message : i18next.t("Unknown error");

          // Update button to Force Import and show overlay again with error
          setForceImportButton(errorMsg);
          showConfirmOverlay();
        }
      })
      .fail(function (xhr) {
        hideSpinnerOverlay();

        if ($status.length) {
          $status.hide();
        }
        $button.prop("disabled", false);

        // Enable force import mode on error
        forceImportMode = true;

        var errorMessage = i18next.t("An error occurred during demo data import");
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        } else if (xhr && xhr.status) {
          errorMessage = i18next.t("Server error") + " (" + xhr.status + ")";
        }

        // Update button to Force Import and show overlay again with error
        setForceImportButton(errorMessage);
        showConfirmOverlay();
      });
  }

  function setForceImportButton(errorMessage) {
    // Remove existing warning if any
    $("#demoImportWarning").remove();

    // Show inline warning message with specific error - better readability
    var warningHtml =
      '<div id="demoImportWarning" class="alert alert-danger mb-4 demo-import-warning">' +
      '<div class="demo-import-warning-title">' +
      '<i class="fa fa-circle-exclamation me-2"></i>' +
      i18next.t("Import failed") +
      "</div>" +
      '<div class="demo-import-warning-body">' +
      (errorMessage || i18next.t("Unknown error")) +
      "</div>" +
      '<div class="demo-import-warning-hint">' +
      '<i class="fa fa-circle-info me-1"></i>' +
      i18next.t("Force Import will retry and may create duplicate data.") +
      "</div></div>";
    $(".demo-import-options").before(warningHtml);

    $("#demoImportConfirmBtn")
      .removeClass("btn-success")
      .addClass("btn-danger")
      .html('<i class="fa fa-triangle-exclamation me-2"></i>' + i18next.t("Force Import"));
  }

  function resetImportButton() {
    $("#demoImportWarning").remove();
    $(".demo-import-instructions").removeClass("d-none");
    $("#demoImportConfirmBtn")
      .removeClass("btn-danger btn-warning")
      .addClass("btn-success")
      .html('<i class="fa fa-users me-2"></i>' + i18next.t("Import Demo Data"));
  }

  function attachHandlers() {
    var selectors = ["#importDemoData", "#importDemoDataQuickBtn", "#importDemoDataV2"];
    selectors.forEach(function (sel) {
      var $btn = $(sel);
      if ($btn.length) {
        $btn.off("click").on("click", function (e) {
          e.preventDefault();
          console.log("Import button clicked, showing confirmation overlay");

          // If the rendering page told us the database already has data,
          // jump straight into force-import mode with an upfront warning
          // instead of letting the user discover the 403 after clicking.
          var hasExistingData =
            $(this).data("has-existing-data") === true || $(this).data("has-existing-data") === "true";
          ensureConfirmOverlay();
          if (hasExistingData) {
            forceImportMode = true;
            var personCount = parseInt($(this).data("person-count"), 10) || 0;
            var familyCount = parseInt($(this).data("family-count"), 10) || 0;
            setExistingDataWarning(personCount, familyCount);
          } else {
            forceImportMode = false;
            resetImportButton();
          }

          showConfirmOverlay();
        });
      }
    });

    // Attach confirm button handler
    $(document)
      .off("click", "#demoImportConfirmBtn")
      .on("click", "#demoImportConfirmBtn", function (e) {
        e.preventDefault();
        console.log("Confirm button clicked, starting import");
        var includeFinancial = $("#includeDemoFinancial").is(":checked");
        var includeEvents = $("#includeDemoEvents").is(":checked");
        var includeSundaySchool = $("#includeDemoSundaySchool").is(":checked");
        var $btn = $("#importDemoData, #importDemoDataQuickBtn, #importDemoDataV2").first();

        // Pass forceImport flag based on current mode
        doImport($btn, includeFinancial, includeEvents, includeSundaySchool, forceImportMode);
      });

    // Attach cancel button handler
    $(document)
      .off("click", "#demoImportCancelBtn")
      .on("click", "#demoImportCancelBtn", function (e) {
        e.preventDefault();
        console.log("Cancel button clicked");
        hideConfirmOverlay();
      });

    // Esc or a click on the dimmed backdrop closes the dialog
    $(document)
      .off("keydown.demoImport")
      .on("keydown.demoImport", function (e) {
        if (e.key === "Escape" && $("#demoImportConfirmOverlay").hasClass("show")) {
          hideConfirmOverlay();
        }
      });
    $(document)
      .off("click", "#demoImportConfirmOverlay")
      .on("click", "#demoImportConfirmOverlay", function (e) {
        if (e.target === this) {
          hideConfirmOverlay();
        }
      });
  }

  $(document).ready(function () {
    if (window.CRM && typeof window.CRM.onLocalesReady === "function") {
      window.CRM.onLocalesReady(function () {
        attachHandlers();
      });
    } else {
      attachHandlers();
    }
  });
})(jQuery);
