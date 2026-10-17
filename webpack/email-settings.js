/**
 * Email Settings page: SMTP presets and the SMTP Authentication toggle.
 * Turning authentication off disables the username and password and clears them.
 */

function init() {
  const form = document.getElementById("email-settings-form");
  if (!form) {
    return;
  }

  const authToggle = form.querySelector("#bSMTPAuth");
  const credentials = [form.querySelector("#sSMTPUser"), form.querySelector("#sSMTPPass")];

  function syncCredentials() {
    credentials.forEach((field) => {
      field.disabled = !authToggle.checked;
      if (!authToggle.checked) {
        field.value = "";
      }
    });
  }

  authToggle.addEventListener("change", syncCredentials);

  form.querySelectorAll("[data-email-preset]").forEach((button) => {
    button.addEventListener("click", () => {
      form.querySelector("#sSMTPHost").value = button.dataset.smtpHost;
      form.querySelector("#iSMTPTimeout").value = button.dataset.smtpTimeout;
      form.querySelector("#sPHPMailerSMTPSecure").value = button.dataset.smtpSecure;
      form.querySelector("#bPHPMailerAutoTLS").checked = button.dataset.smtpAutoTls === "1";
      authToggle.checked = button.dataset.smtpAuth === "1";
      syncCredentials();
    });
  });
}

document.addEventListener("DOMContentLoaded", init);
