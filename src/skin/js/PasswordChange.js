$(document).ready(function () {
    $("#NewPassword1").keyup(function (event) {
        ValidateNewPassword();
    });
    $("#NewPassword2").keyup(function (event) {
        ValidateNewPassword();
    });
});

function ValidateNewPassword() {
    var StatusText = "";
    var NewPassword1 = $("#NewPassword1").val();
    var NewPassword2 = $("#NewPassword2").val();
    if (NewPassword1 !== "" && NewPassword2 !== "") {
        if (NewPassword1 !== NewPassword2) {
            StatusText = i18next.t(
                "You must enter the same password in both boxes",
            );
        }
    }
    $("#NewPasswordError").text(StatusText);
}
