/* Shared Import Demo Data behavior for admin/system-maintenance and v2 dashboard
 * - Attaches to buttons with IDs: #importDemoData, #importDemoDataQuickBtn and #importDemoDataV2
 * - Opens one Tabler modal that confirms the import, shows progress while it runs, and shows any error
 * - Uses optional status/result elements when present (#demoImportStatus, #demoImportResults, #demoImportResultsList)
 */
(($) => {
  var importing = false;
  var forceImportMode = false;

  function ensureModal() {
    if ($("#demoImportModal").length) {
      return;
    }
    var included = [
      i18next.t("Families, people and groups"),
      i18next.t("Events and calendars, including the Member Portal calendars"),
      i18next.t("Sunday School classes"),
      i18next.t("Volunteer ministries, teams, schedules and assignments"),
      i18next.t("Donation funds, pledges and giving records"),
    ]
      .map((item) => '<li class="mb-2"><i class="fa-solid fa-check text-success me-2"></i>' + item + "</li>")
      .join("");

    $("body").append(`
      <div class="modal modal-blur fade" id="demoImportModal" tabindex="-1" aria-labelledby="demoImportTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="demoImportTitle">${i18next.t("Import Demo Data")}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${i18next.t("Close")}"></button>
            </div>
            <div class="modal-body" id="demoImportConfirm">
              <p class="fw-medium">${i18next.t("This will add sample data to your database")}:</p>
              <ul class="list-unstyled mb-0">${included}</ul>
              <div id="demoImportInstructions" class="mt-3 pt-3 border-top">
                <div class="fw-medium">${i18next.t("Need to remove this data later?")}</div>
                <div class="text-secondary">${i18next.t("After importing, you can use Reset Database on the Admin Dashboard to clear all data and start fresh. Your application configuration will be preserved.")}</div>
              </div>
            </div>
            <div class="modal-body text-center py-5 d-none" id="demoImportProgress">
              <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">${i18next.t("Loading...")}</span>
              </div>
              <h3>${i18next.t("Importing demo data...")}</h3>
              <p class="text-secondary mb-0">${i18next.t("Please do not refresh or navigate away")}</p>
            </div>
            <div class="modal-footer" id="demoImportFooter">
              <button type="button" class="btn btn-link link-secondary me-auto" id="demoImportCancelBtn" data-bs-dismiss="modal">${i18next.t("Cancel")}</button>
              <button type="button" class="btn btn-success" id="demoImportConfirmBtn">
                <i class="fa-solid fa-users me-2"></i>${i18next.t("Import Demo Data")}
              </button>
            </div>
          </div>
        </div>
      </div>
    `);

    var modalEl = document.getElementById("demoImportModal");
    modalEl.addEventListener("hide.bs.modal", (e) => {
      if (importing) {
        e.preventDefault();
      }
    });
    modalEl.addEventListener("shown.bs.modal", () => {
      $("#demoImportCancelBtn").trigger("focus");
    });
  }

  function modal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById("demoImportModal"));
  }

  function showConfirmState() {
    $("#demoImportProgress").addClass("d-none");
    $("#demoImportConfirm, #demoImportFooter").removeClass("d-none");
  }

  function showProgressState() {
    importing = true;
    $("#demoImportConfirm, #demoImportFooter").addClass("d-none");
    $("#demoImportProgress").removeClass("d-none");
  }

  function finishImport() {
    importing = false;
    showConfirmState();
  }

  // Show an upfront warning (before any API call) when the database already
  // contains user data. The /api/demo/load endpoint only runs on a truly
  // fresh install, so we preemptively switch into "force import" mode and
  // tell the user what's about to happen instead of letting them discover
  // a 403 after clicking through the dialog.
  function setExistingDataWarning(personCount, familyCount) {
    $("#demoImportWarning").remove();

    var countsLine = "";
    if (personCount > 0 || familyCount > 0) {
      countsLine =
        '<div class="mt-2 text-body">' +
        i18next.t("Current database") +
        ": <strong>" +
        personCount +
        "</strong> " +
        i18next.t("people") +
        ", <strong>" +
        familyCount +
        "</strong> " +
        i18next.t("families") +
        "</div>";
    }

    var warningHtml =
      '<div id="demoImportWarning" class="alert alert-warning mt-3 mb-0" role="alert">' +
      '<h4 class="alert-title"><i class="fa-solid fa-triangle-exclamation me-2"></i>' +
      i18next.t("Your database already contains data") +
      "</h4>" +
      '<div class="text-body">' +
      i18next.t(
        "Demo data is normally only loaded on fresh installations. Importing now will add sample records alongside your existing data and may create duplicates.",
      ) +
      "</div>" +
      countsLine +
      '<div class="text-secondary mt-2"><i class="fa-solid fa-circle-info me-1"></i>' +
      i18next.t(
        "To start over with a clean database, use Reset Database from the Admin Dashboard before importing demo data.",
      ) +
      "</div></div>";
    $("#demoImportInstructions").before(warningHtml).addClass("d-none");

    $("#demoImportConfirmBtn")
      .removeClass("btn-success")
      .addClass("btn-warning")
      .html('<i class="fa-solid fa-triangle-exclamation me-2"></i>' + i18next.t("Import demo data anyway"));
  }

  function setForceImportButton(errorMessage) {
    $("#demoImportWarning").remove();

    var warningHtml =
      '<div id="demoImportWarning" class="alert alert-danger mt-3 mb-0" role="alert">' +
      '<h4 class="alert-title"><i class="fa-solid fa-circle-exclamation me-2"></i>' +
      i18next.t("Import failed") +
      "</h4>" +
      '<div class="text-body">' +
      $("<div>")
        .text(errorMessage || i18next.t("Unknown error"))
        .html() +
      "</div>" +
      '<div class="text-secondary mt-2"><i class="fa-solid fa-circle-info me-1"></i>' +
      i18next.t("Force Import will retry and may create duplicate data.") +
      "</div></div>";
    $("#demoImportInstructions").before(warningHtml);

    $("#demoImportConfirmBtn")
      .removeClass("btn-success")
      .addClass("btn-danger")
      .html('<i class="fa-solid fa-triangle-exclamation me-2"></i>' + i18next.t("Force Import"));
  }

  function resetImportButton() {
    $("#demoImportWarning").remove();
    $("#demoImportInstructions").removeClass("d-none");
    $("#demoImportConfirmBtn")
      .removeClass("btn-danger btn-warning")
      .addClass("btn-success")
      .html('<i class="fa-solid fa-users me-2"></i>' + i18next.t("Import Demo Data"));
  }

  function failImport(message) {
    forceImportMode = true;
    finishImport();
    setForceImportButton(message);
  }

  function doImport($button, forceImport) {
    var $status = $("#demoImportStatus");
    var $results = $("#demoImportResults");
    var $resultsList = $("#demoImportResultsList");

    showProgressState();

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
        force: forceImport || false,
      }),
    })
      .done((data) => {
        if ($status.length) {
          $status.hide();
        }
        $button.prop("disabled", false);

        if (data && data.success) {
          forceImportMode = false;
          resetImportButton();
          finishImport();
          modal().hide();

          if ($resultsList.length) {
            $resultsList.empty();
            var imported = data.imported || {};
            for (var key in imported) {
              if (Object.hasOwn(imported, key) && imported[key] > 0) {
                var label = key.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
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
          failImport(data && data.message ? data.message : i18next.t("Unknown error"));
        }
      })
      .fail((xhr) => {
        if ($status.length) {
          $status.hide();
        }
        $button.prop("disabled", false);

        var errorMessage = i18next.t("An error occurred during demo data import");
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          errorMessage = xhr.responseJSON.message;
        } else if (xhr && xhr.status) {
          errorMessage = i18next.t("Server error") + " (" + xhr.status + ")";
        }
        failImport(errorMessage);
      });
  }

  function attachHandlers() {
    ["#importDemoData", "#importDemoDataQuickBtn", "#importDemoDataV2"].forEach((sel) => {
      var $btn = $(sel);
      if (!$btn.length) {
        return;
      }
      $btn.off("click").on("click", function (e) {
        e.preventDefault();
        ensureModal();
        showConfirmState();

        // The rendering page says when the database already has data, so
        // the user sees the warning before clicking instead of a 403 after.
        var hasExistingData =
          $(this).data("has-existing-data") === true || $(this).data("has-existing-data") === "true";
        if (hasExistingData) {
          forceImportMode = true;
          setExistingDataWarning(
            parseInt($(this).data("person-count"), 10) || 0,
            parseInt($(this).data("family-count"), 10) || 0,
          );
        } else {
          forceImportMode = false;
          resetImportButton();
        }

        modal().show();
      });
    });

    $(document)
      .off("click", "#demoImportConfirmBtn")
      .on("click", "#demoImportConfirmBtn", (e) => {
        e.preventDefault();
        doImport($("#importDemoData, #importDemoDataQuickBtn, #importDemoDataV2").first(), forceImportMode);
      });
  }

  $(document).ready(() => {
    if (window.CRM && typeof window.CRM.onLocalesReady === "function") {
      window.CRM.onLocalesReady(attachHandlers);
    } else {
      attachHandlers();
    }
  });
})(jQuery);
