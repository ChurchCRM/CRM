$(document).ready(function () {
    $("#user-listing-table").DataTable(window.CRM.plugin.dataTable);

    $(".setting-tip").click(function () {
        bootbox.alert({
            message: $(this).data("tip"),
            backdrop: true,
            className: "setting-tip-box",
        });
    });

    $("#aDisallowedPasswords").select2({
        tags: true,
        tokenSeparators: [",", " "],
        data: ["asfasf", "asdfasdf"],
    });
});

function deleteUser(userId, userName) {
    bootbox.confirm({
        title: i18next.t("User Delete Confirmation"),
        message:
            '<p style="color: red">' +
            i18next.t("Please confirm removal of user status from") +
            ": <b>" +
            userName +
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
            userName +
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
            userName +
            "</b></p>",
        callback: function (result) {
            if (result) {
                window.CRM.AdminAPIRequest({
                    path: "user/" + userId + "/password/reset",
                    method: "POST",
                }).done(function (data) {
                    window.CRM.notify(i18next.t('Password reset for') + " " + userName, {
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
            userName +
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
