function skipCheck() {
    $("#prerequisites-war").hide();
    window.CRM.prerequisitesStatus = true;
}

window.CRM.checkIntegrity = function () {
    window.CRM.renderPrerequisite({Name: "ChurchCRM File Integrity Check",WikiLink:"",Satisfied:"pending"});
    $.ajax({
        url: window.CRM.root + "/setup/SystemIntegrityCheck",
        method: "GET"
    }).done(function (data) {
        if (data == "success") {
            window.CRM.renderPrerequisite({Name: "ChurchCRM File Integrity Check",WikiLink:"",Satisfied:true});
            $("#prerequisites-war").hide();
            window.CRM.prerequisitesStatus = true;
        }
        else {
            window.CRM.renderPrerequisite({Name: "ChurchCRM File Integrity Check",WikiLink:"",Satisfied:false});
        }

    }).fail(function () {
        window.CRM.renderPrerequisite({Name: "ChurchCRM File Integrity Check",WikiLink:"",Satisfied:false});
    });
};

window.CRM.checkPrerequisites = function () {
    $.ajax({
        url: window.CRM.root + "/setup/SystemPrerequisiteCheck",
        method: "GET",
        contentType: "application/json"
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
            class: 'text-blue',
            html: '&check;'
        };
    }
    else if (prerequisite.Satisfied === "pending") {
        td = {
            class: 'text-orange',
            html: '<i class="fa fa-spinner fa-spin"></i>'
        };
    }
    else if (prerequisite.Satisfied === false) {
        td = {
            class: 'text-red',
            html: '&#x2717;'
        };
    }
    var id = prerequisite.Name.replace(/[^A-z0-9]/g, '');
    window.CRM.prerequisites[id] = prerequisite.Satisfied;
    var domElement = "#" + id;
    var prerequisite = $("<tr>", {id: id}).append(
        $("<td>", {text: prerequisite.Name})).append(
        $("<td>", td));

    if ($(domElement).length != 0) {
        $(domElement).replaceWith(prerequisite);
    }
    else {
        $("#prerequisites").append(prerequisite);
    }

};


$("document").ready(function () {
    window.CRM.setupWizardForm = $("#setup-form");

    window.CRM.setupWizardValidator = window.CRM.setupWizardForm.validate({
        rules: {
            DB_PASSWORD2: {
                equalTo: "#DB_PASSWORD"
            },

        }
    });

    window.CRM.setupWizardForm.change( function() {
        console.log("form changed");
        if (window.CRM.setupWizardValidator.form())
        {
            console.log("form is valid - checking db");
            validateDatabaseConnection(window.CRM.setupWizardForm);
        }
        
    });

    window.CRM.setupWizardForm.children("div").steps({
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

            if (currentIndex == 3) {
                return window.CRM.databaseConfigurationStatus;
            }

            window.CRM.setupWizardForm.validate().settings.ignore = ":disabled,:hidden";
            return window.CRM.setupWizardForm.valid();
        },
        onFinishing: function (event, currentIndex)
        {
            window.CRM.setupWizardForm.validate().settings.ignore = ":disabled";
            return window.CRM.setupWizardForm.valid() && window.CRM.databaseConfigurationStatus;
        },
        onFinished: function (event, currentIndex)
        {
            submitSetupData(window.CRM.setupWizardForm);
        }
    });



    window.CRM.checkIntegrity();
    window.CRM.checkPrerequisites();
});

function validateDatabaseConnection(form) {
    var formArray = form.serializeArray();
    var json = {};

    jQuery.each(formArray, function() {
       json[this.name] = this.value || '';
    });

    console.log("Submitting data");
    console.log(json);
    $("#database-war").css("visibility","visible");
    $("#database-war").addClass("callout-info");
    $("#database-war").removeClass("callout-danger");
    $("#database-war").removeClass("callout-success");
    $("#database-war").text("Checking Database Connection Details");
    $.ajax({
        url: window.CRM.root + "/setup/DatabasePrerequisiteCheck",
        method: "POST",
        data: JSON.stringify(json),
        contentType: "application/json",
        success : function (data, status, xmlHttpReq) {
            if (data.status == "failure") {
                $("#database-war").removeClass("callout-info");
                $("#database-war").addClass("callout-danger");
                $("#database-war").text(data.message);
                window.CRM.databaseConfigurationStatus = false;
            }
            else if (data.status == "warning") {
              $("#database-war").removeClass("callout-info");
              $("#database-war").addClass("callout-warning");
              $("#database-war").text(data.message);
              window.CRM.databaseConfigurationStatus = false;
            }
            else if (data.status == "success") {
                $("#database-war").removeClass("callout-info");
                $("#database-war").addClass("callout-success");
                $("#database-war").text("Database Check Success");
                window.CRM.databaseConfigurationStatus = true;
            }
        },
        error: function (jqHxr, data, status) {
          $("#database-war").removeClass("callout-info");
          $("#database-war").addClass("callout-warning");
          $("#database-war").text(data);
          window.CRM.databaseConfigurationStatus = false;
        }

    });
}

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
