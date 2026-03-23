// i18next initialization is now handled by locale-loader.js

// Alias jQuery from global scope — churchcrm.min.js sets window.jQuery in <head>
var $ = window.jQuery;

if (!$) {
  console.warn("[Footer.js] jQuery not available. DOM functions will be deferred.");
}

// Wait for both DOM ready AND locales loaded before initializing
function initializeApp() {
  // Guard against jQuery not being available
  if (!window.jQuery) {
    console.error("[Footer.js] Cannot initialize app - jQuery not loaded");
    // Retry after a short delay
    setTimeout(initializeApp, 500);
    return;
  }

  // Re-assign $ in case it was loaded after this script
  const $ = window.jQuery;
  (() => {
    var input = document.getElementById("globalSearch");
    var dropdown = document.getElementById("globalSearchDropdown");
    if (!input || !dropdown) return;

    var debounceTimer = null;
    var currentItems = [];
    var highlighted = -1;

    var groupIcons = {
      Persons: "ti-user",
      Families: "ti-home",
      Groups: "ti-users-group",
      Addresses: "ti-map-pin",
      "Finance Deposits": "ti-building-bank",
      "Finance Payments": "ti-credit-card",
      "Calendar Events": "ti-calendar",
    };

    function showDropdown() {
      dropdown.style.display = "block";
    }
    function hideDropdown() {
      dropdown.style.display = "none";
      highlighted = -1;
    }

    function setHighlight(idx) {
      var items = dropdown.querySelectorAll(".dropdown-item[data-idx]");
      items.forEach((el) => {
        el.classList.remove("active");
      });
      highlighted = Math.max(-1, Math.min(idx, currentItems.length - 1));
      if (highlighted >= 0 && items[highlighted]) {
        items[highlighted].classList.add("active");
        items[highlighted].scrollIntoView({ block: "nearest" });
      }
    }

    function render(groups) {
      currentItems = [];
      var html = "";

      if (!groups || groups.length === 0) {
        html = '<div class="dropdown-item disabled text-secondary">' + i18next.t("No results found") + "</div>";
        dropdown.innerHTML = html;
        showDropdown();
        return;
      }

      groups.forEach((group) => {
        // group.text is "Persons (5)" — strip the count suffix for icon lookup
        var baseName = group.text.split("(")[0].trim();
        var icon = groupIcons[baseName] || "ti-search";
        html +=
          '<div class="dropdown-header d-flex align-items-center">' +
          '<i class="ti ' +
          icon +
          ' me-1"></i> ' +
          group.text +
          "</div>";
        (group.children || []).forEach((item) => {
          var idx = currentItems.length;
          currentItems.push(item);
          html += '<a href="' + item.uri + '" class="dropdown-item" data-idx="' + idx + '">' + item.text + "</a>";
        });
      });

      var term = encodeURIComponent(input.value);
      html +=
        '<div class="dropdown-divider"></div>' +
        '<a href="' +
        window.CRM.root +
        "/v2/search?q=" +
        term +
        '" class="dropdown-item">' +
        '<i class="ti ti-arrow-right me-1"></i>' +
        i18next.t("View all results") +
        "</a>";

      dropdown.innerHTML = html;
      showDropdown();
      highlighted = -1;
    }

    function doSearch(term) {
      if (term.length < 2) {
        hideDropdown();
        return;
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        fetch(window.CRM.root + "/api/search/" + encodeURIComponent(term))
          .then((r) => r.json())
          .then((data) => {
            render(data);
          })
          .catch(() => {
            hideDropdown();
          });
      }, 250);
    }

    input.addEventListener("input", function () {
      doSearch(this.value);
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "ArrowDown") {
        e.preventDefault();
        setHighlight(highlighted + 1);
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        setHighlight(highlighted - 1);
      } else if (e.key === "Enter") {
        e.preventDefault();
        if (highlighted >= 0 && currentItems[highlighted]) {
          window.location.href = currentItems[highlighted].uri;
        } else if (input.value.length > 0) {
          window.location.href = window.CRM.root + "/v2/search?q=" + encodeURIComponent(input.value);
        }
      } else if (e.key === "Escape") {
        hideDropdown();
        input.blur();
      }
    });

    input.addEventListener("focus", () => {
      if (input.value.length >= 2) doSearch(input.value);
    });

    document.addEventListener("click", (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        hideDropdown();
      }
    });

    // "?" shortcut to focus search — must be keydown to prevent the char being typed
    window.addEventListener("keydown", (e) => {
      const tag = document.activeElement?.tagName ?? "";
      const inInput =
        tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || document.activeElement?.isContentEditable;
      if (e.shiftKey && e.key === "?" && !inInput) {
        e.preventDefault();
        input.focus();
      }
    });
  })();

  window.CRM.system.runTimerJobs();

  $(".date-picker").datepicker({
    format: window.CRM.datePickerformat,
    language: window.CRM.lang,
  });

  $(".maxUploadSize").text(window.CRM.maxUploadSize);

  // Note: Cart event handlers are now in cart.js module
  // The CartManager class handles all cart button buttons and notifications

  // Initialize just-validate for all forms with data-validate attribute
  initializeFormValidation();

  // Load event counters once on page load (no polling needed - values only change at midnight)
  window.CRM.dashboard.loadEventCounters();

  window.CRM.APIRequest({
    path: "system/notification",
  }).done((data) => {
    data.notifications.forEach((item) => {
      window.CRM.notify(item.title, {
        delay: item.delay,
        type: item.type,
      });
    });
  });

  // Initialize FAB buttons with localized labels
  initializeFAB();
}

// Helper function to run initialization code after locales are loaded
// Usage: window.CRM.onLocalesReady(function() { /* your init code */ });
window.CRM.onLocalesReady = (callback) => {
  if (window.CRM.localesLoaded) {
    callback();
  } else {
    window.addEventListener("CRM.localesReady", callback, { once: true });
  }
};

// Wait for both DOM and locales to be ready
// Guard against jQuery not being available yet
if (window.jQuery && typeof window.jQuery === "function") {
  window.jQuery(document).ready(() => {
    window.CRM.onLocalesReady(initializeApp);
  });
} else {
  // Fallback if jQuery is not available: use DOM ready event
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      window.CRM.onLocalesReady(initializeApp);
    });
  } else {
    // DOM already loaded
    window.CRM.onLocalesReady(initializeApp);
  }
}

function showGlobalMessage(message, callOutClass) {
  window.CRM.notify(message, {
    delay: 5000,
    type: callOutClass,
  });
}

/**
 * Initialize form validation for all forms with data-validate attribute
 * Uses just-validate library with Bootstrap 4 styling
 */
function initializeFormValidation() {
  document.querySelectorAll("form[data-validate]").forEach((form) => {
    const validator = new window.JustValidate(form, {
      errorFieldCssClass: "is-invalid",
      successFieldCssClass: "is-valid",
      errorLabelCssClass: "invalid-feedback",
      focusInvalidField: true,
      lockForm: true,
    });

    // Auto-add validation rules based on HTML5 attributes
    form.querySelectorAll("input, select, textarea").forEach((field) => {
      const rules = [];

      if (field.hasAttribute("required")) {
        rules.push({
          rule: "required",
          errorMessage: i18next.t("This field is required"),
        });
      }

      if (field.type === "email") {
        rules.push({
          rule: "email",
          errorMessage: i18next.t("Please enter a valid email address"),
        });
      }

      if (field.type === "url") {
        rules.push({
          rule: "customRegexp",
          value: /^https?:\/\/.+/,
          errorMessage: i18next.t("Please enter a valid URL"),
        });
      }

      if (field.hasAttribute("pattern")) {
        rules.push({
          rule: "customRegexp",
          value: new RegExp(field.getAttribute("pattern")),
          errorMessage: field.getAttribute("title") || i18next.t("Invalid format"),
        });
      }

      if (field.hasAttribute("minlength")) {
        rules.push({
          rule: "minLength",
          value: parseInt(field.getAttribute("minlength")),
          errorMessage: i18next.t("Minimum length is") + " " + field.getAttribute("minlength"),
        });
      }

      if (field.hasAttribute("maxlength")) {
        rules.push({
          rule: "maxLength",
          value: parseInt(field.getAttribute("maxlength")),
          errorMessage: i18next.t("Maximum length is") + " " + field.getAttribute("maxlength"),
        });
      }

      if (rules.length > 0 && field.name) {
        rules.forEach((rule) => {
          validator.addField(field.id ? "#" + field.id : '[name="' + field.name + '"]', [rule]);
        });
      }
    });
  });
}

/**
 * Initialize Floating Action Buttons (FAB)
 * - Sets localized labels
 * - Handles scroll behavior to hide buttons on scroll
 * - Auto-hides after 5 seconds
 * - Hides global FAB if page-specific FAB exists
 */
function initializeFAB() {
  const fabContainer = $("#fab-container");
  const fabPersonLabel = $("#fab-person-label");
  const fabFamilyLabel = $("#fab-family-label");

  // Hide global FAB if a page-specific FAB exists
  if (
    $("#fab-person-view").length > 0 ||
    $("#fab-person-editor").length > 0 ||
    $("#fab-family-editor").length > 0 ||
    $("#fab-family-view").length > 0
  ) {
    fabContainer.hide();
    return;
  }

  // Set localized labels
  fabPersonLabel.text(i18next.t("Add New") + " " + i18next.t("Person"));
  fabFamilyLabel.text(i18next.t("Add New") + " " + i18next.t("Family"));

  // Auto-hide FAB after 5 seconds
  setTimeout(() => {
    fabContainer.addClass("hidden");
  }, 5000);

  // Hide FAB on any scroll to prevent blocking content
  $(window).on("scroll", function () {
    const currentScroll = $(this).scrollTop();

    // Hide FAB once user scrolls past 50px
    if (currentScroll > 50) {
      fabContainer.addClass("hidden");
    }
  });
}
