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
                window.CRM.APIRequest({
                    method: "DELETE",
                    path: "user/" + userId + "/",
                }).done(function () {
                    window.location.href = window.CRM.root + "/UserList.php";
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
                $.ajax({
                    method: "POST",
                    url:
                        window.CRM.root +
                        "/api/user/" +
                        userId +
                        "/login/reset",
                    dataType: "json",
                    encode: true,
                }).done(function (data) {
                    if (data.status === "success")
                        window.location.href =
                            window.CRM.root + "/UserList.php";
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
                $.ajax({
                    method: "POST",
                    url:
                        window.CRM.root +
                        "/api/user/" +
                        userId +
                        "/password/reset",
                    dataType: "json",
                    encode: true,
                }).done(function (data) {
                    if (data.status === "success")
                        showGlobalMessage(
                            '<?= gettext("Password reset for") ?> ' + userName,
                            "success",
                        );
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
                $.ajax({
                    method: "POST",
                    url:
                        window.CRM.root +
                        "/api/user/" +
                        userId +
                        "/disableTwoFactor",
                }).done(function (data) {
                    window.location.href = window.CRM.root + "/UserList.php";
                });
            }
        },
    });
}
