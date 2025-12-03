$(document).ready(function () {
    $.ajax({
        type: "GET",
        url: window.CRM.root + "/api/public/data/countries",
    }).done(function (data) {
        let familyCountry = $("#Country");
        $.each(data, function (idx, country) {
            let selected = false;
            if (familyCountry.data("user-selected") == "") {
                selected = familyCountry.data("system-default") == country.name;
            } else if (
                familyCountry.data("user-selected") == country.name ||
                familyCountry.data("user-selected") == country.code
            ) {
                selected = true;
            }
            familyCountry.append(new Option(country.name, country.code, selected, selected));
        });
        familyCountry.change();
    });

    $("#Country").change(function () {
        $.ajax({
            type: "GET",
            url: window.CRM.root + "/api/public/data/countries/" + this.value.toLowerCase() + "/states",
        }).done(function (data) {
            let stateSelect = $("#State");
            if (Object.keys(data).length > 0) {
                stateSelect.empty();
                $.each(data, function (code, name) {
                    let selected = false;
                    if (stateSelect.data("user-selected") == "") {
                        selected = stateSelect.data("system-default") == name;
                    } else if (stateSelect.data("user-selected") == name || stateSelect.data("user-selected") == code) {
                        selected = true;
                    }
                    stateSelect.append(new Option(name, code, selected, selected));
                });
                stateSelect.change();
                $("#stateInputDiv").addClass("d-none");
                $("#StateTextbox").val("");
                $("#stateType").val("dropDown");
                $("#stateOptionDiv").removeClass("d-none");
            } else {
                $("#stateInputDiv").removeClass("d-none");
                $("#stateOptionDiv").addClass("d-none");
                $("#stateType").val("input");
            }
        });
    });

    $("[data-mask]").inputmask();
    $("#Country").select2();
    $("#State").select2();

    // Add Family Member Row functionality
    if (window.CRM.initialFamilyMemberCount !== undefined) {
        let rowCount = window.CRM.initialFamilyMemberCount;

        $("#addFamilyMemberRow").click(function () {
            rowCount++;

            // Build role options
            let roleOptions = '<option value="0">' + window.CRM.i18n.selectRole + "</option>";
            window.CRM.familyRoles.forEach(function (role) {
                roleOptions += '<option value="' + role.id + '">' + role.name + "</option>";
            });

            // Build month options
            let monthOptions = '<option value="0">' + window.CRM.i18n.unknown + "</option>";
            for (let m = 1; m <= 12; m++) {
                let monthVal = m < 10 ? "0" + m : m;
                monthOptions += '<option value="' + monthVal + '">' + window.CRM.i18n.months[m - 1] + "</option>";
            }

            // Build day options
            let dayOptions = '<option value="0">' + window.CRM.i18n.unknown + "</option>";
            for (let d = 1; d <= 31; d++) {
                let dayVal = d < 10 ? "0" + d : d;
                dayOptions += '<option value="' + dayVal + '">' + d + "</option>";
            }

            // Build classification options
            let classOptions = '<option value="0">' + window.CRM.i18n.unassigned + "</option>";
            classOptions += '<option value="" disabled>-----------------------</option>';
            window.CRM.classifications.forEach(function (cls) {
                classOptions += '<option value="' + cls.id + '">' + cls.name + "</option>";
            });

            let newRow = `
                <tr>
                    <td>
                        <input type="hidden" name="PersonID${rowCount}" value="">
                        <input name="FirstName${rowCount}" type="text" value="" class="form-control form-control-sm">
                    </td>
                    <td><input name="MiddleName${rowCount}" type="text" value="" class="form-control form-control-sm"></td>
                    <td><input name="LastName${rowCount}" type="text" value="" class="form-control form-control-sm"></td>
                    <td><input name="Suffix${rowCount}" type="text" value="" class="form-control form-control-sm" style="width: 60px;"></td>
                    <td>
                        <select name="Gender${rowCount}" class="form-control form-control-sm">
                            <option value="0">${window.CRM.i18n.selectGender}</option>
                            <option value="1">${window.CRM.i18n.male}</option>
                            <option value="2">${window.CRM.i18n.female}</option>
                        </select>
                    </td>
                    <td><select name="Role${rowCount}" class="form-control form-control-sm">${roleOptions}</select></td>
                    <td><select name="BirthMonth${rowCount}" class="form-control form-control-sm">${monthOptions}</select></td>
                    <td><select name="BirthDay${rowCount}" class="form-control form-control-sm">${dayOptions}</select></td>
                    <td><input name="BirthYear${rowCount}" type="text" value="" class="form-control form-control-sm" style="width: 70px;" maxlength="4"></td>
                    <td><select name="Classification${rowCount}" class="form-control form-control-sm">${classOptions}</select></td>
                </tr>
            `;

            $("#familyMembersTbody").append(newRow);

            // Update FamCount hidden field
            $("input[name='FamCount']").val(rowCount);
        });
    }
});
