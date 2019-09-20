$(document).ready(function () {

    if (!window.CRM.currentActive) {
        $("#family-deactivated").removeClass("hide");
    }

    $.ajax({
        url: window.CRM.root + "/api/family/" + window.CRM.currentFamily + "/nav",
        encode: true,
        dataType: 'json'
    }).done(function (data) {
        if (data["PreFamilyId"]) {
            $("#lastFamily").attr("href", window.CRM.root + "/v2/family/" + data["PreFamilyId"] + "/view");
        } else {
            $("#lastFamily").addClass("hidden");
        }
        if (data["NextFamilyId"]) {
            $("#nextFamily").attr("href", window.CRM.root + "/v2/family/" + data["NextFamilyId"] + "/view");
        } else {
            $("#nextFamily").addClass("hidden");
        }
    });

    var dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/payments/family/"+ window.CRM.currentFamily +"/list",
            dataSrc: "data"
        },
        columns: [
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Edit'),
                data: 'GroupKey',
                render: function (data, type, row) {
                    return '<a class="btn btn-default" href="'+window.CRM.root+'/PledgeEditor.php?GroupKey='+ row.GroupKey + '&amp;linkBack=FamilyView.php?FamilyID='+window.CRM.currentFamily+'"><i class="fa fa-pencil bg-info"></i></a>';
                },
                searchable: false
            },
            {
                width: '15px',
                sortable: false,
                title: i18next.t('Delete'),
                data: 'GroupKey',
                render: function (data, type, row) {
                    return '<a class="btn btn-default" href="'+window.CRM.root+'/PledgeDelete.php?GroupKey='+ row.GroupKey + '&amp;linkBack=FamilyView.php?FamilyID='+window.CRM.currentFamily+'"><i class="fa fa-trash bg-red"></i></a>';
                },
                searchable: false
            },
            {
                title: i18next.t('Pledge or Payment'),
                data: 'PledgeOrPayment'
            },
            {
                title: i18next.t('Fund'),
                data: 'Fund'
            },
            {
                title: i18next.t('Fiscal Year'),
                data: 'FormattedFY'
            },
            {
                title: i18next.t('Date'),
                type: 'date',
                data: 'Date'
            },
            {
                title: i18next.t('Amount'),
                type: 'num',
                data: 'Amount'
            },
            {
                title: i18next.t('NonDeductible'),
                type: 'num',
                data: 'Nondeductible'
            },
            {
                title: i18next.t('Schedule'),
                data: 'Schedule'
            },
            {
                title: i18next.t('Method'),
                data: 'Method'
            },
            {
                title: i18next.t('Comment'),
                data: 'Comment'
            },
            {
                title: i18next.t('Date Updated'),
                type: 'date',
                data: 'DateLastEdited'
            },
            {
                title: i18next.t('Updated By'),
                data: 'EditedBy'
            }
        ],
        order: [[5, "asc"]]
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    $("#pledge-payment-v2-table").DataTable(dataTableConfig);

    $("#onlineVerify").click(function () {
        window.CRM.APIRequest({
            method : 'POST',
            path: 'family/' + window.CRM.currentFamily + '/verify',
        }).done(function () {
            $('#confirm-verify').modal('hide');
            showGlobalMessage(i18next.t("Verification email sent"), "success")
        });
    });

    $("#verifyNow").click(function () {
        window.CRM.APIRequest({
            method: 'POST',
            path: 'family/' + window.CRM.currentFamily + '/verify/now',
        }).done(function () {
            $('#confirm-verify').modal('hide');
            showGlobalMessage(i18next.t("Verification recorded"), "success")
        });
    });

    $("#verifyURL").click(function () {
        window.CRM.APIRequest({
            path: 'family/' + window.CRM.currentFamily + '/verify/url',
        }).done(function (data) {
            $('#confirm-verify').modal('hide');
            bootbox.alert({
                title: i18next.t("Verification URL"),
                message: "<a href='"+data.url+"'>"+data.url+"</a>"
            });
        });
    });


    $("#verifyDownloadPDF").click(function () {
        window.open(window.CRM.root + '/Reports/ConfirmReport.php?familyId=' + window.CRM.currentFamily, '_blank');
        $('#confirm-verify').modal('hide');
    });

    $("#AddFamilyToCart").click(function () {
        window.CRM.cart.addFamily($(this).data("familyid"));
    });


    // Photos

    $("#deletePhoto").click(function () {
        $.ajax({
            type: "POST",
            url: window.CRM.root + "/api/family/" + window.CRM.currentFamily + "/photo",
            encode: true,
            dataType: 'json',
            data: {
                "_METHOD": "DELETE"
            }
        }).done(function (data) {
            location.reload();
        });
    });

    $("#view-larger-image-btn").click(function () {
        bootbox.alert({
            title: i18next.t('Family Photo'),
            message: '<img class="img-rounded img-responsive center-block" src="' + window.CRM.root + '/api/family/' + window.CRM.currentFamily + '/photo" />',
            backdrop: true
        });
    });


    $("#activateDeactivate").click(function () {
        popupTitle = (window.CRM.currentActive == true ? i18next.t('Confirm Deactivation') : i18next.t("Confirm Activation" ));
        if (window.CRM.currentActive == true) {
            popupMessage = i18next.t("Please confirm deactivation of family") +  ': '  + window.CRM.currentFamilyName;
        }
        else {
            popupMessage = i18next.t('Please confirm activation of family') + ': ' + window.CRM.currentFamilyName;
        }

        bootbox.confirm({
            title: popupTitle,
            message: '<p style="color: red">' + popupMessage + '</p>',
            callback: function (result) {
                if (result) {
                    window.CRM.APIRequest({
                        method: "POST",
                        path: "families/" + window.CRM.currentFamily + "/activate/" + !window.CRM.currentActive
                    }).done(function (data) {
                        if (data.success == true)
                            if (window.CRM.currentFamilyView == 1) {
                                window.location.href = window.CRM.root + "/FamilyView.php?FamilyID=" + window.CRM.currentFamily;
                            } else {
                                window.location.href = window.CRM.root + "/v2/family/" + window.CRM.currentFamily + "/view";
                            }
                    });
                }
            }
        });
    });

    $("#ShowPledges").click(function () {
        updateUserFinanceData();
    });

    $("#ShowPayments").click(function () {
        updateUserFinanceData();
    });

    $("#ShowSinceDate").change(function () {
        updateUserFinanceData();
    });

    function updateUserFinanceData(){
        var finData = {
            "pledges": $("#ShowPledges").prop("checked") ? "true" : "false",
            "payments": $("#ShowPayments").prop("checked") ? "true" : "false",
            "since": $("#ShowSinceDate").val()
        };

        window.CRM.APIRequest({
            method: "POST",
            path: "/user/current/settings/show/finance",
            dataType: 'json',
            data: JSON.stringify(finData)
        }).done(function () {
            $("#pledge-payment-table").DataTable().ajax.reload();
        });
    }
});
