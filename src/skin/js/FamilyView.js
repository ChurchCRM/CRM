$(document).ready(function () {

    if (!window.CRM.currentActive) {
        $("#family-deactivated").removeClass("hide");
    }

    $("#pledge-payment-table").DataTable(window.CRM.plugin.dataTable);


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

});
