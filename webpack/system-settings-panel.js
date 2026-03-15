/**
 * System Settings Panel - Reusable component for displaying/editing SystemConfig settings
 *
 * Usage:
 * 1. Include the CSS: <link rel="stylesheet" href="<?= SystemURLs::getRootPath() ?>/skin/v2/system-settings-panel.min.css">
 * 2. Include the JS: <script src="<?= SystemURLs::getRootPath() ?>/skin/v2/system-settings-panel.min.js"></script>
 * 3. Add container: <div id="settingsPanel"></div>
 * 4. Initialize:
 *    window.CRM.settingsPanel.init({
 *        container: '#settingsPanel',
 *        title: 'Financial Settings',
 *        icon: 'fa-solid fa-sliders',
 *        settings: ['iFYMonth', 'bEnableNonDeductible', 'iChecksPerDepositForm'],
 *        onSave: function() { window.location.reload(); }
 *    });
 */

import "../src/skin/scss/system-settings-panel.scss";

(function () {
  "use strict";

  // Setting type definitions with rendering and value extraction
  const SettingTypes = {
    boolean: {
      render: function (setting, value) {
        const checked = value === "1" || value === "true" || value === true;
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input setting-input"
                                   id="${setting.name}" name="${setting.name}"
                                   data-type="boolean"
                                   ${checked ? "checked" : ""}>
                            <label class="custom-control-label" for="${setting.name}">
                                ${setting.label}
                                ${setting.tooltip ? `<i class="fa-solid fa-circle-question text-muted ml-1" data-toggle="tooltip" data-placement="top" title="${escapeHtml(setting.tooltip)}"></i>` : ""}
                            </label>
                        </div>
                    </div>
                `;
      },
      getValue: function (el) {
        return el.checked ? "1" : "0";
      },
    },
    number: {
      render: function (setting, value) {
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <label for="${setting.name}" class="form-label small font-weight-bold mb-1">
                            ${setting.label}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-info ml-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <input type="number" class="form-control setting-input" 
                               id="${setting.name}" name="${setting.name}"
                               data-type="number"
                               value="${value || ""}" 
                               ${setting.min !== undefined ? `min="${setting.min}"` : ""}
                               ${setting.max !== undefined ? `max="${setting.max}"` : ""}>
                        ${setting.tooltip ? `<small class="form-text text-muted">${setting.tooltip}</small>` : ""}
                    </div>
                `;
      },
      getValue: function (el) {
        return el.value;
      },
    },
    text: {
      render: function (setting, value) {
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <label for="${setting.name}" class="form-label small font-weight-bold mb-1">
                            ${setting.label}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-info ml-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <input type="text" class="form-control setting-input" 
                               id="${setting.name}" name="${setting.name}"
                               data-type="text"
                               value="${escapeHtml(value || "")}"
                               ${setting.placeholder ? `placeholder="${setting.placeholder}"` : ""}>
                        ${setting.tooltip ? `<small class="form-text text-muted">${setting.tooltip}</small>` : ""}
                    </div>
                `;
      },
      getValue: function (el) {
        return el.value;
      },
    },
    choice: {
      render: function (setting, value) {
        let optionsHtml = "";
        if (setting.choices) {
          setting.choices.forEach(function (choice) {
            const optValue = typeof choice === "object" ? choice.value : choice;
            const optLabel = typeof choice === "object" ? choice.label : choice;
            const selected = String(value) === String(optValue) ? "selected" : "";
            optionsHtml += `<option value="${escapeHtml(optValue)}" ${selected}>${escapeHtml(optLabel)}</option>`;
          });
        }
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <label for="${setting.name}" class="form-label small font-weight-bold mb-1">
                            ${setting.label}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-info ml-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <select class="form-control setting-input" 
                                id="${setting.name}" name="${setting.name}"
                                data-type="choice">
                            ${optionsHtml}
                        </select>
                        ${setting.tooltip ? `<small class="form-text text-muted">${setting.tooltip}</small>` : ""}
                    </div>
                `;
      },
      getValue: function (el) {
        return el.value;
      },
    },
    // Password fields never fetch or display the current value.
    // Leave blank to keep the existing value; type a new value to change it.
    // Set setting.generate = true to add a "Generate" button that fills the field.
    password: {
      render: function (setting) {
        const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;
        const generateBtn = setting.generate
          ? `<div class="input-group-append">
               <button type="button" class="btn btn-outline-secondary btn-sm generate-password-btn" data-target="${setting.name}">
                 <i class="fa-solid fa-key mr-1"></i>${t("Generate")}
               </button>
             </div>`
          : "";
        return `
            <div class="col-md-6 col-lg-4 mb-3">
              <label for="${setting.name}" class="form-label small font-weight-bold mb-1">
                ${setting.label}
              </label>
              <div class="input-group">
                <input type="password" class="form-control setting-input"
                     id="${setting.name}" name="${setting.name}"
                     data-type="password"
                     autocomplete="new-password"
                     placeholder="${t("Leave blank to keep existing")}">
                ${generateBtn}
              </div>
              ${setting.tooltip ? `<small class="form-text text-muted">${setting.tooltip}</small>` : ""}
            </div>
          `;
      },
      getValue: function (el) {
        // Return null for empty passwords so they are skipped in the bulk save
        return el.value || null;
      },
    },
  };

  // Escape HTML for safe use in both text content and attribute values (title="...", value="...")
  function escapeHtml(str) {
    if (!str) return "";
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  // Month choices helper
  function getMonthChoices() {
    return [
      { value: "1", label: window.i18next ? i18next.t("January") : "January" },
      { value: "2", label: window.i18next ? i18next.t("February") : "February" },
      { value: "3", label: window.i18next ? i18next.t("March") : "March" },
      { value: "4", label: window.i18next ? i18next.t("April") : "April" },
      { value: "5", label: window.i18next ? i18next.t("May") : "May" },
      { value: "6", label: window.i18next ? i18next.t("June") : "June" },
      { value: "7", label: window.i18next ? i18next.t("July") : "July" },
      { value: "8", label: window.i18next ? i18next.t("August") : "August" },
      { value: "9", label: window.i18next ? i18next.t("September") : "September" },
      { value: "10", label: window.i18next ? i18next.t("October") : "October" },
      { value: "11", label: window.i18next ? i18next.t("November") : "November" },
      { value: "12", label: window.i18next ? i18next.t("December") : "December" },
    ];
  }

  // Pre-defined setting configurations (from SystemConfig)
  const SettingDefinitions = {
    // Financial Settings
    iFYMonth: {
      type: "choice",
      label: "First month of the fiscal year",
      choices: getMonthChoices,
    },
    sDepositSlipType: {
      type: "choice",
      label: "Deposit ticket type",
      tooltip: "QBDT - QuickBooks Deposit Ticket",
      choices: [{ value: "QBDT", label: "QBDT (QuickBooks)" }],
    },
    iChecksPerDepositForm: {
      type: "number",
      label: "Number of checks for Deposit Slip Report",
      min: 1,
      max: 100,
    },
    bDisplayBillCounts: {
      type: "boolean",
      label: "Display bill counts on deposit slip",
    },
    bUseScannedChecks: {
      type: "boolean",
      label: "Enable use of scanned checks",
    },
    bEnableNonDeductible: {
      type: "boolean",
      label: "Enable non-deductible payments",
    },
    bUseDonationEnvelopes: {
      type: "boolean",
      label: "Enable use of donation envelopes",
    },
    aFinanceQueries: {
      type: "text",
      label: "Finance permission query IDs",
      tooltip: "Comma-separated query IDs requiring finance permissions",
      placeholder: "30,31,32",
    },
    // Church Information
    sChurchName: {
      type: "text",
      label: "Church Name",
    },
    sChurchAddress: {
      type: "text",
      label: "Church Address",
    },
    sChurchCity: {
      type: "text",
      label: "Church City",
    },
    sChurchState: {
      type: "text",
      label: "Church State",
    },
    sChurchZip: {
      type: "text",
      label: "Church Zip",
    },
    sChurchPhone: {
      type: "text",
      label: "Church Phone",
    },
    sChurchEmail: {
      type: "text",
      label: "Church Email",
    },
    // Report Settings
    sTaxSigner: {
      type: "text",
      label: "Tax Report signer",
    },
    sReminderSigner: {
      type: "text",
      label: "Pledge Reminder Signer",
    },
  };

  // Settings Panel Class
  class SettingsPanel {
    constructor() {
      this.options = {};
      this.settingValues = {};
      this.initialized = false;
    }

    /**
     * Initialize the settings panel
     * @param {Object} options Configuration options
     * @param {string} options.container - CSS selector for container element
     * @param {string} options.toggleButton - CSS selector for toggle button (optional)
     * @param {string} options.title - Panel title
     * @param {string} options.icon - Font Awesome icon class
     * @param {Array} options.settings - Array of setting names or setting config objects
     * @param {Function} options.onSave - Callback after successful save
     * @param {boolean} options.showAllSettingsLink - Show link to System Settings page
     * @param {string} options.headerClass - CSS class for header (default: bg-info)
     */
    init(options) {
      this.options = Object.assign(
        {
          container: "#settingsPanel",
          toggleButton: null,
          title: "Settings",
          icon: "fa-solid fa-cog",
          settings: [],
          onSave: null,
          showAllSettingsLink: true,
          headerClass: "bg-info",
        },
        options,
      );

      this.container = document.querySelector(this.options.container);
      if (!this.container) {
        console.error("Settings panel container not found:", this.options.container);
        return;
      }

      this.loadSettings();
    }

    // Load current setting values from API
    loadSettings() {
      const self = this;
      // Password fields never fetch their current value — always rendered blank
      const settingsToFetch = this.options.settings.filter((s) => {
        const cfg = this.getSettingConfig(s);
        return cfg.type !== "password";
      });
      const settingNames = settingsToFetch.map((s) => (typeof s === "string" ? s : s.name));

      // Fetch all setting values
      const promises = settingNames.map((name) => {
        return fetch(window.CRM.root + "/admin/api/system/config/" + name)
          .then((response) => response.json())
          .then((data) => {
            self.settingValues[name] = data.value;
          })
          .catch((err) => {
            console.warn("Could not load setting:", name);
            self.settingValues[name] = "";
          });
      });

      Promise.all(promises).then(() => {
        self.render();
        self.bindEvents();
        self.initialized = true;
      });
    }

    // Render the panel HTML
    render() {
      let settingsHtml = "";

      this.options.settings.forEach((setting) => {
        const settingName = typeof setting === "string" ? setting : setting.name;
        const settingConfig = this.getSettingConfig(setting);
        const value = this.settingValues[settingName];

        const renderer = SettingTypes[settingConfig.type];
        if (renderer) {
          // Handle dynamic choices (functions)
          if (typeof settingConfig.choices === "function") {
            settingConfig.choices = settingConfig.choices();
          }
          settingsHtml += renderer.render(settingConfig, value);
        }
      });

      const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;

      const html = `
                <div class="card settings-panel-card border-info">
                    <div class="card-header ${this.options.headerClass} text-white py-2">
                        <h6 class="mb-0">
                            <i class="${this.options.icon}"></i> ${this.options.title}
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="settingsPanelForm">
                            <div class="row">
                                ${settingsHtml}
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center">
                                ${
                                  this.options.showAllSettingsLink
                                    ? `
                                <a href="${window.CRM.root}/SystemSettings.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-external-link-alt mr-1"></i> ${t("All System Settings")}
                                </a>
                                `
                                    : "<div></div>"
                                }
                                <button type="button" id="settingsPanelSaveBtn" class="btn btn-primary">
                                    <i class="fa-solid fa-save mr-1"></i> ${t("Save Settings")}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

      this.container.innerHTML = html;
    }

    // Get merged setting configuration
    getSettingConfig(setting) {
      const name = typeof setting === "string" ? setting : setting.name;
      const baseConfig = SettingDefinitions[name] || { type: "text", label: name };
      const customConfig = typeof setting === "object" ? setting : {};

      return Object.assign({ name: name }, baseConfig, customConfig);
    }

    // Bind event handlers
    bindEvents() {
      const self = this;

      // Initialize Bootstrap tooltips on help icons
      if (window.$ && $.fn.tooltip) {
        $(this.container).find('[data-toggle="tooltip"]').tooltip();
      }

      const saveBtn = this.container.querySelector("#settingsPanelSaveBtn");

      if (saveBtn) {
        saveBtn.addEventListener("click", function () {
          self.save();
        });
      }

      // Generate button for password fields — fills the input with a random key
      this.container.querySelectorAll(".generate-password-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const input = document.getElementById(btn.dataset.target);
          if (input) {
            input.value = SettingsPanel.generateSecretHex();
          }
        });
      });

      // Toggle button handling
      if (this.options.toggleButton) {
        const toggleBtn = document.querySelector(this.options.toggleButton);
        if (toggleBtn) {
          toggleBtn.addEventListener("click", function () {
            $(self.container).collapse("toggle");
          });
        }
      }
    }

    // Generate a secure random hex string of 32 bytes (64 hex chars)
    static generateSecretHex() {
      const array = new Uint8Array(32);
      window.crypto.getRandomValues(array);
      return Array.from(array)
        .map((b) => ("00" + b.toString(16)).slice(-2))
        .join("");
    }

    // Save all settings
    save() {
      const self = this;
      const saveBtn = this.container.querySelector("#settingsPanelSaveBtn");
      const originalHtml = saveBtn.innerHTML;
      const t = window.i18next ? i18next.t.bind(i18next) : (s) => s;

      // Disable button and show loading
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> ' + t("Saving...");

      // Collect all setting values (empty passwords return null from getValue and are skipped)
      const settings = {};
      this.container.querySelectorAll(".setting-input").forEach(function (input) {
        const type = input.dataset.type;
        const renderer = SettingTypes[type];
        if (renderer) {
          const val = renderer.getValue(input);
          if (val !== null) {
            settings[input.name] = val;
          }
        }
      });

      // Save each setting
      const promises = Object.keys(settings).map(function (key) {
        return fetch(window.CRM.root + "/admin/api/system/config/" + key, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ value: settings[key] }),
        });
      });

      Promise.all(promises)
        .then(function () {
          if (window.CRM && window.CRM.notify) {
            window.CRM.notify(t("Settings saved successfully"), { type: "success", delay: 2000 });
          }

          if (typeof self.options.onSave === "function") {
            self.options.onSave();
          }
        })
        .catch(function (error) {
          if (window.CRM && window.CRM.notify) {
            window.CRM.notify(t("Failed to save settings"), { type: "error", delay: 5000 });
          }
          saveBtn.disabled = false;
          saveBtn.innerHTML = originalHtml;
        });
    }

    // Add a custom setting definition
    static addSettingDefinition(name, config) {
      SettingDefinitions[name] = config;
    }

    // Add a custom setting type renderer
    static addSettingType(typeName, renderer) {
      SettingTypes[typeName] = renderer;
    }
  }

  // Export to window.CRM namespace
  window.CRM = window.CRM || {};
  window.CRM.SettingsPanel = SettingsPanel;
  window.CRM.settingsPanel = new SettingsPanel();
})();
