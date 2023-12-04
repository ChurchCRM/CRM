$("#regenApiKey").click(function () {
    $.ajax({
        type: "POST",
        url:
            window.CRM.root +
            "/api/user/" +
            window.CRM.viewUserId +
            "/apikey/regen",
    }).done(function (data, textStatus, xhr) {
        if (xhr.status === 200) {
            $("#apiKey").val(data.apiKey);
        } else {
            showGlobalMessage(
                i18next.t("Failed generate a new API Key"),
                "danger",
            );
        }
    });
});

$(".user-setting-checkbox").click(function () {
    let thisCheckbox = $(this);
    let setting = thisCheckbox.data("setting-name");
    let cssClass = thisCheckbox.data("layout");
    let targetCSS = thisCheckbox.data("css");
    let enabled = thisCheckbox.prop("checked") ? cssClass : "";
    let data = JSON.stringify({ value: enabled });

    window.CRM.APIRequest({
        method: "POST",
        path: "user/" + window.CRM.userId + "/setting/" + setting,
        dataType: "json",
        data: data,
    }).done(function () {
        if (enabled !== "") {
            $(targetCSS).addClass(cssClass);
        } else {
            $(targetCSS).removeClass(cssClass);
        }
    });
});

$(".user-setting-select").change(function () {
    let thisCheckbox = $(this);
    let optionSelected = $(this).find("option:selected");
    let setting = thisCheckbox.data("setting-name");
    let data = JSON.stringify({ value: optionSelected.val() });

    window.CRM.APIRequest({
        method: "POST",
        path: "user/" + window.CRM.userId + "/setting/" + setting,
        dataType: "json",
        data: data,
    });
});

$(document).ready(function () {
    let localeOptions = $("#user-locale-setting");
    $.ajax({
        url: window.CRM.root + "/locale/locales.json",
        dataType: "json",
        type: "GET",
        success: function (data) {
            $.each(data, function (localeName, localeData) {
                let selected = false;
                if (window.CRM.systemLocale === localeData.locale) {
                    selected = true;
                }
                let newOption = new Option(
                    localeName,
                    localeData.locale,
                    false,
                    selected,
                );
                localeOptions.append(newOption);
            });
            localeOptions.change();
        },
    });

    $(".user-setting-checkbox").each(function () {
        let thisCheckbox = $(this);
        let setting = thisCheckbox.data("setting-name");
        window.CRM.APIRequest({
            method: "GET",
            path: "user/" + window.CRM.userId + "/setting/" + setting,
        }).done(function (data) {
            if (data.value !== "") {
                thisCheckbox.prop("checked", true);
            }
        });
    });

    $(".user-setting-select").each(function () {
        let thisSelect = $(this);
        let setting = thisSelect.data("setting-name");
        window.CRM.APIRequest({
            method: "GET",
            path: "user/" + window.CRM.userId + "/setting/" + setting,
        }).done(function (data) {
            if (data.value !== "") {
                thisSelect.val(data.value).change();
            }
        });
    });
});
