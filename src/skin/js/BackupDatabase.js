function doBackup(isRemote) {
    var endpointURL = "";
    if (isRemote) {
        endpointURL = "database/backupRemote";
    } else {
        endpointURL = "database/backup";
    }
    var errorflag = 0;
    if ($("input[name=encryptBackup]").is(":checked")) {
        if ($("input[name=pw1]").val() == "") {
            $("#passworderror").html("You must enter a password");
            errorflag = 1;
        }
        if ($("input[name=pw1]").val() != $("input[name=pw2]").val()) {
            $("#passworderror").html("Passwords must match");
            errorflag = 1;
        }
    }
    if (!errorflag) {
        $("#passworderror").html(" ");
        // get the form data
        // there are many ways to get this data using jQuery (you can use the class or id also)
        var formData = {
            BackupType: $("input[name=archiveType]:checked").val(),
            EncryptBackup: $("input[name=encryptBackup]").is(":checked"),
            BackupPassword: $("input[name=pw1]").val(),
        };
        $("#backupstatus").css("color", "orange");
        $("#backupstatus").html("Backup Running, Please wait.");
        console.log(formData);

        //process the form
        window.CRM.APIRequest({
            method: "POST",
            path: endpointURL,
            data: JSON.stringify(formData),
        })
            .done(function (data) {
                var downloadButton =
                    '<button class="btn btn-primary" id="downloadbutton" role="button" onclick="javascript:downloadbutton(\'' +
                    data.BackupDownloadFileName +
                    "')\"><i class='fa fa-download'></i>  " +
                    data.BackupDownloadFileName +
                    "</button>";
                $("#backupstatus").css("color", "green");
                if (isRemote) {
                    $("#backupstatus").html(
                        "Backup Generated and copied to remote server",
                    );
                } else {
                    $("#backupstatus").html(
                        "Backup Complete, Ready for Download.",
                    );
                    $("#resultFiles").html(downloadButton);
                }
            })
            .fail(function () {
                $("#backupstatus").css("color", "red");
                $("#backupstatus").html("Backup Error.");
            });
    }
}

$("#doBackup").click(function (event) {
    event.preventDefault();
    doBackup(0);
});

$("#doRemoteBackup").click(function (event) {
    event.preventDefault();
    doBackup(1);
});

function downloadbutton(filename) {
    window.location = window.CRM.root + "/api/database/download/" + filename;
    $("#backupstatus").css("color", "green");
    $("#backupstatus").html("Backup Downloaded, Copy on server removed");
    $("#downloadbutton").attr("disabled", "true");
}
