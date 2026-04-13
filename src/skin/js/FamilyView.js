function initializeFamilyView() {
  // Print button
  $("#printFamily").on("click", function () {
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
  }).then(function (data) {
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

  let masterFamilyProperties = {};
  let selectedFamilyProperties = [];
  let assignedFamilyValues = {};
  window.CRM.APIRequest({
    path: "people/properties/family",
  }).then(function (masterData) {
    masterFamilyProperties = masterData;

    window.CRM.APIRequest({
      path: `people/properties/family/${window.CRM.currentFamily}`,
    }).then(function (data) {
      $("#family-property-loading").hide();

      if (data.length === 0) {
        $("#family-property-no-data").show();
      } else {
        $("#family-property-list").show();
        $.each(data, function (key, prop) {
          let { id: propId, name: propName, value: propVal, allowEdit, allowDelete } = prop;
          selectedFamilyProperties.push(propId);
          assignedFamilyValues[propId] = propVal || "";

          // GHSA-8r36-fvxj-26qv: Escape property values to prevent XSS
          let safePropName = window.CRM.escapeHtml(propName || "");
          let safePropVal = window.CRM.escapeHtml(propVal || "");

          let deleteBtn = allowDelete
            ? `<button class="btn btn-sm btn-ghost-danger delete-property" data-property-id="${propId}" data-property-name="${safePropName}" title="${i18next.t("Remove")}"><i class="fa-solid fa-trash"></i></button>`
            : "";

          $("#family-property-list").append(
            `<div class="list-group-item px-0 d-flex align-items-center">` +
              `<div class="me-auto">` +
              `<strong>${safePropName}</strong>` +
              (safePropVal ? `<small class="text-muted d-block">${safePropVal}</small>` : "") +
              `</div>` +
              deleteBtn +
              `</div>`,
          );
        });

        $(".delete-property").on("click", deleteProperty);
      }

      // Populate the inline assign form. Mirrors the Person view UX:
      // show every definition (assigned entries marked with "(assigned)")
      // so the user can edit values in place, and expose the property's
      // prompt + current value as data attributes for the prompt box.
      let $select = $("#input-family-properties");
      if ($select.length && Array.isArray(masterFamilyProperties) && masterFamilyProperties.length > 0) {
        masterFamilyProperties.forEach(function (masterProp) {
          let propId = masterProp.ProId;
          let propName = masterProp.ProName || "";
          let isAssigned = selectedFamilyProperties.includes(propId);
          let label = isAssigned ? `${propName} (${i18next.t("assigned")})` : propName;
          let $option = $("<option></option>").attr("value", propId).text(label);
          if (masterProp.ProPrompt) {
            $option.attr("data-pro_prompt", masterProp.ProPrompt);
            $option.attr("data-pro_value", assignedFamilyValues[propId] || "");
          }
          $select.append($option);
        });
        $("#family-property-assign-wrapper").show();
      }
    });
  });

  // Mirror the Person view prompt-box behavior: when a property with a
  // prompt is chosen, reveal a text area pre-filled with any existing
  // value so it can be edited in place.
  $("#input-family-properties").on("change", function () {
    let $promptBox = $("#family-prompt-box");
    $promptBox.removeClass("mb-3").html("");
    let $selected = $("#input-family-properties :selected");
    let promptText = $selected.data("pro_prompt");
    let promptValue = $selected.data("pro_value");
    if (promptText) {
      $promptBox
        .addClass("mb-3")
        .append($("<label></label>").text(promptText))
        .append($('<textarea rows="3" class="form-control" name="PropertyValue"></textarea>').val(promptValue || ""));
    }
  });

  $("#assign-family-property-btn").on("click", function () {
    let propertyId = "";
    let value = "";
    let dataToSend = $("#assign-family-property-form").serializeArray();
    $.each(dataToSend, function (key, field) {
      if (field.name === "PropertyId") {
        propertyId = field.value;
      } else if (field.name === "PropertyValue") {
        value = field.value;
      }
    });
    if (!propertyId) {
      return;
    }
    window.CRM.APIRequest({
      method: "POST",
      path: `people/properties/family/${window.CRM.currentFamily}/${propertyId}`,
      data: JSON.stringify({ value: value }),
    }).then(function () {
      location.reload();
    });
  });

  function deleteProperty() {
    let propId = $(this).attr("data-property-id");
    let propName = $(this).attr("data-property-name");
    // GHSA-8r36-fvxj-26qv: Escape property name in bootbox message
    let safePropName = window.CRM.escapeHtml(propName || "");

    bootbox.confirm({
      title: i18next.t("Family Property Unassignment"),
      message: `${i18next.t("Do you want to remove")} ${safePropName} ${i18next.t("property")}`,
      locale: window.CRM.locale,
      callback: function (result) {
        if (result) {
          window.CRM.APIRequest({
            path: `people/properties/family/${window.CRM.currentFamily}/${propId}`,
            method: "DELETE",
          }).then(function () {
            location.reload();
          });
        }
      },
    });
  }

  // Pledges & Payments table — init after ensuring both types are returned by API
  if ($("#pledge-payment-v2-table").length) {
    let dataTableConfig = {
      ajax: {
        url: `${window.CRM.root}/api/payments/family/${window.CRM.currentFamily}/list`,
        dataSrc: "data",
      },
      columns: [
        {
          title: i18next.t("Type"),
          data: "PledgeOrPayment",
          render: function (data) {
            let color = data === "Pledge" ? "blue" : "green";
            let icon = data === "Pledge" ? "fa-hand-holding-dollar" : "fa-money-bill-wave";
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
          render: function (data) {
            return "$" + parseFloat(data).toFixed(2);
          },
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
          render: function (data, type, row) {
            let linkBack = "people/family/" + window.CRM.currentFamily;
            let editUrl = window.CRM.root + "/PledgeEditor.php?GroupKey=" + row.GroupKey + "&amp;linkBack=" + linkBack;
            let deleteUrl =
              window.CRM.root + "/PledgeDelete.php?GroupKey=" + row.GroupKey + "&amp;linkBack=" + linkBack;
            return (
              '<div class="dropdown">' +
              '<button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>' +
              '<div class="dropdown-menu dropdown-menu-end">' +
              '<a class="dropdown-item" href="' +
              editUrl +
              '"><i class="fa-solid fa-pen me-2"></i>' +
              i18next.t("Edit") +
              "</a>" +
              '<a class="dropdown-item text-danger" href="' +
              deleteUrl +
              '"><i class="fa-solid fa-trash-can me-2"></i>' +
              i18next.t("Delete") +
              "</a>" +
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
      .catch(function () {}) // ignore errors
      .then(function () {
        let pledgeTable = $("#pledge-payment-v2-table").DataTable(dataTableConfig);

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
        let defaultFY = $(".pledge-fy-pill.active").data("fy") || "";
        if (defaultFY) {
          pledgeTable.column(4).search(defaultFY).draw();
        }
      });
  }

  $("#onlineVerify").on("click", function () {
    window.CRM.APIRequest({
      method: "POST",
      path: "family/" + window.CRM.currentFamily + "/verify",
    }).then(function () {
      $("#confirm-verify").modal("hide");
      showGlobalMessage(i18next.t("Verification email sent"), "success");
    });
  });

  $("#verifyNow").on("click", function () {
    window.CRM.APIRequest({
      method: "POST",
      path: "family/" + window.CRM.currentFamily + "/verify/now",
    }).then(function () {
      $("#confirm-verify").modal("hide");
      showGlobalMessage(i18next.t("Verification recorded"), "success");
    });
  });

  $("#verifyURL").on("click", function () {
    window.CRM.APIRequest({
      path: "family/" + window.CRM.currentFamily + "/verify/url",
    }).then(function (data) {
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
      $("#copyVerifyUrlBtn").on("click", function () {
        const urlInput = document.getElementById("verifyUrlInput");
        navigator.clipboard
          .writeText(urlInput.value)
          .then(function () {
            const btn = document.getElementById("copyVerifyUrlBtn");
            const originalHtml = btn.innerHTML;

            btn.innerHTML = '<i class="fa-solid fa-check me-2"></i>' + i18next.t("Copied!");
            btn.classList.add("btn-success");
            btn.classList.remove("btn-info");

            setTimeout(function () {
              btn.innerHTML = originalHtml;
              btn.classList.remove("btn-success");
              btn.classList.add("btn-info");
            }, 2000);
          })
          .catch(function (err) {
            console.error("Failed to copy:", err);
            window.CRM.notify(i18next.t("Failed to copy URL"), { type: "error" });
          });
      });

      // Cleanup when modal is closed
      $("#verifyUrlModal").on("hidden.bs.modal", function () {
        $("#verifyUrlModal").remove();
      });
    });
  });

  $("#verifyDownloadPDF").on("click", function () {
    window.open(`${window.CRM.root}/Reports/ConfirmReport.php?familyId=${window.CRM.currentFamily}`, "_blank");
    $("#confirm-verify").modal("hide");
  });

  $("#verifyEmailPDF").on("click", function () {
    $("#confirm-verify").modal("hide");
    window.location.href = `${window.CRM.root}/Reports/ConfirmReportEmail.php?familyId=${window.CRM.currentFamily}`;
  });

  // Photos
  $("#deletePhoto").on("click", function () {
    window.CRM.deletePhoto("family", window.CRM.currentFamily);
  });

  $("#view-larger-image-btn").on("click", function (e) {
    e.preventDefault();
    window.CRM.showPhotoLightbox("family", window.CRM.currentFamily);
  });

  // .view-family-photo / .view-person-photo click handlers are registered
  // globally in avatar-loader.ts

  $("#activateDeactivate").on("click", function () {
    let popupTitle = window.CRM.currentActive ? i18next.t("Confirm Deactivation") : i18next.t("Confirm Activation");
    let popupMessage = window.CRM.currentActive
      ? `${i18next.t("Please confirm deactivation of family")}: ${window.CRM.currentFamilyName}`
      : `${i18next.t("Please confirm activation of family")}: ${window.CRM.currentFamilyName}`;

    bootbox.confirm({
      title: popupTitle,
      message: `<p class="text-danger">${popupMessage}</p>`,
      callback: function (result) {
        if (result) {
          window.CRM.APIRequest({
            method: "POST",
            path: `family/${window.CRM.currentFamily}/activate/${!window.CRM.currentActive}`,
          }).then(function (data) {
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
    let val = $(this).val();
    window.CRM.APIRequest({
      method: "POST",
      path: `user/${window.CRM.userId}/setting/finance.show.since`,
      dataType: "json",
      data: JSON.stringify({ value: val }),
    }).then(function () {
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
      success: function (pluginData) {
        if (pluginData.success && pluginData.isActive && pluginData.isConfigured) {
          // Show the MailChimp status container
          $("#mailchimp-status-container").removeClass("d-none");

          // Load the family's MailChimp data
          $.ajax({
            type: "GET",
            dataType: "json",
            url: window.CRM.root + "/plugins/mailchimp/api/family/" + window.CRM.currentFamily,
            success: function (data) {
              if (!data || data.length === 0) {
                $("#mailchimp-status").html(i18next.t("Not Subscribed"));
                return;
              }
              for (let emailData of data) {
                let textVal = "";
                let lists = emailData["list"] || [];
                for (let list of lists) {
                  let listName = window.CRM.escapeHtml(list["name"] || "");
                  let listStatus = window.CRM.escapeHtml(String(list["status"] || ""));
                  let listOpenRate = list["stats"]?.["avg_open_rate"] || 0;
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
            error: function () {
              $("#mailchimp-status").html('<span class="text-muted">' + i18next.t("Unable to load") + "</span>");
            },
          });
        }
      },
    });
  }
}

// Wait for locales to load before initializing
$(document).ready(function () {
  window.CRM.onLocalesReady(initializeFamilyView);
});
