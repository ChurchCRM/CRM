/* Shared Import Demo Data behavior for admin/system-maintenance and v2 dashboard
 * - Attaches to buttons with IDs: #importDemoData, #importDemoDataQuickBtn and #importDemoDataV2
 * - Shows a big confirmation overlay with reset instructions
 * - Displays full-screen spinner while importing to block all user interaction
 * - Uses optional status/result elements when present (#demoImportStatus, #demoImportResults, #demoImportResultsList)
 */
(function ($) {
    // Inject shared styles once
    function injectStyles() {
        if ($("#demo-import-styles").length === 0) {
            $("head").append(`
        <style id="demo-import-styles">
          body.demo-import-open {
            overflow: hidden;
          }
          .demo-import-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 99999;
          }
          .demo-import-overlay.show {
            display: flex !important;
          }
          .demo-import-overlay.confirm {
            z-index: 99999;
          }
          .demo-import-overlay.spinner {
            z-index: 100000;
          }
          .demo-import-confirm-modal {
            width: 100%;
            max-width: 700px;
            position: relative;
            z-index: 100001;
          }
          .demo-import-confirm-content {
            background: white;
            padding: 60px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
          }
          @keyframes slideIn {
            from {
              transform: translateY(-20px) scale(0.95);
              opacity: 0;
            }
            to {
              transform: translateY(0) scale(1);
              opacity: 1;
            }
          }
          .demo-import-confirm-content h2 {
            font-size: 36px;
            font-weight: 700;
            color: #222;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
          }
          .demo-import-confirm-content h2 i {
            color: #27ae60;
          }
          .demo-import-confirm-content .alert {
            font-size: 16px;
            padding: 18px;
            margin-bottom: 28px;
            border-radius: 6px;
            font-weight: 500;
          }
          .demo-import-confirm-content h4 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-top: 0;
            margin-bottom: 18px;
          }
          .demo-import-confirm-content p {
            font-size: 15px;
            line-height: 1.7;
            color: #555;
            margin-bottom: 16px;
          }
          .demo-import-instructions {
            background: #fafafa;
            padding: 24px;
            border-radius: 8px;
            border-left: 5px solid #ffc107;
            margin-bottom: 32px;
          }
          .demo-import-instructions p:last-child {
            margin-bottom: 0;
          }
          .demo-import-options {
            margin-bottom: 28px;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
          }
          .demo-import-options h4 {
            margin-top: 0;
          }
          .demo-import-options .form-check {
            text-align: left;
            padding-left: 0;
            margin-bottom: 14px;
            display: block;
          }
          .demo-import-options .form-check:last-child {
            margin-bottom: 0;
          }
          .demo-import-options .form-check-input {
            cursor: pointer;
            width: 22px;
            height: 22px;
            margin-right: 12px;
            margin-top: 0;
            vertical-align: middle;
            position: relative;
            top: 1px;
            accent-color: #27ae60;
          }
          .demo-import-options .form-check-label {
            user-select: none;
            cursor: pointer;
            margin-bottom: 0;
            font-size: 15px;
            line-height: 1.6;
            display: inline;
            margin-left: 4px;
            color: #444;
          }
          .demo-import-buttons {
            display: flex;
            gap: 16px;
            margin-top: 40px;
          }
          .demo-import-buttons .btn {
            flex: 1;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 6px;
            border: none;
            transition: all 0.2s ease;
          }
          .demo-import-buttons .btn-success {
            background-color: #27ae60;
            color: white;
          }
          .demo-import-buttons .btn-success:hover {
            background-color: #229954;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
          }
          .demo-import-buttons .btn-secondary {
            background-color: #95a5a6;
            color: white;
          }
          .demo-import-buttons .btn-secondary:hover {
            background-color: #7f8c8d;
            transform: translateY(-2px);
          }
          .demo-import-spinner {
            text-align: center;
            position: relative;
            z-index: 100001;
          }
          .demo-import-spinner .spinner-border {
            width: 80px;
            height: 80px;
            border-width: 6px;
            margin-bottom: 24px;
            animation: spin 1s linear infinite;
          }
          @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
          .demo-import-spinner h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
          }
          .demo-import-spinner p {
            font-size: 16px;
            opacity: 0.9;
          }
        </style>
      `);
        }
    }

    // Create and inject confirmation overlay HTML if not already present
    function ensureConfirmOverlay() {
        if ($("#demoImportConfirmOverlay").length === 0) {
            injectStyles();
            $("body").append(`
        <div id="demoImportConfirmOverlay" class="demo-import-overlay confirm" style="display: none;">
          <div class="demo-import-confirm-modal">
            <div class="demo-import-confirm-content">
              <h2 class="mb-4">
                <i class="fa fa-users mr-2 text-success"></i>
                ${i18next.t("Import Demo Data")}
              </h2>
              
              <div class="alert alert-info mb-4">
                <strong>This will add sample families, people, and groups to your database.</strong>
              </div>

              <div class="demo-import-options mb-4">
                <h4 class="mb-3">${i18next.t("Optional Data to Include")}</h4>
                <div class="form-check mb-2">
                  <input type="checkbox" class="form-check-input" id="includeDemoFinancial" disabled>
                  <label class="form-check-label text-muted" for="includeDemoFinancial">
                    ${i18next.t("Include financial data (donation funds, pledges)")} <span class="text-muted small">(${i18next.t("Coming soon")})</span>
                  </label>
                </div>
                <div class="form-check mb-2">
                  <input type="checkbox" class="form-check-input" id="includeDemoEvents" disabled>
                  <label class="form-check-label text-muted" for="includeDemoEvents">
                    ${i18next.t("Include events and calendars")} <span class="text-muted small">(${i18next.t("Coming soon")})</span>
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
                <p>After importing, you can visit the <strong>Admin Dashboard</strong> and click the <strong>Reset Database</strong> button to clear all data and start fresh. Your application configuration will be preserved.</p>
              </div>

              <div class="demo-import-buttons">
                <button type="button" class="btn btn-success" id="demoImportConfirmBtn">
                  <i class="fa fa-users mr-2"></i>${i18next.t("Import Demo Data")}
                </button>
                <button type="button" class="btn btn-secondary" id="demoImportCancelBtn">
                  ${i18next.t("Cancel")}
                </button>
              </div>
            </div>
          </div>
        </div>
      `);
        }
    }

    // Create and inject loading spinner overlay
    function ensureSpinnerOverlay() {
        if ($("#demoImportSpinnerOverlay").length === 0) {
            injectStyles();
            $("body").append(`
        <div id="demoImportSpinnerOverlay" class="demo-import-overlay spinner" style="display: none;">
          <div class="demo-import-spinner">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 60px; height: 60px;">
              <span class="sr-only">Loading...</span>
            </div>
            <h3 class="text-white mb-0">Importing demo data...</h3>
            <p class="text-white-50 mt-2">Please do not refresh or navigate away</p>
          </div>
        </div>
      `);
        }
    }

    function showConfirmOverlay() {
        ensureConfirmOverlay();
        $("body").addClass("demo-import-open");
        $("#demoImportConfirmOverlay").addClass("show");
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

        $.ajax({
            url: window.CRM.root + "/admin/api/demo/load",
            method: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                includeFinancial: includeFinancial,
                includeEvents: includeEvents,
                includeSundaySchool: includeSundaySchool,
                force: forceImport || false,
            }),
            success: function (data) {
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
                                '<li class="text-warning">' +
                                    i18next.t("Warnings") +
                                    ": " +
                                    data.warnings.length +
                                    "</li>",
                            );
                        }
                        $results.show();
                    }

                    window.CRM.notify(i18next.t("Demo data imported successfully"), { type: "success", delay: 3000 });
                } else {
                    // Enable force import mode on failure
                    forceImportMode = true;

                    var errorMsg = data && data.error ? data.error : i18next.t("Unknown error");

                    // Update button to Force Import and show overlay again with error
                    setForceImportButton(errorMsg);
                    showConfirmOverlay();
                }
            },
            error: function (xhr) {
                hideSpinnerOverlay();

                if ($status.length) {
                    $status.hide();
                }
                $button.prop("disabled", false);

                // Enable force import mode on error
                forceImportMode = true;

                var errorMessage = i18next.t("An error occurred during demo data import");
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr && xhr.status) {
                    errorMessage = i18next.t("Server error") + " (" + xhr.status + ")";
                }

                // Update button to Force Import and show overlay again with error
                setForceImportButton(errorMessage);
                showConfirmOverlay();
            },
        });
    }

    function setForceImportButton(errorMessage) {
        // Remove existing warning if any
        $("#demoImportWarning").remove();

        // Show inline warning message with specific error - better readability
        var warningHtml =
            '<div id="demoImportWarning" class="alert alert-danger mb-4" style="border-left: 4px solid #dc3545; padding: 16px;">' +
            '<div style="font-weight: 600; font-size: 15px; margin-bottom: 8px; color: #721c24;">' +
            '<i class="fa fa-exclamation-circle mr-2"></i>' +
            i18next.t("Import failed") +
            "</div>" +
            '<div style="font-size: 14px; color: #495057; margin-bottom: 12px; line-height: 1.5;">' +
            (errorMessage || i18next.t("Unknown error")) +
            "</div>" +
            '<div style="font-size: 13px; color: #856404; background: #fff3cd; padding: 10px 12px; border-radius: 4px; border: 1px solid #ffeeba;">' +
            '<i class="fa fa-info-circle mr-1"></i>' +
            i18next.t("Force Import will retry and may create duplicate data.") +
            "</div></div>";
        $(".demo-import-options").before(warningHtml);

        $("#demoImportConfirmBtn")
            .removeClass("btn-success")
            .addClass("btn-danger")
            .html('<i class="fa fa-exclamation-triangle mr-2"></i>' + i18next.t("Force Import"));
    }

    function resetImportButton() {
        $("#demoImportWarning").remove();
        $("#demoImportConfirmBtn")
            .removeClass("btn-danger")
            .addClass("btn-success")
            .html('<i class="fa fa-users mr-2"></i>' + i18next.t("Import Demo Data"));
    }

    function attachHandlers() {
        var selectors = ["#importDemoData", "#importDemoDataQuickBtn", "#importDemoDataV2"];
        selectors.forEach(function (sel) {
            var $btn = $(sel);
            if ($btn.length) {
                $btn.off("click").on("click", function (e) {
                    e.preventDefault();
                    console.log("Import button clicked, showing confirmation overlay");
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
