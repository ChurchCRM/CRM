function initializeFamilyView() {
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
            $("#lastFamily").attr("href", `${window.CRM.root}/v2/family/${data.PreFamilyId}`);
        } else {
            $("#lastFamily").addClass("disabled").attr("aria-disabled", "true").removeAttr("href");
        }

        if (data?.NextFamilyId) {
            $("#nextFamily").attr("href", `${window.CRM.root}/v2/family/${data.NextFamilyId}`);
        } else {
            $("#nextFamily").addClass("disabled").attr("aria-disabled", "true").removeAttr("href");
        }
    });

    let masterFamilyProperties = {};
    let selectedFamilyProperties = [];
    window.CRM.APIRequest({
        path: "people/properties/family",
    }).then(function (masterData) {
        masterFamilyProperties = masterData;

        window.CRM.APIRequest({
            path: `people/properties/family/${window.CRM.currentFamily}`,
        }).then(function (data) {
            $("#family-property-loading").hide();

            if (masterFamilyProperties.length > data.length) {
                $("#add-family-property").show();
            }

            if (data.length === 0) {
                $("#family-property-no-data").show();
            } else {
                $("#family-property-table").show();
                $.each(data, function (key, prop) {
                    let { id: propId, name: propName, value: propVal, allowEdit, allowDelete } = prop;
                    selectedFamilyProperties.push(propId);

                    // GHSA-8r36-fvxj-26qv: Escape property values to prevent XSS
                    let safePropName = window.CRM.escapeHtml(propName || "");
                    let safePropVal = window.CRM.escapeHtml(propVal || "");

                    let editIcon = allowEdit
                        ? `<a href="${window.CRM.root}/PropertyAssign.php?FamilyID=${window.CRM.currentFamily}&PropertyID=${propId}"><button type="button" class="btn btn-xs btn-primary"><i class="fa-solid fa-pen"></i></button></a>`
                        : "";
                    let deleteIcon = allowDelete
                        ? `<div class="btn btn-xs btn-danger delete-property" data-property-id="${propId}" data-property-name="${safePropName}"><i class="fa-solid fa-trash"></i></div>`
                        : "";

                    $("#family-property-table").append(
                        `<tr><td>${deleteIcon} ${editIcon}</td><td>${safePropName}</td><td>${safePropVal}</td></tr>`,
                    );
                });

                $(".delete-property").on("click", deleteProperty);
            }
        });
    });

    $("#add-family-property").on("click", function () {
        let inputOptions = masterFamilyProperties
            .filter((masterProp) => !selectedFamilyProperties.includes(masterProp.ProId))
            .map(({ ProName: text, ProId: value }) => ({ text, value }));

        bootbox.prompt({
            title: i18next.t("Assign a New Property"),
            locale: window.CRM.locale,
            inputType: "select",
            inputOptions: inputOptions,
            callback: function (result) {
                if (result) {
                    window.CRM.APIRequest({
                        path: `people/properties/family/${window.CRM.currentFamily}/${result}`,
                        method: "POST",
                    }).then(function () {
                        location.reload();
                    });
                }
            },
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

    let dataTableConfig = {
        ajax: {
            url: `${window.CRM.root}/api/payments/family/${window.CRM.currentFamily}/list`,
            dataSrc: "data",
        },
        columns: [
            {
                width: "15px",
                sortable: false,
                title: i18next.t("Edit"),
                data: "GroupKey",
                render: function (data, type, row) {
                    return (
                        '<a class="btn btn-sm btn-primary" href="' +
                        window.CRM.root +
                        "/PledgeEditor.php?GroupKey=" +
                        row.GroupKey +
                        "&amp;linkBack=v2/family/" +
                        window.CRM.currentFamily +
                        '"><i class="fa-solid fa-pen"></i></a>'
                    );
                },
                searchable: false,
            },
            {
                width: "15px",
                sortable: false,
                title: i18next.t("Delete"),
                data: "GroupKey",
                render: function (data, type, row) {
                    return (
                        '<a class="btn btn-sm btn-danger" href="' +
                        window.CRM.root +
                        "/PledgeDelete.php?GroupKey=" +
                        row.GroupKey +
                        "&amp;linkBack=v2/family/" +
                        window.CRM.currentFamily +
                        '"><i class="fa-solid fa-trash-can"></i></a>'
                    );
                },
                searchable: false,
            },
            {
                title: i18next.t("Pledge or Payment"),
                data: "PledgeOrPayment",
            },
            {
                title: i18next.t("Fund"),
                data: "Fund",
            },
            {
                title: i18next.t("Fiscal Year"),
                data: "FormattedFY",
            },
            {
                title: i18next.t("Date"),
                type: "date",
                data: "Date",
            },
            {
                title: i18next.t("Amount"),
                type: "num",
                data: "Amount",
            },
            {
                title: i18next.t("NonDeductible"),
                type: "num",
                data: "Nondeductible",
            },
            {
                title: i18next.t("Schedule"),
                data: "Schedule",
            },
            {
                title: i18next.t("Method"),
                data: "Method",
            },
            {
                title: i18next.t("Comment"),
                data: "Comment",
            },
            {
                title: i18next.t("Date Updated"),
                type: "date",
                data: "DateLastEdited",
            },
            {
                title: i18next.t("Updated By"),
                data: "EditedBy",
            },
        ],
        order: [[5, "asc"]],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $("#pledge-payment-v2-table").DataTable(dataTableConfig);

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
                                <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="verifyUrlInput" value="${data.url}" readonly>
                                    <button class="btn btn-info" type="button" id="copyVerifyUrlBtn">
                                        <i class="fa-solid fa-copy me-2"></i>${i18next.t("Copy")}
                                    </button>
                                </div>
                                <p class="text-muted small">
                                    <i class="fa-solid fa-info-circle me-2"></i>${i18next.t("Share this URL with family members to verify their information")}
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">${i18next.t("Close")}</button>
                                <a href="${data.url}" target="_blank" class="btn btn-primary">
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

    $("#activateDeactivate").on("click", function () {
        let popupTitle = window.CRM.currentActive ? i18next.t("Confirm Deactivation") : i18next.t("Confirm Activation");
        let popupMessage = window.CRM.currentActive
            ? `${i18next.t("Please confirm deactivation of family")}: ${window.CRM.currentFamilyName}`
            : `${i18next.t("Please confirm activation of family")}: ${window.CRM.currentFamilyName}`;

        bootbox.confirm({
            title: popupTitle,
            message: `<p style="color: red">${popupMessage}</p>`,
            callback: function (result) {
                if (result) {
                    window.CRM.APIRequest({
                        method: "POST",
                        path: `family/${window.CRM.currentFamily}/activate/${!window.CRM.currentActive}`,
                    }).then(function (data) {
                        if (data.success) {
                            window.location.href = `${window.CRM.root}/v2/family/${window.CRM.currentFamily}`;
                        }
                    });
                }
            },
        });
    });

    $("#ShowPledges").on("change", function () {
        updateUserSetting("finance.show.pledges", $(this).prop("checked") ? "true" : "false");
    });

    $("#ShowPayments").on("change", function () {
        updateUserSetting("finance.show.payments", $(this).prop("checked") ? "true" : "false");
    });

    $("#ShowSinceDate").on("change", function () {
        updateUserSetting("finance.show.since", $(this).val());
    });

    function updateUserSetting(setting, value) {
        window.CRM.APIRequest({
            method: "POST",
            path: `user/${window.CRM.userId}/setting/${setting}`,
            dataType: "json",
            data: JSON.stringify({ value: value }),
        }).then(function () {
            //TODO NOT WORKING $("#pledge-payment-table").DataTable().ajax.reload();
            window.location.reload();
        });
    }

    // Check if MailChimp plugin is active via API and load data if so
    // Only check if family has email (mailchimp-status-container is rendered conditionally in PHP)
    if ($("#mailchimp-status-container").length > 0 && window.CRM.familyEmail) {
        $.ajax({
            type: "GET",
            dataType: "json",
            url: window.CRM.root + "/plugins/api/plugins/mailchimp",
            success: function (pluginData) {
                if (pluginData.success && pluginData.data.isActive && pluginData.data.isConfigured) {
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
                                let htmlVal = "";
                                let lists = emailData["list"] || [];
                                for (let list of lists) {
                                    let listName = list["name"];
                                    let listStatus = list["status"];
                                    let listOpenRate = list["stats"]?.["avg_open_rate"] || 0;
                                    if (listStatus !== 404) {
                                        htmlVal += `${listName} (${listStatus}) - ${(listOpenRate * 100).toFixed(2)}% ${i18next.t("open rate")}`;
                                    }
                                }
                                if (htmlVal === "") {
                                    htmlVal = i18next.t("Not Subscribed");
                                }
                                $("#mailchimp-status").html(htmlVal);
                            }
                        },
                        error: function () {
                            $("#mailchimp-status").html(
                                '<span class="text-muted">' + i18next.t("Unable to load") + "</span>",
                            );
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
