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
$("#regenApiKey").on("click", () => {
  $.ajax({
    type: "POST",
    url: window.CRM.root + "/api/user/" + window.CRM.viewUserId + "/apikey/regen",
  })
    .done((data) => {
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
  const value = $(this).val();
  saveUserSetting("ui.style", value)
    .done(() => {
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
  const swatch = $(this);
  const color = swatch.data("color");

  saveUserSetting("ui.theme.primary", color)
    .done(() => {
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
  const value = $(this).val();
  saveUserSetting("ui.table.size", value).done(notifySuccess).fail(notifyError);
});

// ── Locale ───────────────────────────────────────────────────────
// Handled separately because it populates from JSON and triggers reload.
// The change handler is bound AFTER initial value is set to avoid reload loop.
function initLocaleDropdown() {
  const dropdown = $("#user-locale-setting");
  const savedLocale = getUserSetting("ui.locale");

  savedLocale.done((settingResult) => {
    const userLocale = settingResult?.value || window.CRM.systemLocale || "";

    window.CRM.populateLocaleDropdown(dropdown[0], userLocale)
      .then(() => {
        dropdown.on("change", function () {
          const selected = $(this).find("option:selected");
          saveUserSetting("ui.locale", selected.val())
            .done(() => {
              window.CRM.notify(i18next.t("Language updated to") + " " + selected.text(), {
                type: "success",
                delay: 3000,
              });
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            })
            .fail(notifyError);
        });
      })
      .catch((e) => {
        console.error("Failed to load locale dropdown:", e);
      });
  });
}

// ── Initialize on page load ──────────────────────────────────────
$(document).ready(() => {
  // Activate the tab matching the URL hash (e.g. #tab-localization from locale banner link).
  if (window.location.hash) {
    const tabEl = document.querySelector(`[data-bs-toggle="list"][href="${window.location.hash}"]`);
    if (tabEl && window.bootstrap?.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(tabEl).show();
    }
  }

  initLocaleDropdown();

  // Photo uploader
  if (typeof window._CRM_createPhotoUploader === "function") {
    window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
  }
  if (typeof window.CRM.createPhotoUploader === "function") {
    window.CRM.photoUploader = window.CRM.createPhotoUploader({
      uploadUrl: window.CRM.root + "/api/person/" + window.CRM.viewPersonId + "/photo",
      maxFileSize: window.CRM.maxUploadSizeBytes,
      onComplete: () => {
        window.location.reload();
      },
    });
    $("#uploadPhotoBtn").on("click", (e) => {
      e.preventDefault();
      if (window.CRM.photoUploader) {
        window.CRM.photoUploader.show();
      }
    });
  }
});
