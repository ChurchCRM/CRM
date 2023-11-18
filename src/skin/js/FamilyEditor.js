$(document).ready(function () {
    $.ajax({
        type: "GET",
        url: window.CRM.root + "/api/public/data/countries",
    }).done(function (data) {
        let familyCountry = $("#Country");
        $.each(data, function (idx, country) {
            let selected = false;
            if (familyCountry.data("user-selected") == "") {
                selected = familyCountry.data("system-default") == country.name;
            } else if (
                familyCountry.data("user-selected") == country.name ||
                familyCountry.data("user-selected") == country.code
            ) {
                selected = true;
            }
            familyCountry.append(
                new Option(country.name, country.code, selected, selected),
            );
        });
        familyCountry.change();
    });

    $("#Country").change(function () {
        $.ajax({
            type: "GET",
            url:
                window.CRM.root +
                "/api/public/data/countries/" +
                this.value.toLowerCase() +
                "/states",
        }).done(function (data) {
            let stateSelect = $("#State");
            if (Object.keys(data).length > 0) {
                stateSelect.empty();
                $.each(data, function (code, name) {
                    let selected = false;
                    if (stateSelect.data("user-selected") == "") {
                        selected = stateSelect.data("system-default") == name;
                    } else if (
                        stateSelect.data("user-selected") == name ||
                        stateSelect.data("user-selected") == code
                    ) {
                        selected = true;
                    }
                    stateSelect.append(
                        new Option(name, code, selected, selected),
                    );
                });
                stateSelect.change();
                $("#stateInputDiv").addClass("hidden");
                $("#StateTextbox").val("");
                $("#stateType").val("dropDown");
                $("#stateOptionDiv").removeClass("hidden");
            } else {
                $("#stateInputDiv").removeClass("hidden");
                $("#stateOptionDiv").addClass("hidden");
                $("#stateType").val("input");
            }
        });
    });

    $("[data-mask]").inputmask();
    $("#Country").select2();
    $("#State").select2();
});
