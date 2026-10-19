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
let pendingApiKeyAction = null;

function getApiKeyCsrfToken() {
  return $("#apiKeyControls").attr("data-csrf-token") || "";
}

function showApiKeyReauthentication(action) {
  pendingApiKeyAction = action;
  $("#apiKeyReauthenticationError").addClass("d-none").text("");
  $("#apiKeyReauthentication").removeClass("d-none");
  $("#apiKeyCurrentPassword").val("").trigger("focus");
}

function executeApiKeyAction(action) {
  $.ajax({
    type: "POST",
    url: `${window.CRM.root}/api/user/${window.CRM.viewUserId}/apikey/${action}`,
    dataType: "json",
    headers: { "X-CSRF-Token": getApiKeyCsrfToken() },
  })
    .done((data) => {
      const apiKey = typeof data.apiKey === "string" ? data.apiKey : "";
      $("#apiKey")
        .attr("type", apiKey ? "text" : "password")
        .val(apiKey);
      if (action === "regen") {
        window.CRM.notify(i18next.t("API key regenerated"), {
          type: "success",
          delay: 3000,
        });
      }
    })
    .fail((xhr) => {
      if (xhr.status === 428 && xhr.responseJSON?.code === "reauthentication_required") {
        showApiKeyReauthentication(action);
        return;
      }
      if (xhr.status === 401) {
        window.location.assign(`${window.CRM.root}/session/begin`);
        return;
      }
      if (xhr.status === 403) {
        window.CRM.notify(i18next.t("This page's security token expired. Reload the page and try again."), {
          type: "danger",
          delay: 7000,
        });
        return;
      }
      window.CRM.notify(i18next.t("Unable to manage the API key"), {
        type: "danger",
        delay: 5000,
      });
    });
}

$("#revealApiKey").on("click", () => {
  if ($("#apiKey").attr("type") === "text" && $("#apiKey").val()) {
    $("#apiKey").attr("type", "password").val("");
    return;
  }
  executeApiKeyAction("reveal");
});

$("#regenApiKey").on("click", () => {
  if (window.confirm(i18next.t("Regenerate this API key and invalidate the current key?"))) {
    executeApiKeyAction("regen");
  }
});

$("#confirmApiKeyReauthentication").on("click", () => {
  const currentPassword = $("#apiKeyCurrentPassword").val();
  if (!currentPassword) {
    $("#apiKeyReauthenticationError").removeClass("d-none").text(i18next.t("Current password is required."));
    return;
  }

  $.ajax({
    type: "POST",
    url: `${window.CRM.root}/api/user/current/reauthenticate`,
    contentType: "application/json",
    dataType: "json",
    headers: { "X-CSRF-Token": getApiKeyCsrfToken() },
    data: JSON.stringify({ currentPassword }),
  })
    .done((data) => {
      if (typeof data.CSRFToken === "string" && data.CSRFToken) {
        $("#apiKeyControls").attr("data-csrf-token", data.CSRFToken);
      }
      const action = pendingApiKeyAction;
      pendingApiKeyAction = null;
      $("#apiKeyCurrentPassword").val("");
      $("#apiKeyReauthentication").addClass("d-none");
      if (action) executeApiKeyAction(action);
    })
    .fail((xhr) => {
      if (xhr.status === 401) {
        window.location.assign(`${window.CRM.root}/session/begin`);
        return;
      }
      if (xhr.status === 403) {
        $("#apiKeyReauthenticationError")
          .removeClass("d-none")
          .text(i18next.t("This page's security token expired. Reload the page and try again."));
        return;
      }
      const message =
        xhr.status === 422
          ? i18next.t("The current password was not accepted.")
          : i18next.t("Unable to confirm your password. Reload the page and try again.");
      $("#apiKeyReauthenticationError").removeClass("d-none").text(message);
    });
});

$("#cancelApiKeyReauthentication").on("click", () => {
  pendingApiKeyAction = null;
  $("#apiKeyCurrentPassword").val("");
  $("#apiKeyReauthentication").addClass("d-none");
});

// ── Theme Mode (Light / Dark / Auto) ────────────────────────────
$('input[name="themeMode"]').on("change", function () {
  const value = $(this).val();
  saveUserSetting("ui.style", value)
    .done(() => {
      if (window.CRM.viewIsOwnProfile && window.CRM.theme) {
        window.CRM.theme.setMode(value);
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
      if (window.CRM.viewIsOwnProfile) {
        if (color) {
          document.documentElement.setAttribute("data-bs-theme-primary", color);
        } else {
          document.documentElement.removeAttribute("data-bs-theme-primary");
        }
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
