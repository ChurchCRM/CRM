// ── Helper: save a user setting via API ──────────────────────────
function saveUserSetting(settingName, value) {
  return window.CRM.APIRequest({
    method: "POST",
    path: "user/" + window.CRM.userId + "/setting/" + settingName,
    dataType: "json",
    data: JSON.stringify({ value: value }),
  });
}

function getUserSetting(settingName) {
  return window.CRM.APIRequest({
    method: "GET",
    path: "user/" + window.CRM.userId + "/setting/" + settingName,
  });
}

function notifySuccess() {
  window.CRM.notify(i18next.t("Setting updated successfully"), {
    type: "success",
    delay: 3000,
  });
}

// ── API Key Regeneration ──────────────────────────────────────────
$("#regenApiKey").on("click", function () {
  $.ajax({
    type: "POST",
    url: window.CRM.root + "/api/user/" + window.CRM.viewUserId + "/apikey/regen",
  }).done(function (data, textStatus, xhr) {
    if (xhr.status === 200) {
      $("#apiKey").val(data.apiKey);
      window.CRM.notify(i18next.t("API key regenerated"), {
        type: "success",
        delay: 3000,
      });
    } else {
      window.CRM.notify(i18next.t("Failed generate a new API Key"), {
        type: "danger",
      });
    }
  });
});

// ── Theme Mode (Light / Dark) ────────────────────────────────────
$('input[name="themeMode"]').on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.style", value).done(function () {
    if (value === "dark") {
      document.documentElement.setAttribute("data-bs-theme", "dark");
    } else {
      document.documentElement.removeAttribute("data-bs-theme");
    }
    notifySuccess();
  });
});

// ── Primary Color Picker ─────────────────────────────────────────
$("#primaryColorPicker .btn-color-swatch").on("click", function () {
  let color = $(this).data("color");
  $("#primaryColorPicker .btn-color-swatch").removeClass("active");
  $(this).addClass("active");

  saveUserSetting("ui.theme.primary", color).done(function () {
    if (color) {
      document.documentElement.setAttribute("data-bs-theme-primary", color);
    } else {
      document.documentElement.removeAttribute("data-bs-theme-primary");
    }
    notifySuccess();
  });
});

// ── Base Palette ─────────────────────────────────────────────────
$("#basePalette").on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.theme.base", value).done(function () {
    if (value) {
      document.documentElement.setAttribute("data-bs-theme-base", value);
    } else {
      document.documentElement.removeAttribute("data-bs-theme-base");
    }
    notifySuccess();
  });
});

// ── Border Radius ────────────────────────────────────────────────
$('input[name="borderRadius"]').on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.theme.radius", value).done(function () {
    if (value) {
      document.documentElement.setAttribute("data-bs-theme-radius", value);
    } else {
      document.documentElement.removeAttribute("data-bs-theme-radius");
    }
    notifySuccess();
  });
});

// ── Layout checkboxes (Boxed / Sidebar) ──────────────────────────
$(".user-setting-checkbox").on("click", function () {
  let thisCheckbox = $(this);
  let setting = thisCheckbox.data("setting-name");
  let cssClass = thisCheckbox.data("layout");
  let targetCSS = thisCheckbox.data("css");
  let enabled = thisCheckbox.prop("checked") ? cssClass : "";

  saveUserSetting(setting, enabled).done(function () {
    if (enabled !== "") {
      $(targetCSS).addClass(cssClass);
    } else {
      $(targetCSS).removeClass(cssClass);
    }
    notifySuccess();
  });
});

// ── Table Page Length ─────────────────────────────────────────────
$("#tablePageLength").on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.table.size", value).done(function () {
    notifySuccess();
  });
});

// ── Locale ───────────────────────────────────────────────────────
// Handled separately because it populates from JSON and triggers reload.
// The change handler is bound AFTER initial value is set to avoid reload loop.
function initLocaleDropdown() {
  let dropdown = $("#user-locale-setting");

  // 1. Load available locales and user's saved locale in parallel
  let localeList = $.ajax({
    url: window.CRM.root + "/locale/locales.json",
    dataType: "json",
    type: "GET",
  });
  let savedLocale = getUserSetting("ui.locale");

  // 2. When both are ready, populate and select
  $.when(localeList, savedLocale).done(function (listResult, settingResult) {
    let locales = listResult[0];
    let userLocale = settingResult[0]?.value || "";

    $.each(locales, function (localeName, localeData) {
      let isSelected = userLocale
        ? localeData.locale === userLocale
        : localeData.locale === window.CRM.systemLocale;
      dropdown.append(
        new Option(localeName, localeData.locale, false, isSelected),
      );
    });

    // 3. Bind change handler only after initial value is set
    dropdown.on("change", function () {
      let selected = $(this).find("option:selected");
      saveUserSetting("ui.locale", selected.val()).done(function () {
        window.CRM.notify(
          i18next.t("Language updated to") + " " + selected.text(),
          { type: "success", delay: 3000 },
        );
        setTimeout(function () {
          window.location.reload();
        }, 3000);
      });
    });
  });
}

// ── Initialize all settings on page load ─────────────────────────
$(document).ready(function () {
  initLocaleDropdown();

  // Theme mode
  getUserSetting("ui.style").done(function (data) {
    if (data.value === "dark") {
      $("#themeModeDark").prop("checked", true);
    } else {
      $("#themeModeLight").prop("checked", true);
    }
  });

  // Primary color
  getUserSetting("ui.theme.primary").done(function (data) {
    let color = data.value || "";
    $(
      '#primaryColorPicker .btn-color-swatch[data-color="' + color + '"]',
    ).addClass("active");
  });

  // Base palette
  getUserSetting("ui.theme.base").done(function (data) {
    if (data.value) {
      $("#basePalette").val(data.value);
    }
  });

  // Border radius
  getUserSetting("ui.theme.radius").done(function (data) {
    let val = data.value || "";
    $('input[name="borderRadius"][value="' + val + '"]').prop("checked", true);
  });

  // Checkbox settings (boxed, sidebar)
  $(".user-setting-checkbox").each(function () {
    let thisCheckbox = $(this);
    let setting = thisCheckbox.data("setting-name");
    getUserSetting(setting).done(function (data) {
      if (data.value !== "") {
        thisCheckbox.prop("checked", true);
      }
    });
  });

  // Table page length
  getUserSetting("ui.table.size").done(function (data) {
    if (data.value !== "") {
      $("#tablePageLength").val(data.value);
    }
  });
});
