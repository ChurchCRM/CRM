$(document).ready(() => {
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

function getAdminUserSecurityCSRFToken() {
  var context = document.getElementById("admin-user-security-context");
  return context ? context.getAttribute("data-csrf-token") || "" : "";
}

function setAdminUserSecurityCSRFToken(token) {
  var context = document.getElementById("admin-user-security-context");
  if (context && typeof token === "string" && token !== "") {
    context.setAttribute("data-csrf-token", token);
  }
}

function handleAdminUserSecurityRequestError(jqXHR, textStatus, errorThrown) {
  if (jqXHR.status === 401) {
    window.location.assign(`${window.CRM.root}/session/begin`);
    return;
  }
  if (jqXHR.status === 403) {
    window.CRM.notify(i18next.t("This page's security token expired. Reload the page and try again."), {
      type: "danger",
      delay: 7000,
    });
    return;
  }
  if (jqXHR.status === 422) {
    window.CRM.notify(i18next.t("The current password was not accepted."), {
      type: "danger",
      delay: 5000,
    });
    return;
  }
  window.CRM.system.handlejQAJAXError(jqXHR, textStatus, errorThrown, false);
}

function reauthenticateAdminSecurityAction() {
  var deferred = window.jQuery.Deferred();

  bootbox.prompt({
    title: i18next.t("Current Password"),
    inputType: "password",
    callback: function (currentPassword) {
      if (currentPassword === null) {
        deferred.reject({ cancelled: true });
        return;
      }

      if (currentPassword === "") {
        window.CRM.notify(`${i18next.t("Current Password")} ${i18next.t("is required")}`, {
          type: "danger",
        });
        return false;
      }

      window.CRM.APIRequest({
        path: "user/current/reauthenticate",
        method: "POST",
        data: JSON.stringify({ currentPassword: currentPassword }),
        headers: { "X-CSRF-Token": getAdminUserSecurityCSRFToken() },
        suppressErrorDialog: true,
        error: function () {},
      })
        .done(function (data) {
          if (data && data.CSRFToken) {
            setAdminUserSecurityCSRFToken(data.CSRFToken);
          }
          deferred.resolve();
        })
        .fail(function (jqXHR, textStatus, errorThrown) {
          handleAdminUserSecurityRequestError(jqXHR, textStatus, errorThrown);
          deferred.reject(jqXHR, textStatus, errorThrown);
        });
    },
  });

  return deferred.promise();
}

function adminUserSecurityRequest(options, requiresRecentAuthentication) {
  var deferred = window.jQuery.Deferred();

  function attempt(allowReauthentication) {
    var requestOptions = window.jQuery.extend(true, {}, options);
    requestOptions.headers = window.jQuery.extend({}, requestOptions.headers, {
      "X-CSRF-Token": getAdminUserSecurityCSRFToken(),
    });
    requestOptions.suppressErrorDialog = true;
    requestOptions.error = function () {};

    window.CRM.AdminAPIRequest(requestOptions)
      .done(function (data, textStatus, jqXHR) {
        deferred.resolve(data, textStatus, jqXHR);
      })
      .fail(function (jqXHR, textStatus, errorThrown) {
        var reauthenticationRequired = jqXHR.status === 428 && jqXHR.responseJSON?.code === "reauthentication_required";
        if (requiresRecentAuthentication && allowReauthentication && reauthenticationRequired) {
          reauthenticateAdminSecurityAction()
            .done(function () {
              attempt(false);
            })
            .fail(function (reason, reauthenticationStatus, reauthenticationError) {
              deferred.reject(reason, reauthenticationStatus, reauthenticationError);
            });
          return;
        }

        handleAdminUserSecurityRequestError(jqXHR, textStatus, errorThrown);
        deferred.reject(jqXHR, textStatus, errorThrown);
      });
  }

  attempt(true);
  return deferred.promise();
}

function deleteUser(userId, userName) {
  bootbox.confirm({
    title: i18next.t("User Delete Confirmation"),
    message:
      '<p class="text-danger">' +
      i18next.t("Please confirm removal of user status from") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: (result) => {
      if (result) {
        adminUserSecurityRequest(
          {
            path: "user/" + userId + "/",
            method: "DELETE",
          },
          true,
        ).done(function () {
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
      '<p class="text-danger">' +
      i18next.t("Please confirm reset failed login count") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: (result) => {
      if (result) {
        adminUserSecurityRequest(
          {
            path: "user/" + userId + "/login/reset",
            method: "POST",
          },
          true,
        ).done(function (data) {
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
      '<p class="text-danger">' +
      i18next.t("Please confirm the password reset of this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: (result) => {
      if (result) {
        adminUserSecurityRequest(
          {
            path: "user/" + userId + "/password/reset",
            method: "POST",
          },
          true,
        ).done(function () {
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
      '<p class="text-danger">' +
      i18next.t("Please confirm disabling 2 Factor Auth for this user") +
      ": <b>" +
      window.CRM.escapeHtml(String(userName || "")) +
      "</b></p>",
    callback: (result) => {
      if (result) {
        adminUserSecurityRequest(
          {
            path: "user/" + userId + "/disableTwoFactor",
            method: "POST",
          },
          true,
        ).done(function () {
          window.location.href = window.CRM.root + "/admin/system/users";
        });
      }
    },
  });
}
