/**
 * Dropdown Manager - Centralized country/state dropdown initialization
 * Used across PersonEditor, CartToFamily, CSVImport, FamilyEditor, and FamilyRegister
 */

class DropdownManager {
    /**
     * Initialize a country dropdown with API data
     * @param {string} countrySelectId - ID of the country select element
     * @param {string} stateSelectId - Optional ID of the state select element (for auto-cascade)
     * @param {Object} options - Configuration options
     * @param {string} options.userSelected - Pre-selected country value
     * @param {string} options.systemDefault - Default country from system config
     * @param {boolean} options.initSelect2 - Whether to initialize select2 (default: true)
     * @param {boolean} options.cascadeState - Whether to cascade to state select on change (default: false)
     * @param {Function} options.onCountryChange - Callback when country changes
     */
    static initializeCountry(countrySelectId, stateSelectId = null, options = {}) {
        const countrySelect = $(`#${countrySelectId}`);
        if (countrySelect.length === 0) return;

        const defaults = {
            userSelected: countrySelect.data("user-selected") || "",
            systemDefault: countrySelect.data("system-default") || "",
            initSelect2: true,
            cascadeState: !!stateSelectId,
            onCountryChange: null,
        };

        const config = { ...defaults, ...options };

        // Fetch and populate countries
        $.ajax({
            type: "GET",
            url: window.CRM.root + "/api/public/data/countries",
        }).done(function (data) {
            countrySelect.empty();

            $.each(data, function (idx, country) {
                let selected = false;

                if (config.userSelected === "") {
                    selected = config.systemDefault === country.name || config.systemDefault === country.code;
                } else {
                    selected = config.userSelected === country.name || config.userSelected === country.code;
                }

                countrySelect.append(new Option(country.name, country.code, selected, selected));
            });

            // Trigger change to cascade to state if needed
            countrySelect.change();

            if (config.initSelect2) {
                countrySelect.select2();
            }
        });

        // Handle cascading to state dropdown if configured
        if (config.cascadeState) {
            countrySelect.off("change").on("change", function () {
                DropdownManager.initializeState(stateSelectId, this.value.toLowerCase(), {
                    userSelected: $(`#${stateSelectId}`).data("user-selected") || "",
                    systemDefault: $(`#${stateSelectId}`).data("system-default") || "",
                    initSelect2: true,
                    stateTextboxId: config.stateTextboxId,
                    stateOptionDivId: config.stateOptionDivId,
                    stateInputDivId: config.stateInputDivId,
                });

                if (config.onCountryChange) {
                    config.onCountryChange(this.value);
                }
            });
        }
    }

    /**
     * Initialize a state dropdown with API data
     * @param {string} stateSelectId - ID of the state select element
     * @param {string} countryCode - Country code to fetch states for
     * @param {Object} options - Configuration options
     * @param {string} options.userSelected - Pre-selected state value
     * @param {string} options.systemDefault - Default state from system config
     * @param {boolean} options.initSelect2 - Whether to initialize select2 (default: true)
     * @param {string} options.stateTextboxId - Optional ID of textbox fallback for countries without states
     * @param {string} options.stateOptionDivId - Optional ID of div to show/hide for state dropdown
     * @param {string} options.stateInputDivId - Optional ID of div to show/hide for state textbox
     */
    static initializeState(stateSelectId, countryCode, options = {}) {
        const stateSelect = $(`#${stateSelectId}`);
        if (stateSelect.length === 0) return;

        const defaults = {
            userSelected: stateSelect.data("user-selected") || "",
            systemDefault: stateSelect.data("system-default") || "",
            initSelect2: true,
            stateTextboxId: null,
            stateOptionDivId: null,
            stateInputDivId: null,
        };

        const config = { ...defaults, ...options };

        // Fetch and populate states
        $.ajax({
            type: "GET",
            url: window.CRM.root + "/api/public/data/countries/" + countryCode + "/states",
        })
            .done(function (data) {
                if (Object.keys(data).length > 0) {
                    // Country has states - populate dropdown
                    stateSelect.empty();

                    $.each(data, function (code, name) {
                        let selected = false;

                        if (config.userSelected === "") {
                            selected = config.systemDefault === name || config.systemDefault === code;
                        } else {
                            selected = config.userSelected === name || config.userSelected === code;
                        }

                        stateSelect.append(new Option(name, code, selected, selected));
                    });

                    stateSelect.change();

                    if (config.initSelect2) {
                        stateSelect.select2();
                    }

                    // Show state dropdown, hide textbox fallback
                    if (config.stateOptionDivId) {
                        $(`#${config.stateOptionDivId}`).removeClass("d-none");
                    }
                    if (config.stateInputDivId) {
                        $(`#${config.stateInputDivId}`).addClass("d-none");
                    }
                    if (config.stateTextboxId) {
                        $(`#${config.stateTextboxId}`).val("");
                    }
                } else {
                    // Country has no states - show textbox instead
                    if (config.stateOptionDivId) {
                        $(`#${config.stateOptionDivId}`).addClass("d-none");
                    }
                    if (config.stateInputDivId) {
                        $(`#${config.stateInputDivId}`).removeClass("d-none");
                    }
                }
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                window.CRM.notify(
                    i18next.t("Unable to load state list. Please check your network connection or try again later."),
                    { type: "error", delay: 5000 },
                );
                // Optionally, show textbox fallback if config.stateInputDivId is set
                if (config.stateOptionDivId) {
                    $(`#${config.stateOptionDivId}`).addClass("d-none");
                }
                if (config.stateInputDivId) {
                    $(`#${config.stateInputDivId}`).removeClass("d-none");
                }
            });
    }

    /**
     * Initialize both country and state dropdowns with cascading
     * @param {string} countrySelectId - ID of the country select element
     * @param {string} stateSelectId - ID of the state select element
     * @param {Object} options - Configuration options (merged with country and state options)
     */
    static initializeCountryState(countrySelectId, stateSelectId, options = {}) {
        // Initialize country with state cascading
        this.initializeCountry(countrySelectId, stateSelectId, {
            userSelected: options.userSelected || $(`#${countrySelectId}`).data("user-selected") || "",
            systemDefault: options.systemDefault || $(`#${countrySelectId}`).data("system-default") || "",
            initSelect2: true,
            cascadeState: true,
            ...options,
        });
    }

    /**
     * Initialize FamilyRegister-style country/state with dynamic container
     * @param {string} countrySelectId - ID of the country select element
     * @param {string} stateContainerId - ID of the container div for state field
     * @param {string} stateFieldId - ID of the state field (input or select)
     * @param {Object} options - Configuration options
     */
    static initializeFamilyRegisterCountryState(countrySelectId, stateContainerId, stateFieldId, options = {}) {
        const countrySelect = $(`#${countrySelectId}`);
        const stateContainer = $(`#${stateContainerId}`);

        if (countrySelect.length === 0 || stateContainer.length === 0) return;

        const defaults = {
            userSelected: countrySelect.data("user-selected") || "",
            systemDefault: countrySelect.data("system-default") || "",
            stateDefault: $(`#${stateFieldId}`).data("default") || "",
        };

        const config = { ...defaults, ...options };

        // Initialize country
        $.ajax({
            type: "GET",
            url: window.CRM.root + "/api/public/data/countries",
        }).done(function (data) {
            countrySelect.empty();

            $.each(data, function (idx, country) {
                let selected = false;

                if (config.userSelected === "") {
                    selected = config.systemDefault === country.name || config.systemDefault === country.code;
                } else {
                    selected = config.userSelected === country.name || config.userSelected === country.code;
                }

                countrySelect.append(new Option(country.name, country.code, selected, selected));
            });

            countrySelect.change();
            countrySelect.select2();
        });

        // Handle country change
        countrySelect.off("change").on("change", function () {
            $.ajax({
                type: "GET",
                url: window.CRM.root + "/api/public/data/countries/" + this.value.toLowerCase() + "/states",
            }).done(function (data) {
                const defaultState = config.stateDefault || "";

                if (Object.keys(data).length > 0) {
                    // Country has states - show dropdown
                    const $select = $(
                        `<select id="${stateFieldId}" name="${stateFieldId.replace(/^[^-]+_/, "")}" class="form-control" data-default="${defaultState}"></select>`,
                    );

                    $.each(data, function (code, name) {
                        const $option = $("<option></option>").val(code).text(name);
                        if (defaultState === code || defaultState === name) {
                            $option.prop("selected", true);
                        }
                        $select.append($option);
                    });

                    stateContainer.html($select);
                    $select.select2();
                } else {
                    // Country has no states - show text input
                    const $input = $(
                        `<input type="text" id="${stateFieldId}" name="${stateFieldId.replace(/^[^-]+_/, "")}" class="form-control" data-default="${defaultState}">`,
                    );
                    if (defaultState) {
                        $input.val(defaultState);
                    }
                    stateContainer.html($input);
                }
            });
        });
    }
}

// jQuery-style plugin initialization shorthand
$.fn.initializeCountryDropdown = function (options) {
    this.each(function () {
        DropdownManager.initializeCountry($(this).attr("id"), null, options);
    });
    return this;
};

$.fn.initializeStateDropdown = function (countryCode, options) {
    this.each(function () {
        DropdownManager.initializeState($(this).attr("id"), countryCode, options);
    });
    return this;
};
