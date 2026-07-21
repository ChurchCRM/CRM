function initializeFamilyView() {
  // Print button
  $("#printFamily").on("click", () => {
    window.print();
  });

  if (!window.CRM.currentActive) {
    $("#family-deactivated").removeClass("d-none");
  }

  // Check if family has a photo (uploaded or gravatar) and show/hide view button accordingly
  // Query the avatar info endpoint to see if there's an actual photo to display
  fetch(window.CRM.root + "/api/family/" + window.CRM.currentFamily + "/avatar")
    .then((response) => response.json())
    .then((data) => {
      // Show view button only if there's an actual uploaded photo (hasPhoto=true)
      if (data.hasPhoto) {
        $("#view-larger-image-btn").removeClass("hide-if-no-photo");
        $("#view-larger-image-btn").removeClass("d-none");
      } else {
        // Keep hidden for initials/gravatar only
        $("#view-larger-image-btn").addClass("hide-if-no-photo");
        $("#view-larger-image-btn").addClass("d-none");
      }
    })
    .catch((error) => {
      console.error("Failed to fetch avatar info:", error);
      $("#view-larger-image-btn").addClass("hide-if-no-photo");
      $("#view-larger-image-btn").addClass("d-none");
    });

  window.CRM.APIRequest({
    path: `family/${window.CRM.currentFamily}/nav`,
  }).then((data) => {
    if (data?.PreFamilyId) {
      $("#lastFamily").attr("href", `${window.CRM.root}/people/family/${data.PreFamilyId}`);
    } else {
      $("#lastFamily").addClass("disabled").attr("aria-disabled", "true").removeAttr("href");
    }

    if (data?.NextFamilyId) {
      $("#nextFamily").attr("href", `${window.CRM.root}/people/family/${data.NextFamilyId}`);
    } else {
      $("#nextFamily").addClass("disabled").attr("aria-disabled", "true").removeAttr("href");
    }
  });

  // Family properties: inline form (matches Person page UX).
  $("#input-family-properties").on("change", () => {
    const promptBox = $("#family-property-prompt-box").removeClass("mb-3").html("");
    const selected = $("#input-family-properties :selected");
    const proPrompt = selected.data("pro_prompt");
    const proValue = selected.data("pro_value");
    if (proPrompt) {
      promptBox
        .addClass("mb-3")
        .append($("<label></label>").text(proPrompt))
        .append($('<textarea rows="3" class="form-control" name="PropertyValue"></textarea>').val(proValue || ""));
    }
  });

  $("#assign-family-property-btn").on("click", () => {
    let propertyId = "";
    let value = "";
    $("#assign-family-property-form")
      .serializeArray()
      .forEach((field) => {
        if (field.name === "PropertyId") propertyId = field.value;
        else if (field.name === "PropertyValue") value = field.value;
      });
    if (!propertyId) return;
    window.CRM.APIRequest({
      method: "POST",
      path: `people/properties/family/${window.CRM.currentFamily}/${propertyId}`,
      data: JSON.stringify({ value: value }),
    }).done(() => {
      location.reload();
    });
  });

  $(".remove-family-property-btn").on("click", function () {
    const propertyId = $(this).data("property_id");
    bootbox.confirm(i18next.t("Are you sure you want to unassign this property?"), (result) => {
      if (result) {
        window.CRM.APIRequest({
          method: "DELETE",
          path: `people/properties/family/${window.CRM.currentFamily}/${propertyId}`,
        }).done(() => {
          location.reload();
        });
      }
    });
  });

  // Pledges & Payments table — init after ensuring both types are returned by API
  if ($("#pledge-payment-v2-table").length) {
    const dataTableConfig = {
      ajax: {
        url: `${window.CRM.root}/api/payments/family/${window.CRM.currentFamily}/list`,
        dataSrc: "data",
      },
      columns: [
        {
          title: i18next.t("Type"),
          data: "PledgeOrPayment",
          render: (data) => {
            const color = data === "Pledge" ? "blue" : "green";
            const icon = data === "Pledge" ? "fa-hand-holding-dollar" : "fa-money-bill-wave";
            return `<span class="badge bg-${color}-lt text-${color}"><i class="fa-solid ${icon} me-1"></i>${data}</span>`;
          },
        },
        { title: i18next.t("Fund"), data: "Fund" },
        { title: i18next.t("Date"), type: "date", data: "Date" },
        {
          title: i18next.t("Amount"),
          type: "num",
          data: "Amount",
          className: "text-end",
          render: (data) => "$" + parseFloat(data).toFixed(2),
        },
        { title: i18next.t("Fiscal Year"), data: "FormattedFY" },
        { title: i18next.t("Method"), data: "Method" },
        { title: i18next.t("Comment"), data: "Comment" },
        {
          width: "40px",
          sortable: false,
          title: "",
          data: "GroupKey",
          className: "all no-export",
          render: (data, type, row) => {
            const editUrl = window.CRM.root + "/finance/pledge/" + encodeURIComponent(row.GroupKey) + "/edit";
            return (
              '<div class="dropdown">' +
              '<button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>' +
              '<div class="dropdown-menu dropdown-menu-end">' +
              '<a class="dropdown-item" href="' +
              editUrl +
              '"><i class="fa-solid fa-pen me-2"></i>' +
              i18next.t("Edit") +
              "</a>" +
              '<button type="button" class="dropdown-item text-danger pledge-delete-btn" data-group-key="' +
              row.GroupKey.replace(/"/g, "&quot;") +
              '"><i class="fa-solid fa-trash-can me-2"></i>' +
              i18next.t("Delete") +
              "</button>" +
              "</div></div>"
            );
          },
          searchable: false,
        },
      ],
      order: [[2, "desc"]],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);

    // Force both types visible in API, then init DataTable
    Promise.all([
      window.CRM.APIRequest({
        method: "POST",
        path: `user/${window.CRM.userId}/setting/finance.show.pledges`,
        dataType: "json",
        data: JSON.stringify({ value: "true" }),
      }),
      window.CRM.APIRequest({
        method: "POST",
        path: `user/${window.CRM.userId}/setting/finance.show.payments`,
        dataType: "json",
        data: JSON.stringify({ value: "true" }),
      }),
    ])
      .catch(() => {}) // ignore errors
      .then(() => {
        const pledgeTable = $("#pledge-payment-v2-table").DataTable(dataTableConfig);

        // Type filter pills: client-side column 0 (Type) search
        $(".pledge-type-pill").on("click", function (e) {
          e.preventDefault();
          $(".pledge-type-pill").removeClass("active");
          $(this).addClass("active");
          pledgeTable
            .column(0)
            .search($(this).data("filter") || "")
            .draw();
        });

        // Fiscal year filter pills: client-side column 4 (Fiscal Year) search
        $(".pledge-fy-pill").on("click", function (e) {
          e.preventDefault();
          $(".pledge-fy-pill").removeClass("active");
          $(this).addClass("active");
          pledgeTable
            .column(4)
            .search($(this).data("fy") || "")
            .draw();
        });

        // Apply default FY filter (Current FY pill is active by default)
        const defaultFY = $(".pledge-fy-pill.active").data("fy") || "";
        if (defaultFY) {
          pledgeTable.column(4).search(defaultFY).draw();
        }
      });
  }

  $("#onlineVerify").on("click", () => {
    window.CRM.APIRequest({
      method: "POST",
      path: "family/" + window.CRM.currentFamily + "/verify",
    }).then(() => {
      $("#confirm-verify").modal("hide");
      showGlobalMessage(i18next.t("Verification email sent"), "success");
    });
  });

  $("#verifyNow").on("click", () => {
    window.CRM.APIRequest({
      method: "POST",
      path: "family/" + window.CRM.currentFamily + "/verify/now",
    }).then(() => {
      $("#confirm-verify").modal("hide");
      showGlobalMessage(i18next.t("Verification recorded"), "success");
    });
  });

  $("#verifyURL").on("click", () => {
    window.CRM.APIRequest({
      path: "family/" + window.CRM.currentFamily + "/verify/url",
    }).then((data) => {
      $("#confirm-verify").modal("hide");

      // Create custom modal for verification URL
      const modalHtml = `
                <div class="modal fade" id="verifyUrlModal" tabindex="-1" role="dialog" aria-labelledby="verifyUrlLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title" id="verifyUrlLabel">
                                    <i class="fa-solid fa-link me-2"></i>${i18next.t("Verification URL")}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="verifyUrlInput" value="${window.CRM.escapeHtml(data.url)}" readonly>
                                    <button class="btn btn-info" type="button" id="copyVerifyUrlBtn">
                                        <i class="fa-solid fa-copy me-2"></i>${i18next.t("Copy")}
                                    </button>
                                </div>
                                <p class="text-muted small">
                                    <i class="fa-solid fa-circle-info me-2"></i>${i18next.t("Share this URL with family members to verify their information")}
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${i18next.t("Close")}</button>
                                <a href="${window.CRM.escapeHtml(data.url)}" target="_blank" class="btn btn-primary">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-2"></i>${i18next.t("Open in New Tab")}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      // Remove old modal if exists
      $("#verifyUrlModal").remove();

      // Add new modal to page
      $("body").append(modalHtml);

      // Show modal
      $("#verifyUrlModal").modal("show");

      // Handle copy button
      $("#copyVerifyUrlBtn").on("click", () => {
        const urlInput = document.getElementById("verifyUrlInput");
        navigator.clipboard
          .writeText(urlInput.value)
          .then(() => {
            const btn = document.getElementById("copyVerifyUrlBtn");
            const originalHtml = btn.innerHTML;

            btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>' + i18next.t("Copied!");
            btn.classList.add("btn-success");
            btn.classList.remove("btn-info");

            setTimeout(() => {
              btn.innerHTML = originalHtml;
              btn.classList.remove("btn-success");
              btn.classList.add("btn-info");
            }, 2000);
          })
          .catch((err) => {
            console.error("Failed to copy:", err);
            window.CRM.notify(i18next.t("Failed to copy URL"), { type: "error" });
          });
      });

      // Cleanup when modal is closed
      $("#verifyUrlModal").on("hidden.bs.modal", () => {
        $("#verifyUrlModal").remove();
      });
    });
  });

  $("#verifyDownloadPDF").on("click", () => {
    window.open(`${window.CRM.root}/Reports/ConfirmReport.php?familyId=${window.CRM.currentFamily}`, "_blank");
    $("#confirm-verify").modal("hide");
  });

  $("#verifyEmailPDF").on("click", () => {
    $("#confirm-verify").modal("hide");
    window.location.href = `${window.CRM.root}/Reports/ConfirmReportEmail.php?familyId=${window.CRM.currentFamily}`;
  });

  // Photos
  $("#deletePhoto").on("click", () => {
    window.CRM.deletePhoto("family", window.CRM.currentFamily);
  });

  $("#view-larger-image-btn").on("click", (e) => {
    e.preventDefault();
    window.CRM.showPhotoLightbox("family", window.CRM.currentFamily);
  });

  // .view-family-photo / .view-person-photo click handlers are registered
  // globally in avatar-loader.ts

  $("#activateDeactivate").on("click", () => {
    const popupTitle = window.CRM.currentActive ? i18next.t("Confirm Deactivation") : i18next.t("Confirm Activation");
    const popupMessage = window.CRM.currentActive
      ? `${i18next.t("Please confirm deactivation of family")}: ${window.CRM.currentFamilyName}`
      : `${i18next.t("Please confirm activation of family")}: ${window.CRM.currentFamilyName}`;

    bootbox.confirm({
      title: popupTitle,
      message: `<p class="text-danger">${popupMessage}</p>`,
      callback: (result) => {
        if (result) {
          window.CRM.APIRequest({
            method: "POST",
            path: `family/${window.CRM.currentFamily}/activate/${!window.CRM.currentActive}`,
          }).then((data) => {
            if (data.success) {
              window.location.href = `${window.CRM.root}/people/family/${window.CRM.currentFamily}`;
            }
          });
        }
      },
    });
  });

  // Since date filter: save preference then reload page (server-side filter)
  $("#ShowSinceDate").on("changeDate", function () {
    const val = $(this).val();
    window.CRM.APIRequest({
      method: "POST",
      path: `user/${window.CRM.userId}/setting/finance.show.since`,
      dataType: "json",
      data: JSON.stringify({ value: val }),
    }).then(() => {
      window.location.reload();
    });
  });

  // Check if MailChimp plugin is active via API and load data if so
  // Only check if family has email (mailchimp-status-container is rendered conditionally in PHP)
  if ($("#mailchimp-status-container").length > 0 && window.CRM.familyEmail) {
    $.ajax({
      type: "GET",
      dataType: "json",
      url: window.CRM.root + "/plugins/status/mailchimp",
      success: (pluginData) => {
        if (pluginData.success && pluginData.isActive && pluginData.isConfigured) {
          // Show the MailChimp status container
          $("#mailchimp-status-container").removeClass("d-none");

          // Load the family's MailChimp data
          $.ajax({
            type: "GET",
            dataType: "json",
            url: window.CRM.root + "/plugins/mailchimp/api/family/" + window.CRM.currentFamily,
            success: (data) => {
              if (!data || data.length === 0) {
                $("#mailchimp-status").html(i18next.t("Not Subscribed"));
                return;
              }
              for (const emailData of data) {
                let textVal = "";
                const lists = emailData["list"] || [];
                for (const list of lists) {
                  const listName = window.CRM.escapeHtml(list["name"] || "");
                  const listStatus = window.CRM.escapeHtml(String(list["status"] || ""));
                  const listOpenRate = list["stats"]?.["avg_open_rate"] || 0;
                  if (list["status"] !== 404) {
                    textVal += `${listName} (${listStatus}) - ${(listOpenRate * 100).toFixed(2)}% ${i18next.t("open rate")}`;
                  }
                }
                if (textVal === "") {
                  textVal = i18next.t("Not Subscribed");
                }
                $("#mailchimp-status").text(textVal);
              }
            },
            error: () => {
              $("#mailchimp-status").html('<span class="text-muted">' + i18next.t("Unable to load") + "</span>");
            },
          });
        }
      },
    });

    // Handle pledge/payment deletion via API
    $(document).on("click", ".pledge-delete-btn", function () {
      const groupKey = $(this).data("group-key");
      if (!confirm(i18next.t("Are you sure you want to permanently delete this pledge record?"))) {
        return;
      }
      fetch(window.CRM.root + "/api/payments/" + encodeURIComponent(groupKey), {
        method: "DELETE",
      })
        .then((res) => {
          if (res.ok) {
            window.CRM.notify("Deleted successfully", "success");
            setTimeout(() => location.reload(), 800);
          } else {
            window.CRM.notify("Delete failed", "danger");
          }
        })
        .catch(() => {
          window.CRM.notify("Network error, please try again", "danger");
        });
    });
  }
}

// Wait for locales to load before initializing
$(document).ready(() => {
  window.CRM.onLocalesReady(initializeFamilyView);
});
