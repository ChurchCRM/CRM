/**
 * Issue Reporter Module — ES6 conversion
 * Handles GitHub issue modal interactions
 * Imported as a webpack module in skin-main.js after jQuery is set up
 */

import $ from "jquery";

/**
 * Initialize issue reporter click handlers
 * Called via DOM ready to ensure elements are available
 */
function initializeIssueReporter() {
  // Guard against jQuery not being available
  if (!$ || typeof $.ajax !== "function") {
    console.warn("[IssueReporter] jQuery not fully available, deferring initialization");
    setTimeout(initializeIssueReporter, 500);
    return;
  }

  // Attach click handler to submit button
  $("#submitIssue").click(function () {
    var $btn = $(this);
    var description = $("#issueDescription").val().trim();

    // Loading state
    $btn.prop("disabled", true).html(
      '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Opening…'
    );

    var postData = {
      pageName: $("input[name=pageName]").val(),
      screenSize: {
        height: screen.height,
        width: screen.width,
      },
      windowSize: {
        height: $(window).height(),
        width: $(window).width(),
      },
      pageSize: {
        height: $(document).height(),
        width: $(document).width(),
      },
    };

    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/issues",
      data: JSON.stringify(postData),
      contentType: "application/json; charset=utf-8",
      dataType: "json",
    })
      .done(function (data) {
        var userDescription = description
          ? description + "\n\n"
          : "**Describe the issue** \n\n\n\n";
        var systemInfo = encodeURIComponent(userDescription + data["issueBody"]);
        var gitHubTemplateURL =
          "https://github.com/ChurchCRM/CRM/issues/new?type=bug&body=" +
          systemInfo;
        window.open(gitHubTemplateURL, "github");

        // Success feedback before closing
        $btn.html('<i class="ti ti-check me-1"></i> Opened!');
        setTimeout(function () {
          $("#IssueReportModal").modal("hide");
        }, 800);
      })
      .fail(function () {
        $btn
          .prop("disabled", false)
          .html('<i class="ti ti-brand-github me-1"></i> Open GitHub Issue');
        alert("Could not gather system info. Please try again.");
      })
      .always(function () {
        // Reset button and textarea when modal is fully hidden
        $("#IssueReportModal").one("hidden.bs.modal", function () {
          $btn
            .prop("disabled", false)
            .html('<i class="ti ti-brand-github me-1"></i> Open GitHub Issue');
          $("#issueDescription").val("");
        });
      });
  });
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializeIssueReporter);
} else {
  // DOM is already loaded (if this script loads late)
  initializeIssueReporter();
}
