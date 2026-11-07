window.CRM.onLocalesReady(() => {
  window.CRM.formUtils.initializePhoneMaskToggles([
    { checkboxName: "noFormatHomePhone", inputName: "homePhone" },
    { checkboxName: "noFormatWorkPhone", inputName: "workPhone" },
    { checkboxName: "noFormatCellPhone", inputName: "cellPhone" },
    ...(window.CRM.customPhoneFields || []),
  ]);
  $("[data-mask]").inputmask();
  const family = document.getElementById("familyId");
  if (family && !family.tomselect) new TomSelect(family);
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
      $("#DeceasedDateGroup").prop("hidden", false);
    } else {
      $("#DeceasedDateGroup").prop("hidden", true);
      $("#DateDeceased").val("");
    }
  });
});
