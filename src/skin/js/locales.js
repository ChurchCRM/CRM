$("document").ready(function() {
    window.CRM.APIRequest({
        path: 'system/locale/' + window.CRM.locale,
    }).done(function (data) {
        $(".flag-icon").addClass("flag-icon-" + data.countryFlagCode);
        $("#translationInfo").html(data.name + " [" + window.CRM.locale + "]");
        if (data.countryFlagCode != "us") {
            $("#translationPer").html(data.poPerComplete + "%");
            $("#localePer").removeClass("hidden");
        }
    });
});
