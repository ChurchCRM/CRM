$(function () {
    if (!window.CRM.currentActive) {
        $("#family-deactivated").removeClass("d-none");
    }

    window.CRM.APIRequest({
        path: `family/${window.CRM.currentFamily}/nav`,
    }).then(function (data) {
        if (data?.PreFamilyId) {
            $("#lastFamily").attr(
                "href",
                `${window.CRM.root}/v2/family/${data.PreFamilyId}`,
            );
        } else {
            $("#lastFamily").addClass("hidden");
        }

        if (data?.NextFamilyId) {
            $("#nextFamily").attr(
                "href",
                `${window.CRM.root}/v2/family/${data.NextFamilyId}`,
            );
        } else {
            $("#nextFamily").addClass("hidden");
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
                    let {
                        id: propId,
                        name: propName,
                        value: propVal,
                        allowEdit,
                        allowDelete,
                    } = prop;
                    selectedFamilyProperties.push(propId);

                    let editIcon = allowEdit
                        ? `<a href="${window.CRM.root}/PropertyAssign.php?FamilyID=${window.CRM.currentFamily}&PropertyID=${propId}"><button type="button" class="btn btn-xs btn-primary"><i class="fa fa-pen"></i></button></a>`
                        : "";
                    let deleteIcon = allowDelete
                        ? `<div class="btn btn-xs btn-danger delete-property" data-property-id="${propId}" data-property-name="${propName}"><i class="fa fa-trash"></i></div>`
                        : "";

                    $("#family-property-table").append(
                        `<tr><td>${deleteIcon} ${editIcon}</td><td>${propName}</td><td>${propVal}</td></tr>`,
                    );
                });

                $(".delete-property").on("click", deleteProperty);
            }
        });
    });

    $("#add-family-property").on("click", function () {
        let inputOptions = masterFamilyProperties
            .filter(
                (masterProp) =>
                    !selectedFamilyProperties.includes(masterProp.ProId),
            )
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

        bootbox.confirm({
            title: i18next.t("Family Property Unassignment"),
            message: `${i18next.t("Do you want to remove")} ${propName} ${i18next.t("property")}`,
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
                        '<a class="btn btn-default" href="' +
                        window.CRM.root +
                        "/PledgeEditor.php?GroupKey=" +
                        row.GroupKey +
                        "&amp;linkBack=v2/family/" +
                        window.CRM.currentFamily +
                        '"><i class="fas fa-pen bg-info"></i></a>'
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
                        '<a class="btn btn-default" href="' +
                        window.CRM.root +
                        "/PledgeDelete.php?GroupKey=" +
                        row.GroupKey +
                        "&amp;linkBack=v2/family/" +
                        window.CRM.currentFamily +
                        '"><i class="fa fa-trash bg-red"></i></a>'
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
            bootbox.alert({
                title: i18next.t("Verification URL"),
                message: "<a href='" + data.url + "'>" + data.url + "</a>",
            });
        });
    });

    $("#verifyDownloadPDF").on("click", function () {
        window.open(
            `${window.CRM.root}/Reports/ConfirmReport.php?familyId=${window.CRM.currentFamily}`,
            "_blank",
        );
        $("#confirm-verify").modal("hide");
    });

    $("#AddFamilyToCart").on("click", function () {
        window.CRM.cart.addFamily($(this).data("familyid"));
    });

    // Photos
    $("#deletePhoto").on("click", function () {
        $.ajax({
            type: "DELETE",
            url: `${window.CRM.root}/api/family/${window.CRM.currentFamily}/photo`,
            encode: true,
            dataType: "json",
        }).then(function () {
            location.reload();
        });
    });

    $("#view-larger-image-btn").on("click", function () {
        bootbox.alert({
            title: i18next.t("Family Photo"),
            message:
                '<img class="img-rounded img-responsive center-block" src="' +
                `${window.CRM.root}/api/family/${window.CRM.currentFamily}/photo` +
                '"/>',
            backdrop: true,
        });
    });

    $("#activateDeactivate").on("click", function () {
        let popupTitle = window.CRM.currentActive
            ? i18next.t("Confirm Deactivation")
            : i18next.t("Confirm Activation");
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
                        path: `families/${window.CRM.currentFamily}/activate/${!window.CRM.currentActive}`,
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
        updateUserSetting(
            "finance.show.pledges",
            $(this).prop("checked") ? "true" : "false",
        );
    });

    $("#ShowPayments").on("change", function () {
        updateUserSetting(
            "finance.show.payments",
            $(this).prop("checked") ? "true" : "false",
        );
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

    if (window.CRM.plugin.mailchimp) {
        window.CRM.APIRequest({
            type: "GET",
            path: `mailchimp/family/${window.CRM.currentFamily}`,
            dataType: "json",
        }).then(function (data) {
            for (let emailData of data) {
                let htmlVal = "";
                let emailMD5 = emailData["emailMD5"];
                for (let list of emailData["list"]) {
                    let {
                        name: listName,
                        status: listStatus,
                        stats: { avg_open_rate: listOpenRate },
                    } = list;
                    if (listStatus !== 404) {
                        htmlVal += `${listName} (${listStatus}) - ${listOpenRate * 100}% ${i18next.t("open rate")}`;
                    }
                }
                if (htmlVal === "") {
                    htmlVal = i18next.t("Not Subscribed");
                }
                $(`#${emailMD5}`).html(htmlVal);
            }
        });
    }
});
