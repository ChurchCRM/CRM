$(document).ready(function () {

    if (!window.CRM.currentActive) {
        $("#family-deactivated").removeClass("hide");
    }

    $("#pledge-payment-table").DataTable(window.CRM.plugin.dataTable);


    $("#onlineVerify").click(function () {
        $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/families/' + window.CRM.currentFamily + '/verify'
        })
            .done(function (data, textStatus, xhr) {
                $('#confirm-verify').modal('hide');
                if (xhr.status == 200) {
                    showGlobalMessage(i18next.t("Verification email sent"), "success")
                } else {
                    showGlobalMessage(i18next.t("Failed to send verification email"), "danger")
                }
            });
    });

    $("#verifyNow").click(function () {
        $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/families/verify/' + window.CRM.currentFamily + '/now'
        })
            .done(function (data, textStatus, xhr) {
                $('#confirm-verify').modal('hide');
                if (xhr.status == 200) {
                    location.reload();
                } else {
                    showGlobalMessage(i18next.t("Failed to add verification"), "danger")
                }
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
            url: window.CRM.root + "/api/families/" + window.CRM.currentFamily + "/photo",
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
            message: '<img class="img-rounded img-responsive center-block" src="' + window.CRM.root + '/api/families/' + window.CRM.currentFamily + '/photo" />',
            backdrop: true
        });
    });

});
