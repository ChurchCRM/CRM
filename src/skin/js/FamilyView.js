$(document).ready(function () {
    if (!window.CRM.currentActive) {
        $("#family-deactivated").removeClass("d-none");
    }

    $.ajax({
        url:
            window.CRM.root +
            "/api/family/" +
            window.CRM.currentFamily +
            "/nav",
        encode: true,
        dataType: "json",
    }).done(function (data) {
        if (data["PreFamilyId"]) {
            $("#lastFamily").attr(
                "href",
                window.CRM.root + "/v2/family/" + data["PreFamilyId"],
            );
        } else {
            $("#lastFamily").addClass("hidden");
        }
        if (data["NextFamilyId"]) {
            $("#nextFamily").attr(
                "href",
                window.CRM.root + "/v2/family/" + data["NextFamilyId"],
            );
        } else {
            $("#nextFamily").addClass("hidden");
        }
    });

    let masterFamilyProperties = {};
    let selectedFamilyProperties = [];
    window.CRM.APIRequest({
        path: "people/properties/family",
    }).done(function (data) {
        masterFamilyProperties = data;

        window.CRM.APIRequest({
            path: "people/properties/family/" + window.CRM.currentFamily,
        }).done(function (data) {
            if (masterFamilyProperties.length > data.length) {
                $("#add-family-property").removeClass("hidden");
            }

            $("#family-property-loading").addClass("hidden");
            if (data.length === 0) {
                $("#family-property-no-data").removeClass("hidden");
            } else {
                $("#family-property-table").removeClass("hidden");
                $.each(data, function (key, prop) {
                    let propId = prop.id;
                    let editIcon = "";
                    let deleteIcon = "";
                    let propName = prop.name;
                    let propVal = prop.value;
                    selectedFamilyProperties.push(propId);
                    if (prop.allowEdit) {
                        editIcon =
                            "<a href='" +
                            window.CRM.root +
                            "/PropertyAssign.php?FamilyID=" +
                            window.CRM.currentFamily +
                            "&PropertyID=" +
                            propId +
                            "'><button type='button' class='btn btn-xs btn-primary'><i class='fa fa-pen'></i></button></a>";
                    }
                    if (prop.allowDelete) {
                        deleteIcon =
                            "<div class='btn btn-xs btn-danger delete-property' data-property-id='" +
                            propId +
                            "' data-property-name='" +
                            propName +
                            "'><i class='fa fa-trash'></i></div>";
                    }

                    $("#family-property-table tr:last").after(
                        "<tr><td>" +
                            deleteIcon +
                            " " +
                            editIcon +
                            "</td><td>" +
                            propName +
                            "</td><td>" +
                            propVal +
                            "</td></tr>",
                    );
                });
                $(".delete-property").click(function () {
                    let propId = $(this).attr("data-property-id");
                    bootbox.confirm({
                        title: i18next.t("Family Property Unassignment"),
                        message:
                            i18next.t("Do you want to remove") +
                            " " +
                            $(this).attr("data-property-name") +
                            " " +
                            "property",
                        locale: window.CRM.locale,
                        callback: function (result) {
                            if (result) {
                                window.CRM.APIRequest({
                                    path:
                                        "people/properties/family/" +
                                        window.CRM.currentFamily +
                                        "/" +
                                        propId,
                                    method: "DELETE",
                                }).done(function (data) {
                                    location.reload();
                                });
                            }
                        },
                    });
                });
            }
        });
    });

    $("#add-family-property").click(function () {
        let inputOptions = [];
        $.each(masterFamilyProperties, function (index, masterProp) {
            if ($.inArray(masterProp.ProId, selectedFamilyProperties) == -1) {
                inputOptions.push({
                    text: masterProp.ProName,
                    value: masterProp.ProId,
                });
            }
        });
        bootbox.prompt({
            title: i18next.t("Assign a New Property"),
            locale: window.CRM.locale,
            inputType: "select",
            inputOptions: inputOptions,
            callback: function (result) {
                window.CRM.APIRequest({
                    path:
                        "people/properties/family/" +
                        window.CRM.currentFamily +
                        "/" +
                        result,
                    method: "POST",
                }).done(function (data) {
                    location.reload();
                });
            },
        });
    });

    var dataTableConfig = {
        ajax: {
            url:
                window.CRM.root +
                "/api/payments/family/" +
                window.CRM.currentFamily +
                "/list",
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

    $("#onlineVerify").click(function () {
        window.CRM.APIRequest({
            method: "POST",
            path: "family/" + window.CRM.currentFamily + "/verify",
        }).done(function () {
            $("#confirm-verify").modal("hide");
            showGlobalMessage(i18next.t("Verification email sent"), "success");
        });
    });

    $("#verifyNow").click(function () {
        window.CRM.APIRequest({
            method: "POST",
            path: "family/" + window.CRM.currentFamily + "/verify/now",
        }).done(function () {
            $("#confirm-verify").modal("hide");
            showGlobalMessage(i18next.t("Verification recorded"), "success");
        });
    });

    $("#verifyURL").click(function () {
        window.CRM.APIRequest({
            path: "family/" + window.CRM.currentFamily + "/verify/url",
        }).done(function (data) {
            $("#confirm-verify").modal("hide");
            bootbox.alert({
                title: i18next.t("Verification URL"),
                message: "<a href='" + data.url + "'>" + data.url + "</a>",
            });
        });
    });

    $("#verifyDownloadPDF").click(function () {
        window.open(
            window.CRM.root +
                "/Reports/ConfirmReport.php?familyId=" +
                window.CRM.currentFamily,
            "_blank",
        );
        $("#confirm-verify").modal("hide");
    });

    $("#AddFamilyToCart").click(function () {
        window.CRM.cart.addFamily($(this).data("familyid"));
    });

    // Photos

    $("#deletePhoto").click(function () {
        $.ajax({
            type: "DELETE",
            url:
                window.CRM.root +
                "/api/family/" +
                window.CRM.currentFamily +
                "/photo",
            encode: true,
            dataType: "json",
        }).done(function (data) {
            location.reload();
        });
    });

    $("#view-larger-image-btn").click(function () {
        bootbox.alert({
            title: i18next.t("Family Photo"),
            message:
                '<img class="img-rounded img-responsive center-block" src="' +
                window.CRM.root +
                "/api/family/" +
                window.CRM.currentFamily +
                '/photo" />',
            backdrop: true,
        });
    });

    $("#activateDeactivate").click(function () {
        popupTitle =
            window.CRM.currentActive == true
                ? i18next.t("Confirm Deactivation")
                : i18next.t("Confirm Activation");
        if (window.CRM.currentActive == true) {
            popupMessage =
                i18next.t("Please confirm deactivation of family") +
                ": " +
                window.CRM.currentFamilyName;
        } else {
            popupMessage =
                i18next.t("Please confirm activation of family") +
                ": " +
                window.CRM.currentFamilyName;
        }

        bootbox.confirm({
            title: popupTitle,
            message: '<p style="color: red">' + popupMessage + "</p>",
            callback: function (result) {
                if (result) {
                    window.CRM.APIRequest({
                        method: "POST",
                        path:
                            "families/" +
                            window.CRM.currentFamily +
                            "/activate/" +
                            !window.CRM.currentActive,
                    }).done(function (data) {
                        if (data.success == true) {
                            window.location.href =
                                window.CRM.root +
                                "/v2/family/" +
                                window.CRM.currentFamily;
                        }
                    });
                }
            },
        });
    });

    $("#ShowPledges").click(function () {
        updateUserSetting(
            "finance.show.pledges",
            $("#ShowPledges").prop("checked") ? "true" : "false",
        );
    });

    $("#ShowPayments").click(function () {
        updateUserSetting(
            "finance.show.payments",
            $("#ShowPayments").prop("checked") ? "true" : "false",
        );
    });

    $("#ShowSinceDate").change(function () {
        updateUserSetting("finance.show.since", $("#ShowSinceDate").val());
    });

    function updateUserSetting(setting, value) {
        window.CRM.APIRequest({
            method: "POST",
            path: "user/" + window.CRM.userId + "/setting/" + setting,
            dataType: "json",
            data: JSON.stringify({ value: value }),
        }).done(function () {
            //TODO NOT WORKING $("#pledge-payment-table").DataTable().ajax.reload();
            window.location.reload();
        });
    }

    if (window.CRM.plugin.mailchimp) {
        $.ajax({
            type: "GET",
            dataType: "json",
            url:
                window.CRM.root +
                "/api/mailchimp/family/" +
                window.CRM.currentFamily,
            success: function (data, status, xmlHttpReq) {
                for (emailData of data) {
                    let htmlVal = "";
                    let eamilMD5 = emailData["emailMD5"];
                    for (list of emailData["list"]) {
                        let listName = list["name"];
                        let listStatus = list["status"];
                        if (listStatus != 404) {
                            let listOpenRate =
                                list["stats"]["avg_open_rate"] * 100;
                            htmlVal =
                                htmlVal +
                                listName +
                                " (" +
                                listStatus +
                                ") - " +
                                listOpenRate +
                                "% " +
                                i18next.t("open rate");
                        }
                    }
                    if (htmlVal === "") {
                        htmlVal = i18next.t("Not Subscribed ");
                    }
                    $("#" + eamilMD5).html(htmlVal);
                }
            },
        });
    }
});
