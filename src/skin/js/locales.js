$("document").ready(function() {
    window.CRM.APIRequest({
        path: 'system/locale/' + window.CRM.locale,
    }).done(function (data) {
        $(".flag-icon").addClass("flag-icon-" + data.countryFlagCode);
        $("#translationPer").html(data.poPerComplete + "%");
        $("#translationInfo").html(data.name + " ["+ window.CRM.locale +"]");
    });
});
