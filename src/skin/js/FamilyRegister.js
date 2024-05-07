$(document).ready(function () {
    let registerWizard = $("#registration-form");

    registerWizard.validate({
        errorPlacement: function errorPlacement(error, element) {
            element.after(error);
        },
    });

    registerWizard.children("div").steps({
        headerTag: "h2",
        bodyTag: "section",
        transitionEffect: "slideLeft",

        labels: {
            finish: i18next.t("Register"),
        },

        onCanceled: function (event) {
            window.location.href = window.CRM.root + "/";
        },

        onStepChanging: function (event, currentIndex, newIndex) {
            if (currentIndex > newIndex) {
                return true;
            }

            if (newIndex === 1) {
                let familyCount = $("#familyCount").val();
                for (let i = 1; i <= 8; i++) {
                    let boxName = "#memberBox" + i;
                    if (i <= familyCount) {
                        $(boxName).show();
                        $("#memberLastName-" + i).val($("#familyName").val());
                    } else {
                        $(boxName).hide();
                    }
                }
            }

            if (newIndex === 2) {
                let familyCount = $("#familyCount").val();
                for (let i = 1; i <= familyCount; i++) {
                    if (
                        $("#memberFirstName-" + i).val() === "" ||
                        $("#memberLastName-" + i).val() === ""
                    ) {
                        return false;
                    }
                }
            }
            registerWizard.validate().settings.ignore = ":disabled,:hidden";
            return registerWizard.valid();
        },
        onStepChanged: function (event, currentIndex, priorIndex) {
            if (currentIndex === 2) {
                let family = buildFamilyObject();
                $("#displayFamilyName").text(family.Name);

                let familyAddress =
                    family.Address1 +
                    ", " +
                    family.City +
                    ", " +
                    family.State +
                    " " +
                    family.Zip +
                    " " +
                    family.Country;
                $("#displayFamilyAddress").text(familyAddress);
                $("#displayFamilyPhone").text(family.HomePhone);

                let familyCount = $("#familyCount").val();
                for (let i = parseInt(familyCount) + 1; i <= 8; i++) {
                    $("#displayFamilyPerson" + i).hide();
                }

                let num = 1;
                family.people.forEach(function (person) {
                    $("#displayFamilyPersonFName" + num).text(person.firstName);
                    $("#displayFamilyPersonLName" + num).text(person.lastName);
                    $("#displayFamilyPersonEmail" + num).text(person.email);
                    $("#displayFamilyPersonPhone" + num).text(
                        person.phoneNumber,
                    );
                    $("#displayFamilyPersonBDay" + num).text(person.birthday);
                    num++;
                });
            }
        },
        onFinishing: function (event, currentIndex) {
            registerWizard.validate().settings.ignore = ":disabled";
            return registerWizard.valid();
        },
        onFinished: function (event, currentIndex) {
            $.ajax({
                url: window.CRM.root + "/api/public/register/family",
                type: "POST",
                dataType: "json",
                contentType: "application/json",
                data: JSON.stringify(buildFamilyObject()),
            })
                .done(function (data) {
                    bootbox.dialog({
                        title: i18next.t("Registration Complete"),
                        message: i18next.t(
                            "Thank you for registering your family",
                        ),
                        buttons: {
                            new: {
                                label: i18next.t("Register another family!"),
                                className: "btn-default",
                                callback: function () {
                                    window.location.href =
                                        window.CRM.root + "/external/register/";
                                },
                            },
                            done: {
                                label: i18next.t("Done, show me the homepage!"),
                                className: "btn-info",
                                callback: function () {
                                    window.location.href =
                                        window.CRM.churchWebSite;
                                },
                            },
                        },
                    });
                })
                .fail(function (data) {
                    bootbox.alert({
                        title: i18next.t(
                            "Sorry, we are unable to process your request at this point in time.",
                        ),
                        message: data.responseText,
                    });
                });
        },
    });

    $.ajax({
        type: "GET",
        url: window.CRM.root + "/api/public/data/countries",
    }).done(function (data) {
        let familyCountry = $("#familyCountry");
        $.each(data, function (idx, country) {
            let selected =
                familyCountry.data("system-default") === country.name;
            familyCountry.append(
                new Option(country.name, country.code, selected, selected),
            );
        });
        familyCountry.change();
    });

    $("#familyCountry").select2();
    $("#familyCountry").change(function () {
        $.ajax({
            type: "GET",
            url:
                window.CRM.root +
                "/api/public/data/countries/" +
                this.value.toLowerCase() +
                "/states",
        }).done(function (data) {
            if (Object.keys(data).length > 0) {
                $("#familyStateSelect").empty();
                $.each(data, function (code, name) {
                    let selected =
                        $("#familyStateSelect").data("system-default") == code;
                    $("#familyStateSelect").append(
                        new Option(name, code, selected, selected),
                    );
                });
                $("#familyStateSelect").change();
                $("#familyStateInput").addClass("hidden");
                $("#familyStateSelect").removeClass("hidden");
            } else {
                $("#familyStateInput").removeClass("hidden");
                $("#familyStateSelect").addClass("hidden");
            }
        });
    });

    $(".inputDatePicker").datepicker({
        autoclose: true,
    });

    $("[data-mask]").inputmask();

    function buildFamilyObject() {
        let family = {};
        family["Name"] = $("#familyName").val();
        family["Address1"] = $("#familyAddress1").val();
        family["City"] = $("#familyCity").val();
        if ($("#familyStateSelect").hasClass("hidden")) {
            family["State"] = $("#familyStateInput").val();
        } else {
            family["State"] = $("#familyStateSelect").val();
        }
        family["Country"] = $("#familyCountry").val();
        family["Zip"] = $("#familyZip").val();
        family["HomePhone"] = $("#familyHomePhone").val();
        family["people"] = [];

        let familyCount = $("#familyCount").val();
        for (let i = 1; i <= familyCount; i++) {
            let person = {
                role: $("#memberRole-" + i).val(),
                gender: $("#memberGender-" + i).val(),
                firstName: $("#memberFirstName-" + i).val(),
                lastName: $("#memberLastName-" + i).val(),
                email: $("#memberEmail-" + i).val(),
                birthday: $("#memberBirthday-" + i).val(),
                hideAge: $("#memberHideAge-" + i).prop("checked"),
            };

            let phoneType = $("#memberPhoneType-" + i).val();
            let phoneNumber = $("#memberPhone-" + i).val();
            if (phoneType === "mobile") {
                person["cellPhone"] = phoneNumber;
            } else if (phoneType === "work") {
                person["workPhone"] = phoneNumber;
            } else if (phoneType === "home") {
                person["homePhone"] = phoneNumber;
            }
            family["people"].push(person);
        }
        return family;
    }
});
