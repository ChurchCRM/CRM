$(document).ready(() => {
  $("#user-listing-table").DataTable(window.CRM.plugin.dataTable);

  $(".setting-tip").click(function () {
    window.CRM.alertAction("", $(this).data("tip"), { backdrop: true, className: "setting-tip-box" });
  });

  var disallowedEl = document.getElementById("aDisallowedPasswords");
  if (disallowedEl && !disallowedEl.tomselect) {
    new TomSelect(disallowedEl, {
      create: true,
      persist: false,
      delimiter: ",",
      createOnBlur: true,
      plugins: ["remove_button"],
      options: [
        { value: "asfasf", text: "asfasf" },
        { value: "asdfasdf", text: "asdfasdf" },
      ],
      items: ["asfasf", "asdfasdf"],
    });
  }

  // Delegated handlers for user action menu items.
  // Data is read from safe data-* attributes set by PHP (escapeAttribute), so
  // there is no inline JS string that can be broken out of by a crafted name.
  // Defense-in-depth: userName is also wrapped in window.CRM.escapeHtml()
  // before being placed into bootbox HTML messages (notify/bootbox render via
  // innerHTML), matching the pattern already used in GroupList.js,
  // sundayschool-actions.js, and event-checkin.js.
  // Fixes GHSA-4qpj-3hw2-52g8 (Stored XSS via Person Name, CWE-79/116, CVSS 8.7).

  $(document).on("click", ".js-reset-user-password", function (e) {
    e.preventDefault();
    resetUserPassword($(this).data("user_id"), $(this).data("user_name"));
  });

  $(document).on("click", ".js-reset-login-count", function (e) {
    e.preventDefault();
    restUserLoginCount($(this).data("user_id"), $(this).data("user_name"));
  });

  $(document).on("click", ".js-disable-2fa", function (e) {
    e.preventDefault();
    disableUserTwoFactorAuth($(this).data("user_id"), $(this).data("user_name"));
  });

  $(document).on("click", ".js-delete-user", function (e) {
    e.preventDefault();
    deleteUser($(this).data("user_id"), $(this).data("user_name"));
  });

  $(document).on("click", ".js-disable-sign-in", function (e) {
    e.preventDefault();
    disableUserSignIn($(this).data("person_id"), $(this).data("user_name"));
  });

  $(document).on("click", ".js-enable-sign-in", function (e) {
    e.preventDefault();
    enableUserSignIn($(this).data("person_id"), $(this).data("user_name"));
  });
});

function deleteUser(userId, userName) {
  window.CRM.confirmAction(
    i18next.t("User Delete Confirmation"),
    '<p class="text-danger">' +
      i18next.t("Delete the login for") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p><p>" +
      i18next.t("This removes the login only. The person and their notes stay.") +
      '</p><div class="form-check"><input class="form-check-input" type="checkbox" id="delete-user-disable-sign-in" checked>' +
      '<label class="form-check-label" for="delete-user-disable-sign-in">' +
      i18next.t("Also disable sign-in, so they can't use the Member Portal") +
      "</label></div>",
    () => {
      const checkbox = document.getElementById("delete-user-disable-sign-in");
      window.CRM.AdminAPIRequest({
        path: "user/" + userId + "/",
        method: "DELETE",
        data: JSON.stringify({ disableSignIn: checkbox ? checkbox.checked : true }),
      }).done(() => {
        window.location.href = window.CRM.root + "/admin/system/users";
      });
    },
  );
}

function postPersonSignIn(personId, action, body) {
  const table = document.getElementById("user-listing-table");
  return fetch(window.CRM.root + "/admin/api/person/" + personId + "/sign-in/" + action, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      "X-CSRF-Token": table?.dataset.csrfToken || "",
    },
    body: JSON.stringify(body || {}),
  }).then(async (response) => {
    if (response.ok) {
      window.location.href = window.CRM.root + "/admin/system/users";
      return;
    }
    let message = i18next.t("An error occurred.");
    try {
      const data = await response.json();
      message = data.message || message;
    } catch {
      // keep the generic message
    }
    window.CRM.notify(window.CRM.escapeHtml(String(message)), { type: "danger", delay: 6000 });
  });
}

function disableUserSignIn(personId, userName) {
  window.CRM.promptAction(
    i18next.t("Disable sign-in") + ": " + window.CRM.escapeHtml(String(userName || "")),
    (reason) => {
      if (String(reason).trim() === "") {
        window.CRM.notify(i18next.t("Say why sign-in is being disabled."), { type: "warning" });
        return;
      }
      postPersonSignIn(personId, "disable", { reason: String(reason) });
    },
    {
      message:
        "<p>" +
        i18next.t(
          "They can't sign in as staff or to the Member Portal, and their sessions, devices and passkeys are removed.",
        ) +
        "</p><p>" +
        i18next.t("Reason") +
        ":</p>",
      inputType: "textarea",
      confirmLabel: i18next.t("Disable sign-in"),
    },
  );
}

function enableUserSignIn(personId, userName) {
  window.CRM.confirmAction(
    i18next.t("Action Confirmation"),
    "<p>" + i18next.t("Enable sign-in") + ": <b>" + window.CRM.escapeHtml(String(userName || "")) + "</b></p>",
    () => {
      postPersonSignIn(personId, "enable");
    },
    false,
  );
}

function restUserLoginCount(userId, userName) {
  window.CRM.confirmAction(
    i18next.t("Action Confirmation"),
    '<p class="text-danger">' +
      i18next.t("Please confirm reset failed login count") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    () => {
      window.CRM.AdminAPIRequest({
        path: "user/" + userId + "/login/reset",
        method: "POST",
      }).done(() => {
        window.location.href = window.CRM.root + "/admin/system/users";
      });
    },
  );
}

function resetUserPassword(userId, userName) {
  window.CRM.confirmAction(
    i18next.t("Action Confirmation"),
    '<p class="text-danger">' +
      i18next.t("Please confirm the password reset of this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    () => {
      window.CRM.AdminAPIRequest({
        path: "user/" + userId + "/password/reset",
        method: "POST",
      }).done((data) => {
        window.CRM.notify(i18next.t("Password reset for") + " " + window.CRM.escapeHtml(String(userName || "")), {
          type: "success",
        });
      });
    },
  );
}

function disableUserTwoFactorAuth(userId, userName) {
  window.CRM.confirmAction(
    i18next.t("Action Confirmation"),
    '<p class="text-danger">' +
      i18next.t("Please confirm disabling 2 Factor Auth for this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    () => {
      window.CRM.AdminAPIRequest({
        path: "user/" + userId + "/disableTwoFactor",
        method: "POST",
      }).done((data) => {
        window.location.href = window.CRM.root + "/admin/system/users";
      });
    },
  );
}
