window.CRM.onLocalesReady(() => {
  // Initialize Country and State dropdowns using DropdownManager
  DropdownManager.initializeCountryState("Country", "State", {
    userSelected: $("#Country").data("user-selected"),
    systemDefault: $("#Country").data("system-default"),
    stateOptionDivId: "stateOptionDiv",
    stateInputDivId: "stateInputDiv",
    stateTextboxId: "StateTextbox",
  });

  // Update state type field when state dropdown visibility changes
  $(document).on("change", "#State", () => {
    const stateSelect = $("#State");
    if (stateSelect.find("option").length > 1) {
      $("#stateType").val("dropDown");
    } else {
      $("#stateType").val("input");
    }
  });

  // Optional second address (#9743) — same country/state cascade as the primary
  // address. The country select has no blank option, so it falls back to the
  // installation default; people/family/editor discards the whole block on save when
  // no street line or city was entered, so a blank second address stays blank.
  DropdownManager.initializeCountryState("SecondCountry", "SecondState", {
    userSelected: $("#SecondCountry").data("user-selected"),
    systemDefault: $("#SecondCountry").data("system-default"),
    stateOptionDivId: "secondStateOptionDiv",
    stateInputDivId: "secondStateInputDiv",
    stateTextboxId: "SecondStateTextbox",
  });

  $(document).on("change", "#SecondState", () => {
    const secondStateSelect = $("#SecondState");
    if (secondStateSelect.find("option").length > 1) {
      $("#secondStateType").val("dropDown");
    } else {
      $("#secondStateType").val("input");
    }
  });

  // Convenience only — the server enforces the same rule. The flag is meaningless
  // until a second address line or city has been entered.
  const syncSecondIsMailingState = () => {
    const hasSecondAddress = $("#SecondAddress1").val().trim().length > 0 || $("#SecondCity").val().trim().length > 0;
    const $flag = $("#SecondIsMailing");
    $flag.prop("disabled", !hasSecondAddress);
    if (!hasSecondAddress) {
      $flag.prop("checked", false);
    }
  };
  if ($("#SecondIsMailing").length > 0) {
    $(document).on("input change", "#SecondAddress1, #SecondCity", syncSecondIsMailingState);
    syncSecondIsMailingState();
  }

  // Initialize phone mask toggles FIRST, before applying masks globally
  // This ensures phone fields with "No format" checked don't get masked
  window.CRM.formUtils.initializePhoneMaskToggles([
    { checkboxName: "noFormatHomePhone", inputName: "homePhone" },
    ...(window.CRM.customPhoneFields || []),
  ]);

  // Apply inputmask to non-phone fields (fields without a "No format" checkbox)
  $("[data-mask]").inputmask();

  // Add Family Member Row functionality
  if (window.CRM.initialFamilyMemberCount !== undefined) {
    let rowCount = window.CRM.initialFamilyMemberCount;

    $("#addFamilyMemberRow").click(() => {
      rowCount++;

      // Build role options
      let roleOptions = '<option value="0">' + window.CRM.i18n.selectRole + "</option>";
      window.CRM.familyRoles.forEach((role) => {
        roleOptions += '<option value="' + role.id + '">' + role.name + "</option>";
      });

      // Build month options
      let monthOptions = '<option value="0">' + window.CRM.i18n.unknown + "</option>";
      for (let m = 1; m <= 12; m++) {
        const monthVal = m < 10 ? "0" + m : m;
        monthOptions += '<option value="' + monthVal + '">' + window.CRM.i18n.months[m - 1] + "</option>";
      }

      // Build day options
      let dayOptions = '<option value="0">' + window.CRM.i18n.unknown + "</option>";
      for (let d = 1; d <= 31; d++) {
        const dayVal = d < 10 ? "0" + d : d;
        dayOptions += '<option value="' + dayVal + '">' + d + "</option>";
      }

      // Build classification options
      let classOptions = '<option value="0">' + window.CRM.i18n.unassigned + "</option>";
      classOptions += '<option value="" disabled>-----------------------</option>';
      window.CRM.classifications.forEach((cls) => {
        classOptions += '<option value="' + cls.id + '">' + cls.name + "</option>";
      });

      let gradeOptions = '<option value="">' + window.CRM.i18n.unassigned + "</option>";
      (window.CRM.grades || []).forEach((grade) => {
        gradeOptions += '<option value="' + Number(grade.id) + '">' + window.CRM.escapeHtml(grade.name) + "</option>";
      });

      const newRow = `
                <tr>
                    <td>
                        <input type="hidden" name="personId${rowCount}" value="">
                        <input name="firstName${rowCount}" type="text" value="" class="form-control form-control-sm">
                    </td>
                    <td><input name="middleName${rowCount}" type="text" value="" class="form-control form-control-sm"></td>
                    <td><input name="lastName${rowCount}" type="text" value="" class="form-control form-control-sm"></td>
                    <td><input name="suffix${rowCount}" type="text" value="" class="form-control form-control-sm family-suffix-input"></td>
                    <td>
                        <select name="gender${rowCount}" class="form-control form-control-sm">
                            <option value="0">${window.CRM.i18n.selectGender}</option>
                            <option value="1">${window.CRM.i18n.male}</option>
                            <option value="2">${window.CRM.i18n.female}</option>
                        </select>
                    </td>
                    <td><select name="role${rowCount}" class="form-control form-control-sm">${roleOptions}</select></td>
                    <td><select name="birthMonth${rowCount}" class="form-control form-control-sm">${monthOptions}</select></td>
                    <td><select name="birthDay${rowCount}" class="form-control form-control-sm">${dayOptions}</select></td>
                    <td><input name="birthYear${rowCount}" type="text" value="" class="form-control form-control-sm family-birth-year-input" maxlength="4"></td>
                    <td><select name="classification${rowCount}" class="form-control form-control-sm">${classOptions}</select></td>
                    <td><select name="grade${rowCount}" class="form-control form-control-sm">${gradeOptions}</select></td>
                </tr>
            `;

      $("#familyMembersTbody").append(newRow);

      // Update FamCount hidden field
      $("input[name='famCount']").val(rowCount);
    });
  }
});
