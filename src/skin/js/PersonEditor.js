$(document).ready(function () {
  // Initialize Country and State dropdowns using DropdownManager
  DropdownManager.initializeCountryState("Country", "State", {
    userSelected: $("#Country").data("user-selected"),
    systemDefault: $("#Country").data("system-default"),
    stateOptionDivId: "stateOptionDiv",
    stateInputDivId: "stateInputDiv",
    stateTextboxId: "StateTextbox",
  });

  // Toggle date-of-death picker visibility when the Deceased checkbox is changed
  $("#IsDeceased").on("change", function () {
    if ($(this).is(":checked")) {
      $("#DeceasedDateGroup").show();
    } else {
      $("#DeceasedDateGroup").hide();
      $("#DateDeceased").val("");
    }
  });
});
