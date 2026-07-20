$(document).ready(function () {
  $("#user-listing-table").DataTable(window.CRM.plugin.dataTable);

  $(".setting-tip").click(function () {
    bootbox.alert({
      message: $(this).data("tip"),
      backdrop: true,
      className: "setting-tip-box",
    });
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
  // before being placed into bootbox HTML messages (Notyf/bootbox render via
  // innerHTML), matching the pattern already used in GroupView.js, GroupList.js,
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
});

function deleteUser(userId, userName) {
  bootbox.confirm({
    title: i18next.t("User Delete Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm removal of user status from") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/",
          method: "DELETE",
        }).done(function () {
          window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}

function restUserLoginCount(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm reset failed login count") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/login/reset",
          method: "POST",
        }).done(function (data) {
          if (data.status === "success") window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}

function resetUserPassword(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm the password reset of this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/password/reset",
          method: "POST",
        }).done(function (data) {
          window.CRM.notify(i18next.t("Password reset for") + " " + window.CRM.escapeHtml(String(userName || "")), {
            type: "success",
          });
        });
      }
    },
  });
}

function disableUserTwoFactorAuth(userId, userName) {
  bootbox.confirm({
    title: i18next.t("Action Confirmation"),
    message:
      '<p style="color: red">' +
      i18next.t("Please confirm disabling 2 Factor Auth for this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: function (result) {
      if (result) {
        window.CRM.AdminAPIRequest({
          path: "user/" + userId + "/disableTwoFactor",
          method: "POST",
        }).done(function (data) {
          window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}
