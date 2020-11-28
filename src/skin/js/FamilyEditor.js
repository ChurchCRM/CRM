$(document).ready(function () {


    $.ajax({
        type: "GET",
        url: window.CRM.root + "/api/public/data/countries"
    }).done(function (data) {
        let familyCountry = $("#Country");
        $.each(data, function( idx, country ) {
            let selected = familyCountry.data("system-default") == country.name;
            familyCountry.append(new Option(country.name, country.code, selected, selected));
        });
        familyCountry.change();
    });

    $("#Country").change(function () {
        $.ajax({
            type: "GET",
            url: window.CRM.root + "/api/public/data/countries/"+ this.value.toLowerCase() +"/states"
        }).done(function (data) {
            let stateSelect = $("#State");
            if (Object.keys(data).length > 0) {
                stateSelect.empty();
                $.each(data, function (code, name) {
                    let selected = stateSelect.data("system-default") == code;
                    stateSelect.append(new Option(name, code, selected, selected));
                });
                stateSelect.change();
                $("#stateInputDiv").addClass("hidden");
                $("#stateOptionDiv").removeClass("hidden");
            } else {
                $("#stateInputDiv").removeClass("hidden");
                $("#stateOptionDiv").addClass("hidden");
            }
        });
    })

    $(function() {
        $("[data-mask]").inputmask();
    });


});
