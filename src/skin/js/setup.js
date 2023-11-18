function skipCheck() {
    $("#prerequisites-war").hide();
    window.CRM.prerequisitesStatus = true;
}

window.CRM.checkIntegrity = function () {
    window.CRM.renderPrerequisite({
        Name: "ChurchCRM File Integrity Check",
        WikiLink: "",
        Satisfied: "pending",
    });
    $.ajax({
        url: window.CRM.root + "/setup/SystemIntegrityCheck",
        method: "GET",
    })
        .done(function (data) {
            if (data === "success") {
                window.CRM.renderPrerequisite({
                    Name: "ChurchCRM File Integrity Check",
                    WikiLink: "",
                    Satisfied: true,
                });
                $("#prerequisites-war").hide();
                window.CRM.prerequisitesStatus = true;
            } else {
                window.CRM.renderPrerequisite({
                    Name: "ChurchCRM File Integrity Check",
                    WikiLink: "",
                    Satisfied: false,
                });
            }
        })
        .fail(function () {
            window.CRM.renderPrerequisite({
                Name: "ChurchCRM File Integrity Check",
                WikiLink: "",
                Satisfied: false,
            });
        });
};

window.CRM.checkPrerequisites = function () {
    $.ajax({
        url: window.CRM.root + "/setup/SystemPrerequisiteCheck",
        method: "GET",
        contentType: "application/json",
    }).done(function (data) {
        $.each(data, function (index, prerequisite) {
            window.CRM.renderPrerequisite(prerequisite);
        });
    });
};
window.CRM.renderPrerequisite = function (prerequisite) {
    var td = {};
    if (prerequisite.Satisfied === true) {
        td = {
            class: "text-blue",
            html: "&check;",
        };
    } else if (prerequisite.Satisfied === "pending") {
        td = {
            class: "text-orange",
            html: '<i class="fa fa-spinner fa-spin"></i>',
        };
    } else if (prerequisite.Satisfied === false) {
        td = {
            class: "text-red",
            html: "&#x2717;",
        };
    }
    var id = prerequisite.Name.replace(/[^A-z0-9]/g, "");
    window.CRM.prerequisites[id] = prerequisite.Satisfied;
    var domElement = "#" + id;
    var prerequisite = $("<tr>", { id: id })
        .append($("<td>", { text: prerequisite.Name }))
        .append($("<td>", td));

    if ($(domElement).length !== 0) {
        $(domElement).replaceWith(prerequisite);
    } else {
        $("#prerequisites").append(prerequisite);
    }
};

$("document").ready(function () {
    var setupWizard = $("#setup-form");

    setupWizard.validate({
        rules: {
            DB_PASSWORD2: {
                equalTo: "#DB_PASSWORD",
            },
        },
    });

    setupWizard.children("div").steps({
        headerTag: "h2",
        bodyTag: "section",
        transitionEffect: "slideLeft",
        stepsOrientation: "vertical",
        onStepChanging: function (event, currentIndex, newIndex) {
            if (currentIndex > newIndex) {
                return true;
            }

            if (currentIndex === 0) {
                return window.CRM.prerequisitesStatus;
            }

            setupWizard.validate().settings.ignore = ":disabled,:hidden";
            return setupWizard.valid();
        },
        onFinishing: function (event, currentIndex) {
            setupWizard.validate().settings.ignore = ":disabled";
            return setupWizard.valid();
        },
        onFinished: function (event, currentIndex) {
            submitSetupData(setupWizard);
        },
    });

    window.CRM.checkIntegrity();
    window.CRM.checkPrerequisites();
});

function submitSetupData(form) {
    var formArray = form.serializeArray();
    var json = {};

    jQuery.each(formArray, function () {
        json[this.name] = this.value || "";
    });

    $.ajax({
        url: window.CRM.root + "/setup/",
        method: "POST",
        data: JSON.stringify(json),
        contentType: "application/json",
        success: function (data, status, xmlHttpReq) {
            location.replace(window.CRM.root + "/");
        },
    });
}
