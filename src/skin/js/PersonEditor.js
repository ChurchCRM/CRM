$(document).ready(function () {
    // Initialize Country and State dropdowns using DropdownManager
    DropdownManager.initializeCountryState("Country", "State", {
        userSelected: $("#Country").data("user-selected"),
        systemDefault: $("#Country").data("system-default"),
        stateOptionDivId: "stateOptionDiv",
        stateInputDivId: "stateInputDiv",
        stateTextboxId: "StateTextbox",
    });
});
