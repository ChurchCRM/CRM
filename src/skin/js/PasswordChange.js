$(document).ready(() => {
  $("#NewPassword1, #NewPassword2").on("input", ValidateNewPassword);
  $("#NewPassword1")
    .closest("form")
    .on("submit", (event) => {
      if ($("#NewPassword1").val() !== $("#NewPassword2").val()) {
        event.preventDefault();
        $("#NewPasswordError").text(PasswordMismatchText());
        $("#NewPassword2").trigger("focus");
      }
    });
});

function PasswordMismatchText() {
  return i18next.t("You must enter the same password in both boxes");
}

function ValidateNewPassword() {
  var StatusText = "";
  var NewPassword1 = $("#NewPassword1").val();
  var NewPassword2 = $("#NewPassword2").val();
  if (NewPassword1 !== "" && NewPassword2 !== "") {
    if (NewPassword1 !== NewPassword2) {
      StatusText = PasswordMismatchText();
    }
  }
  $("#NewPasswordError").text(StatusText);
}
