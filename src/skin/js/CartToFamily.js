$(document).ready(function () {
    // Initialize Country and State dropdowns using DropdownManager
    DropdownManager.initializeCountryState("Country", "State", {
        userSelected: $("#Country").data("user-selected"),
        systemDefault: $("#Country").data("system-default"),
        stateOptionDivId: "stateOptionDiv",
        stateInputDivId: "stateInputDiv",
        stateTextboxId: "StateTextbox",
    });

    // Initialize phone mask toggles
    window.CRM.formUtils.initializePhoneMaskToggles([{ checkboxName: "NoFormat_HomePhone", inputName: "HomePhone" }]);
});
