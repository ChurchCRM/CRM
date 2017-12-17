function skipCheck() {
    $("#prerequisites-war").hide();
    window.CRM.prerequisitesStatus = true;
}

window.CRM.checkIntegrity = function () {
    window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "pending");
    $.ajax({
        url: window.CRM.root + "/setup/SystemIntegrityCheck",
        method: "GET"
    }).done(function (data) {
        if (data == "success") {
            window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "pass");
            $("#prerequisites-war").hide();
            window.CRM.prerequisitesStatus = true;
        }
        else {
            window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "fail");
        }

    }).fail(function () {
        window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "fail");
    });
};

window.CRM.checkPrerequisites = function () {
    $.ajax({
        url: window.CRM.root + "/setup/SystemPrerequisiteCheck",
        method: "GET",
        contentType: "application/json"
    }).done(function (data) {
        $.each(data, function (key, value) {
            if (value) {
                status = "pass";
            }
            else {
                status = "fail";
            }
            window.CRM.renderPrerequisite(key, status);
        });
    });
};
window.CRM.renderPrerequisite = function (name, status) {
    var td = {};
    if (status == "pass") {
        td = {
            class: 'text-blue',
            html: '&check;'
        };
    }
    else if (status == "pending") {
        td = {
            class: 'text-orange',
            html: '<i class="fa fa-spinner fa-spin"></i>'
        };
    }
    else if (status == "fail") {
        td = {
            class: 'text-red',
            html: '&#x2717;'
        };
    }
    var id = name.replace(/[^A-z0-9]/g, '');
    window.CRM.prerequisites[id] = status;
    var domElement = "#" + id;
    var prerequisite = $("<tr>", {id: id}).append(
        $("<td>", {text: name})).append(
        $("<td>", td));

    if ($(domElement).length != 0) {
        $(domElement).replaceWith(prerequisite);
    }
    else {
        $("#prerequisites").append(prerequisite);
    }

};


$("document").ready(function () {
    var setupWizard = $("#setup-form");

    setupWizard.validate({
        rules: {
            DB_PASSWORD2: {
                equalTo: "#DB_PASSWORD"
            }
        }
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

            if (currentIndex == 0) {
                return window.CRM.prerequisitesStatus;
            }

            setupWizard.validate().settings.ignore = ":disabled,:hidden";
            return setupWizard.valid();
        },
        onFinishing: function (event, currentIndex)
        {
            setupWizard.validate().settings.ignore = ":disabled";
            return setupWizard.valid();
        },
        onFinished: function (event, currentIndex)
        {
            submitSetupData(setupWizard);
        }
    });

    window.CRM.checkIntegrity();
    window.CRM.checkPrerequisites();
});

function submitSetupData(form) {
    var formArray = form.serializeArray();
    var json = {};

    jQuery.each(formArray, function() {
       json[this.name] = this.value || '';
    });

    $.ajax({
        url: window.CRM.root + "/setup/",
        method: "POST",
        data: JSON.stringify(json),
        contentType: "application/json",
        success: function (data, status, xmlHttpReq) {
            location.replace( window.CRM.root + "/");
        }
    });

}
