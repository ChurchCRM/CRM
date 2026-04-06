// ===== Utility =====

function t(str) {
  if (window.i18next && window.i18next.t) {
    return window.i18next.t(str);
  }
  return str;
}

// ===== JSON Settings Modal =====

function getRender(key, value, depth) {
  var sr = $("<div>")
    .addClass("JSONObjectDiv")
    .data("nodeName", key)
    .css("margin-left", depth * 15 + "px");
  if (value instanceof Object) {
    $("<label>").text(key).appendTo(sr);
    $.each(value, function (key, value) {
      sr.append(getRender(key, value, depth + 1));
    });
  } else {
    $("<label>").text(key).css("margin-right", "15px").appendTo(sr);
    $("<input>").attr("type", "text").val(value).appendTo(sr);
  }
  return sr;
}
var cfgid = 0;
$(".jsonSettingsEdit").on("click", function (event) {
  event.preventDefault();
  cfgid = $(this).data("cfgid");
  var cfgvalue = jQuery.parseJSON($("input[name='new_value[" + cfgid + "]']").val());
  $("#JSONSettingsDiv").html("");
  $.each(cfgvalue, function (key, value) {
    $("#JSONSettingsDiv").append(getRender(key, value, 0));
  });

  $("#JSONSettingsModal").modal("show");
});

function getFormValue(object) {
  var tmp = {};
  if ($(object).children(".JSONObjectDiv").length > 0) {
    $(object)
      .children(".JSONObjectDiv")
      .each(function () {
        tmp[$(this).data("nodeName")] = getFormValue($(this));
      });
    return tmp;
  } else if ($(object).children("input").length > 0) {
    return $("input", object).val();
  }
}

function updateDropDrownFromAjax(selectObj) {
  $.ajax({
    method: "GET",
    url: window.CRM.root + selectObj.data("url"),
    dataType: "json",
    encode: true,
  }).done(function (data) {
    $.each(data, function (index, config) {
      var optSelected = config.id == selectObj.data("value");
      var opt = new Option(config.value, config.id, optSelected, optSelected);
      selectObj.append(opt);
    });
  });
}

$(".jsonSettingsClose").on("click", function (event) {
  var settings = getFormValue($("#JSONSettingsDiv"));
  $("input[name='new_value[" + cfgid + "]']").val(JSON.stringify(settings));
  $("#JSONSettingsModal").modal("hide");
  // Save all settings after JSON edit (includes the updated JSON field)
  systemSettingsSaveAll();
});

$(".setting-tip").click(function () {
  bootbox.alert({
    message: $(this).data("tip"),
    backdrop: true,
    className: "setting-tip-box",
  });
});

// ===== Dirty-state tracking =====

/**
 * Returns true when the given input differs from its original page-load value.
 * Password fields are never considered dirty when empty (blank = keep existing).
 */
function isInputDirty($el) {
  var name = $el.data("setting-name");
  if (!name) return false;
  if ($el.attr("type") === "password" && $el.val() === "") return false;
  var initial = $el.data("initial-value");
  if (initial === undefined) return false;
  return String($el.val()) !== String(initial);
}

/** Returns true when any tracked input on the page has been modified. */
function isAnyDirty() {
  var dirty = false;
  $("[data-setting-name]").each(function () {
    if (isInputDirty($(this))) {
      dirty = true;
      return false; // break jQuery .each
    }
  });
  return dirty;
}

/**
 * Returns an array of tab-pane IDs (other than excludeTabId) that contain
 * at least one dirty input.
 */
function getDirtyTabsExcept(excludeTabId) {
  var tabs = [];
  $("[data-setting-name]").each(function () {
    var $el = $(this);
    if (!isInputDirty($el)) return;
    var tabPane = $el.closest(".tab-pane");
    if (!tabPane.length) return;
    var tabId = tabPane.attr("id");
    if (tabId !== excludeTabId && tabs.indexOf(tabId) === -1) {
      tabs.push(tabId);
    }
  });
  return tabs;
}

// ===== AJAX Save Logic =====

var _isSaving = false;

/**
 * Collects all [data-setting-name] inputs within $container into a plain
 * {name: value} object, skipping empty password fields.
 */
function collectSettings($container) {
  var settings = {};
  $container.find("[data-setting-name]").each(function () {
    var $el = $(this);
    var name = $el.data("setting-name");
    if (!name) return;
    var val = $el.val();
    if ($el.attr("type") === "password" && val === "") return;
    settings[name] = val;
  });
  return settings;
}

/**
 * Sends API POST calls for each setting in the map, then reloads the page
 * via GET (no double-POST).  Disables save buttons while in-flight.
 */
function doSave(settings) {
  var keys = Object.keys(settings);
  if (keys.length === 0) {
    window.location.reload();
    return;
  }

  $("[data-save-scope]").prop("disabled", true);

  var promises = keys.map(function (name) {
    return fetch(window.CRM.root + "/admin/api/system/config/" + encodeURIComponent(name), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ value: settings[name] }),
    }).then(function (resp) {
      if (!resp.ok) {
        return resp.json().then(function (e) {
          throw e;
        });
      }
      return resp.json();
    });
  });

  Promise.all(promises)
    .then(function () {
      _isSaving = true; // suppress beforeunload on the reload
      window.location.reload();
    })
    .catch(function (err) {
      console.error("Settings save failed:", err);
      $("[data-save-scope]").prop("disabled", false);
      if (window.CRM && window.CRM.notify) {
        window.CRM.notify(t("Failed to save settings. Please try again."), {
          type: "error",
          delay: 5000,
        });
      }
    });
}

/** Save every setting on the page. */
function systemSettingsSaveAll() {
  doSave(collectSettings($(document)));
}

/** Save only the settings that belong to the given tab-pane ID. */
function systemSettingsSaveSection(tabId) {
  doSave(collectSettings($("#" + tabId)));
}

// ===== Save-button click handlers =====

// Sidebar "Save Settings" — save everything
$(document).on("click", "[data-save-scope='all']", function (e) {
  e.preventDefault();
  systemSettingsSaveAll();
});

// Per-card "Save Settings" — save current section; warn if others are dirty
$(document).on("click", "[data-save-scope='section']", function (e) {
  e.preventDefault();
  var activeTabId = $(".tab-pane.show.active").attr("id");
  if (!activeTabId) {
    systemSettingsSaveAll();
    return;
  }

  var dirtyOtherTabs = getDirtyTabsExcept(activeTabId);
  if (dirtyOtherTabs.length > 0) {
    bootbox.confirm({
      title: t("Unsaved Changes"),
      message: t("You have unsaved changes on this page. They will be lost when the page reloads. Continue?"),
      buttons: {
        cancel: { label: t("Cancel"), className: "btn-secondary" },
        confirm: { label: t("Continue"), className: "btn-primary" },
      },
      callback: function (result) {
        if (result) {
          systemSettingsSaveSection(activeTabId);
        }
      },
    });
  } else {
    systemSettingsSaveSection(activeTabId);
  }
});

// ===== Navigation-away warnings =====

// Native browser dialog for tab/window close and address-bar navigation
window.addEventListener("beforeunload", function (e) {
  if (!_isSaving && isAnyDirty()) {
    e.preventDefault();
    e.returnValue = "";
  }
});

// Bootbox dialog when clicking any page link that would navigate away
$(document).on("click", "a[href]", function (e) {
  var href = $(this).attr("href");
  // Skip: empty links, same-page anchors, tab toggles, new-tab links
  if (!href || href === "#" || href.charAt(0) === "#") return;
  if ($(this).is("[data-bs-toggle]")) return;
  if ($(this).attr("target") === "_blank") return;
  if (!isAnyDirty()) return;

  e.preventDefault();
  bootbox.confirm({
    title: t("Unsaved Changes"),
    message: t("You have unsaved changes. Are you sure you want to leave without saving?"),
    buttons: {
      cancel: { label: t("Stay"), className: "btn-secondary" },
      confirm: { label: t("Leave"), className: "btn-warning" },
    },
    callback: function (result) {
      if (result) {
        _isSaving = true; // suppress beforeunload
        window.location.href = href;
      }
    },
  });
});
