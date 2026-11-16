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
  UpdatePasswordStrength(NewPassword1);
}

// Advisory only: the score never blocks submission and the server
// policy is unchanged. A password shorter than the minimum length
// cannot score above Weak, whatever characters it contains.
function PasswordStrength(password) {
  var points = 0;
  if (password.length >= 8) {
    points += 1;
  }
  if (password.length >= 12) {
    points += 1;
  }
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
    points += 1;
  }
  if (/[0-9]/.test(password)) {
    points += 1;
  }
  if (/[^A-Za-z0-9]/.test(password)) {
    points += 1;
  }
  if (password.length < 8) {
    points = Math.min(points, 1);
  }
  var level = password === "" ? 0 : Math.min(Math.max(points, 1), 4);
  var levels = [
    { label: "", hint: "", barClass: "" },
    {
      label: i18next.t("Weak"),
      hint: i18next.t("Add more characters, and mix upper and lower case, numbers, and symbols."),
      barClass: "bg-danger",
    },
    {
      label: i18next.t("Fair"),
      hint: i18next.t("Add more characters or mix in numbers and symbols."),
      barClass: "bg-warning",
    },
    {
      label: i18next.t("Good"),
      hint: i18next.t("Add a few more characters or a symbol."),
      barClass: "bg-info",
    },
    {
      label: i18next.t("Strong"),
      hint: i18next.t("This password looks strong."),
      barClass: "bg-success",
    },
  ];
  return {
    level: level,
    label: levels[level].label,
    hint: levels[level].hint,
    barClass: levels[level].barClass,
    percent: level * 25,
  };
}

function UpdatePasswordStrength(password) {
  var strength = PasswordStrength(password);
  var container = $("#PasswordStrengthContainer");
  if (password === "") {
    container.prop("hidden", true);
    $("#PasswordStrengthBar").css("width", "0%").attr("aria-valuenow", 0);
    $("#PasswordStrengthLabel").text("");
    $("#PasswordStrengthHint").text("");
    return;
  }
  container.prop("hidden", false);
  $("#PasswordStrengthBar")
    .removeClass("bg-danger bg-warning bg-info bg-success")
    .addClass(strength.barClass)
    .css("width", `${strength.percent}%`)
    .attr("aria-valuenow", strength.percent);
  $("#PasswordStrengthLabel").text(strength.label);
  $("#PasswordStrengthHint").text(strength.hint);
}
