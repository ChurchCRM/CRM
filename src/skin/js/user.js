// ── Helper: save a user setting via API ──────────────────────────
function saveUserSetting(settingName, value) {
  return window.CRM.APIRequest({
    method: "POST",
    path: "user/" + window.CRM.viewUserId + "/setting/" + settingName,
    dataType: "json",
    data: JSON.stringify({ value: value }),
  });
}

function getUserSetting(settingName) {
  return window.CRM.APIRequest({
    method: "GET",
    path: "user/" + window.CRM.viewUserId + "/setting/" + settingName,
  });
}

function notifySuccess() {
  window.CRM.notify(i18next.t("Setting updated successfully"), {
    type: "success",
    delay: 3000,
  });
}

function notifyError() {
  window.CRM.notify(i18next.t("Failed to save setting"), {
    type: "danger",
    delay: 5000,
  });
}

// ── API Key Regeneration ──────────────────────────────────────────
$("#regenApiKey").on("click", function () {
  $.ajax({
    type: "POST",
    url: window.CRM.root + "/api/user/" + window.CRM.viewUserId + "/apikey/regen",
  })
    .done(function (data) {
      $("#apiKey").val(data.apiKey);
      window.CRM.notify(i18next.t("API key regenerated"), {
        type: "success",
        delay: 3000,
      });
    })
    .fail(notifyError);
});

// ── Theme Mode (Light / Dark) ────────────────────────────────────
$('input[name="themeMode"]').on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.style", value)
    .done(function () {
      if (value === "dark") {
        document.documentElement.setAttribute("data-bs-theme", "dark");
      } else {
        document.documentElement.removeAttribute("data-bs-theme");
      }
      notifySuccess();
    })
    .fail(notifyError);
});

// ── Primary Color Picker ─────────────────────────────────────────
$("#primaryColorPicker .btn-color-swatch").on("click", function () {
  let swatch = $(this);
  let color = swatch.data("color");

  saveUserSetting("ui.theme.primary", color)
    .done(function () {
      $("#primaryColorPicker .btn-color-swatch").removeClass("active");
      swatch.addClass("active");
      if (color) {
        document.documentElement.setAttribute("data-bs-theme-primary", color);
      } else {
        document.documentElement.removeAttribute("data-bs-theme-primary");
      }
      notifySuccess();
    })
    .fail(notifyError);
});

// ── Table Page Length ─────────────────────────────────────────────
$("#tablePageLength").on("change", function () {
  let value = $(this).val();
  saveUserSetting("ui.table.size", value).done(notifySuccess).fail(notifyError);
});

// ── Locale ───────────────────────────────────────────────────────
// Handled separately because it populates from JSON and triggers reload.
// The change handler is bound AFTER initial value is set to avoid reload loop.
function initLocaleDropdown() {
  let dropdown = $("#user-locale-setting");

  let localeList = $.ajax({
    url: window.CRM.root + "/locale/locales.json",
    dataType: "json",
    type: "GET",
  });
  let savedLocale = getUserSetting("ui.locale");

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

    dropdown.on("change", function () {
      let selected = $(this).find("option:selected");
      saveUserSetting("ui.locale", selected.val())
        .done(function () {
          window.CRM.notify(
            i18next.t("Language updated to") + " " + selected.text(),
            { type: "success", delay: 3000 },
          );
          setTimeout(function () {
            window.location.reload();
          }, 3000);
        })
        .fail(notifyError);
    });
  });
}

// ── Initialize on page load ──────────────────────────────────────
$(document).ready(function () {
  initLocaleDropdown();

  // Photo uploader
  if (typeof window._CRM_createPhotoUploader === "function") {
    window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
  }
  if (typeof window.CRM.createPhotoUploader === "function") {
    window.CRM.photoUploader = window.CRM.createPhotoUploader({
      uploadUrl:
        window.CRM.root + "/api/person/" + window.CRM.viewPersonId + "/photo",
      maxFileSize: window.CRM.maxUploadSizeBytes,
      onComplete: function () {
        window.location.reload();
      },
    });
    $("#uploadPhotoBtn").on("click", function (e) {
      e.preventDefault();
      if (window.CRM.photoUploader) {
        window.CRM.photoUploader.show();
      }
    });
  }
});
