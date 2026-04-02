/**
 * System Settings Panel - Reusable component for displaying/editing SystemConfig settings
 *
 * Usage:
 * 1. Include the CSS bundle, for example:
 *    <link rel="stylesheet" href="/skin/v2/system-settings-panel.min.css">
 * 2. Include the JS bundle, for example:
 *    <script src="/skin/v2/system-settings-panel.min.js"></script>
 * (When rendering server-side you may prepend the application's root path.)
 * 3. Add container: <div id="settingsPanel"></div>
 * 4. Initialize (labels/types/choices are provided by the caller, not the component):
 *    window.CRM.settingsPanel.init({
 *        container: '#settingsPanel',
 *        title: 'Financial Settings',
 *        icon: 'fa-solid fa-sliders',
 *        settings: [
 *            { name: 'iFYMonth', type: 'choice', label: 'Fiscal Year Month', choices: [...] },
 *            { name: 'bEnableNonDeductible', type: 'boolean', label: 'Non-deductible' }
 *        ],
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
        const isOn = value === "1" || value === "true" || value === true;
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="form-label small fw-bold mb-1">
                            ${escapeHtml(resolve(setting.label))}
                            ${setting.tooltip ? `<i class="fa-solid fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" data-placement="top" title="${escapeHtml(resolve(setting.tooltip))}"></i>` : ""}
                        </div>
                        <div class="form-selectgroup form-selectgroup-pills">
                            <label class="form-selectgroup-item">
                                <input type="radio" class="form-selectgroup-input setting-input"
                                       name="${setting.name}" value="1"
                                       data-type="boolean"
                                       ${isOn ? "checked" : ""}>
                                <span class="form-selectgroup-label">
                                    <i class="fa-solid fa-check me-1"></i>${t("Yes")}
                                </span>
                            </label>
                            <label class="form-selectgroup-item">
                                <input type="radio" class="form-selectgroup-input setting-input"
                                       name="${setting.name}" value="0"
                                       data-type="boolean"
                                       ${!isOn ? "checked" : ""}>
                                <span class="form-selectgroup-label">
                                    <i class="fa-solid fa-xmark me-1"></i>${t("No")}
                                </span>
                            </label>
                        </div>
                    </div>
                `;
      },
      getValue: function (el) {
        // Radio inputs: only the checked one returns a value; unchecked returns null (skipped in save)
        if (el.type === "radio") return el.checked ? el.value : null;
        return el.checked ? "1" : "0";
      },
    },
    number: {
      render: function (setting, value) {
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <label for="${setting.name}" class="form-label small fw-bold mb-1">
                            ${escapeHtml(resolve(setting.label))}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-info ms-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <input type="number" class="form-control setting-input"
                               id="${setting.name}" name="${setting.name}"
                               data-type="number"
                               value="${value || ""}"
                               ${setting.min !== undefined ? `min="${setting.min}"` : ""}
                               ${setting.max !== undefined ? `max="${setting.max}"` : ""}>
                        ${setting.tooltip ? `<small class="form-text text-muted">${escapeHtml(resolve(setting.tooltip))}</small>` : ""}
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
                        <label for="${setting.name}" class="form-label small fw-bold mb-1">
                            ${escapeHtml(resolve(setting.label))}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-info ms-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <input type="text" class="form-control setting-input"
                               id="${setting.name}" name="${setting.name}"
                               data-type="text"
                               value="${escapeHtml(value || "")}"
                               ${setting.placeholder ? `placeholder="${setting.placeholder}"` : ""}>
                        ${setting.tooltip ? `<small class="form-text text-muted">${escapeHtml(resolve(setting.tooltip))}</small>` : ""}
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
            optionsHtml += `<option value="${escapeHtml(optValue)}" ${selected}>${escapeHtml(resolve(optLabel))}</option>`;
          });
        }
        return `
                    <div class="col-md-6 col-lg-4 mb-3">
                        <label for="${setting.name}" class="form-label small fw-bold mb-1">
                            ${escapeHtml(resolve(setting.label))}
                            ${setting.helpLink ? `<a href="${setting.helpLink}" target="_blank" class="text-muted ms-1"><i class="fa-solid fa-circle-question"></i></a>` : ""}
                        </label>
                        <select class="form-select setting-input"
                                id="${setting.name}" name="${setting.name}"
                                data-type="choice">
                            ${optionsHtml}
                        </select>
                        ${setting.tooltip ? `<small class="form-text text-muted">${escapeHtml(resolve(setting.tooltip))}</small>` : ""}
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
        const generateBtn = setting.generate
          ? `<button type="button" class="btn btn-outline-secondary btn-sm generate-password-btn" data-target="${setting.name}">
               <i class="fa-solid fa-key me-1"></i>${t("Generate")}
             </button>`
          : "";
        return `
            <div class="col-md-6 col-lg-4 mb-3">
              <label for="${setting.name}" class="form-label small fw-bold mb-1">
                ${escapeHtml(resolve(setting.label))}
              </label>
              <div class="input-group">
                <input type="password" class="form-control setting-input"
                     id="${setting.name}" name="${setting.name}"
                     data-type="password"
                     autocomplete="new-password"
                     placeholder="${t("Leave blank to keep existing")}">
                ${generateBtn}
              </div>
              ${setting.tooltip ? `<small class="form-text text-muted">${escapeHtml(resolve(setting.tooltip))}</small>` : ""}
            </div>
          `;
      },
      getValue: function (el) {
        // Return null for empty passwords so they are skipped in the bulk save
        return el.value || null;
      },
    },
    ajax: {
      render: function (setting) {
        return `
            <div class="col-md-6 col-lg-4 mb-3">
              <label for="${setting.name}" class="form-label small fw-bold mb-1">
                ${escapeHtml(resolve(setting.label))}
              </label>
              <select class="form-select setting-input"
                      id="${setting.name}" name="${setting.name}"
                      data-type="ajax"
                      data-ajax-url="${escapeHtml(setting.ajaxUrl || "")}">
                <option value="">${t("Unassigned")}</option>
              </select>
              ${setting.tooltip ? `<small class="form-text text-muted">${escapeHtml(resolve(setting.tooltip))}</small>` : ""}
            </div>
          `;
      },
      getValue: function (el) {
        return el.value;
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

  // Translate a key at call-time. Falls back to the raw key if i18next is not
  // yet initialised — so labels always show something meaningful.
  function t(key) {
    if (!key) return key;
    if (window.i18next) {
      const translated = i18next.t(key);
      return translated !== undefined ? translated : key;
    }
    return key;
  }

  // Safely resolve a setting label or tooltip, returning "" for falsy values.
  function resolve(value) {
    return value || "";
  }

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
     * @param {string} options.headerClass - CSS class for header (default: bg-primary-lt)
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
          allSettingsUrl: "/SystemSettings.php",
          configApiPath: "/admin/api/system/config",
          headerClass: "bg-primary-lt",
        },
        options,
      );

      this.container = document.querySelector(this.options.container);
      if (!this.container) {
        console.error("Settings panel container not found:", this.options.container);
        return;
      }

      // Defer rendering until translations are loaded so that i18next.t() calls
      // in component-own strings (Yes/No/Save) return translated values.
      // If locale infrastructure is not present, init immediately.
      if (!window.CRM || !window.i18next || window.CRM.localesLoaded) {
        this._doInit();
      } else {
        window.addEventListener("CRM.localesReady", () => this._doInit(), { once: true });
      }
    }

    _doInit() {
      // Render the panel structure so the collapse container is not empty
      // when the user opens it. Values are fetched from the API afterwards
      // without replacing innerHTML (no animation interruption).
      this.render();
      this.bindEvents();
      this.initialized = true;
      this.fetchAndApplyValues();
    }

    // Fetch current values from API and update each input individually
    fetchAndApplyValues() {
      const self = this;
      // Password fields never show their current value
      const settingsToFetch = this.options.settings.filter((s) => {
        const cfg = this.getSettingConfig(s);
        return cfg.type !== "password";
      });

      settingsToFetch.forEach((s) => {
        const name = typeof s === "string" ? s : s.name;
        const cfg = this.getSettingConfig(s);

        fetch(window.CRM.root + self.options.configApiPath + "/" + name)
          .then((response) => response.json())
          .then((data) => {
            // For ajax selects, load options from the remote URL first
            if (cfg.type === "ajax" && cfg.ajaxUrl) {
              self.loadAjaxOptions(name, cfg.ajaxUrl, data.value);
            } else {
              self.applyValue(name, data.value);
            }
          })
          .catch(() => {
            console.warn("Could not load setting:", name);
          });
      });
    }

    // Load options for an ajax-type select from a remote URL, then set the value
    loadAjaxOptions(name, url, currentValue) {
      const select = this.container.querySelector('select[name="' + name + '"]');
      if (!select) return;

      fetch(window.CRM.root + url)
        .then((response) => response.json())
        .then((options) => {
          options.forEach((opt) => {
            const option = document.createElement("option");
            option.value = opt.id;
            option.textContent = opt.value;
            if (String(opt.id) === String(currentValue)) {
              option.selected = true;
            }
            select.appendChild(option);
          });
        })
        .catch(() => {
          console.warn("Could not load ajax options for:", name);
        });
    }

    // Update a single input (or radio group) without re-rendering the whole panel
    applyValue(name, value) {
      const inputs = this.container.querySelectorAll('[name="' + name + '"]');
      if (inputs.length === 0) return;

      if (inputs.length > 1) {
        // Radio group (boolean pills): check the radio whose value matches
        const normalized = value === "1" || value === true || value === "true" ? "1" : "0";
        inputs.forEach(function (input) {
          input.checked = input.value === normalized;
        });
        return;
      }

      const input = inputs[0];
      if (input.dataset.type === "boolean") {
        input.checked = value === "1" || value === "true" || value === true;
      } else {
        input.value = value != null ? value : "";
      }
    }

    // Render the panel HTML (inputs start with empty/unchecked values;
    // fetchAndApplyValues() fills them in after the API responds)
    render() {
      let settingsHtml = "";

      this.options.settings.forEach((setting) => {
        const settingConfig = this.getSettingConfig(setting);

        const renderer = SettingTypes[settingConfig.type];
        if (renderer) {
          settingsHtml += renderer.render(settingConfig, "");
        }
      });

      // Presets bar
      let presetsHtml = "";
      if (this.options.presets && this.options.presets.length > 0) {
        const presetButtons = this.options.presets
          .map(
            (p, i) =>
              `<button type="button" class="btn btn-sm btn-outline-primary preset-btn" data-preset-index="${i}">
                ${p.icon ? `<i class="${p.icon} me-1"></i>` : ""}${escapeHtml(p.label)}
              </button>`,
          )
          .join("");
        presetsHtml = `
          <div class="mb-3">
            <small class="text-muted fw-bold d-block mb-1">${t("Quick Setup")}:</small>
            <div class="btn-list">${presetButtons}</div>
          </div>
          <hr class="my-3">`;
      }

      const html = `
                <div class="card settings-panel-card">
                    <div class="card-header ${this.options.headerClass} py-2">
                        <h6 class="mb-0">
                            <i class="${this.options.icon} me-1"></i>${resolve(this.options.title)}
                        </h6>
                    </div>
                    <div class="card-body">
                        <form id="settingsPanelForm">
                            ${presetsHtml}
                            <div class="row">
                                ${settingsHtml}
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center">
                                ${
                                  this.options.showAllSettingsLink
                                    ? `
                                <a href="${window.CRM.root}${this.options.allSettingsUrl}" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> ${t("All System Settings")}
                                </a>
                                `
                                    : "<div></div>"
                                }
                                <button type="button" id="settingsPanelSaveBtn" class="btn btn-primary">
                                    <i class="fa-solid fa-save me-1"></i> ${t("Save Settings")}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

      this.container.innerHTML = html;
    }

    // Get merged setting configuration from the caller-supplied object.
    // Falls back to { type: "text", label: name } when no config is provided.
    getSettingConfig(setting) {
      const name = typeof setting === "string" ? setting : setting.name;
      const customConfig = typeof setting === "object" ? setting : {};
      return Object.assign({ name: name, type: "text", label: name }, customConfig);
    }

    // Bind event handlers
    bindEvents() {
      const self = this;

      // Initialize Bootstrap tooltips on help icons
      if (window.$ && $.fn.tooltip) {
        $(this.container).find('[data-bs-toggle="tooltip"]').tooltip();
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

      // Preset buttons — fill form fields with predefined values
      this.container.querySelectorAll(".preset-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const idx = parseInt(btn.dataset.presetIndex, 10);
          const preset = self.options.presets && self.options.presets[idx];
          if (!preset || !preset.values) return;
          Object.keys(preset.values).forEach(function (key) {
            self.applyValue(key, preset.values[key]);
          });
          if (window.CRM && window.CRM.notify) {
            window.CRM.notify(t("Applied preset") + ": " + preset.label + ". " + t("Click Save to apply."), {
              type: "info",
              delay: 3000,
            });
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

      // Disable button and show loading
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> ' + t("Saving...");

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
        return fetch(window.CRM.root + self.options.configApiPath + "/" + key, {
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
