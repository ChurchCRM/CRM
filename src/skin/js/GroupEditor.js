$("document").ready(function () {
    $(".groupSpecificProperties").click(function (e) {
        var groupPropertyAction = e.currentTarget.id;
        if (groupPropertyAction === "enableGroupProps") {
            $("#groupSpecificPropertiesModal").modal("show");
            $("#gsproperties-label").text(
                i18next.t("Confirm Enable Group Specific Properties"),
            );
            $("#groupSpecificPropertiesModal .modal-body span").text(
                i18next.t(
                    "This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.",
                ),
            );
            $("#setgroupSpecificProperties").text(
                i18next.t("Enable Group Specific Properties"),
            );
            $("#setgroupSpecificProperties").data("action", 1);
        } else {
            $("#groupSpecificPropertiesModal").modal("show");
            $("#gsproperties-label").text(
                i18next.t("Confirm Disable Group Specific Properties"),
            );
            $("#groupSpecificPropertiesModal .modal-body span").text(
                i18next.t(
                    "Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!",
                ),
            );
            $("#setgroupSpecificProperties").text(
                i18next.t("Disable Group Specific Properties"),
            );
            $("#setgroupSpecificProperties").data("action", 0);
        }
    });

    $("#setgroupSpecificProperties").click(function (e) {
        var action = $("#setgroupSpecificProperties").data("action");
        $.ajax({
            method: "POST",
            url:
                window.CRM.root +
                "/api/groups/" +
                groupID +
                "/setGroupSpecificPropertyStatus",
            data: JSON.stringify({"GroupSpecificPropertyStatus": action}),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {
            location.reload(); // this shouldn't be necessary
        });
    });

    $("#selectGroupIDDiv").hide();
    $("#cloneGroupRole").click(function (e) {
        if (e.target.checked) $("#selectGroupIDDiv").show();
        else {
            $("#selectGroupIDDiv").hide();
            $("#seedGroupID").prop("selectedIndex", 0);
        }
    });

    $("#groupEditForm").submit(function (e) {
        e.preventDefault();

        var formData = {
            groupName: $("input[name='Name']").val(),
            description: $("textarea[name='Description']").val(),
            groupType: $("select[name='GroupType'] option:selected").val(),
        };
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/" + groupID,
            data: JSON.stringify(formData),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {
            if (data.groupType === i18next.t("Sunday School")) {
                window.location.href =
                    CRM.root + "/sundayschool/SundaySchoolDashboard.php";
            } else {
                window.location.href = CRM.root + "/GroupList.php";
            }
        });
    });

    $("#addNewRole").click(function (e) {
        var newRoleName = $("#newRole").val();

        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/" + groupID + "/roles",
            data: JSON.stringify({"roleName": newRoleName}),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {
            var newRole = data.newRole;
            var newRow = {
                lst_OptionName: newRole.roleName,
                lst_OptionID: newRole.roleID,
                lst_OptionSequence: newRole.sequence,
            };
            roleCount += 1;
            var node = dataT.row.add(newRow).node();
            dataT.rows().invalidate().draw(true);
            $("#newRole").val("");
            //location.reload(); // this shouldn't be necessary
        });
    });

    $(document).on("click", ".deleteRole", function (e) {
        var roleID = e.currentTarget.id.split("-")[1];
        $.ajax({
            method: "DELETE",
            url:
                window.CRM.root + "/api/groups/" + groupID + "/roles/" + roleID,
            encode: true,
            dataType: "json",
        }).done(function (data) {
            dataT.clear();
            dataT.rows.add(data);
            if (roleID == defaultRoleID)
                // if we delete the default group role, set the default group role to 1 before we tell the table to re-render so that the buttons work correctly
                defaultRoleID = 1;
            dataT.rows().invalidate().draw(true);
        });
    });

    $(document).on("click", ".rollOrder", function (e) {
        var roleID = e.currentTarget.id.split("-")[1]; // get the ID of the role that we're manipulating
        var roleSequenceAction = e.currentTarget.id.split("-")[0]; //determine whether we're increasing or decreasing this role's sequence number
        var newRoleSequence = 0; //create a variable at the function scope to store the new role's sequence
        var currentRoleSequence = dataT
            .cell(function (idx, data, node) {
                if (data.lst_OptionID == roleID) {
                    return true;
                }
            }, 2)
            .data(); //get the sequence number of the selected role
        if (roleSequenceAction === "roleUp") {
            newRoleSequence = Number(currentRoleSequence) - 1; //decrease the role's sequence number
        } else if (roleSequenceAction === "roleDown") {
            newRoleSequence = Number(currentRoleSequence) + 1; // increase the role's sequenc number
        }

        replaceRow = dataT.row(function (idx, data, node) {
            if (data.lst_OptionSequence == newRoleSequence) {
                return true;
            }
        });
        var d = replaceRow.data();
        d.lst_OptionSequence = currentRoleSequence;
        setGroupRoleOrder(groupID, d.lst_OptionID, d.lst_OptionSequence);
        replaceRow.data(d);

        dataT
            .cell(function (idx, data, node) {
                if (data.lst_OptionID == roleID) {
                    return true;
                }
            }, 2)
            .data(newRoleSequence); // set our role to the new sequence number
        setGroupRoleOrder(groupID, roleID, newRoleSequence);
        dataT.rows().invalidate().draw(true);
        dataT.order([[2, "asc"]]).draw();
    });

    $(document).on("change", ".roleName", function (e) {
        var groupRoleName = e.target.value;
        var roleID = e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url:
                window.CRM.root + "/api/groups/" + groupID + "/roles/" + roleID,
                data: JSON.stringify({"groupRoleName": groupRoleName}),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {});
    });

    $(document).on("click", ".defaultRole", function (e) {
        var roleID = e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url: window.CRM.root + "/api/groups/" + groupID + "/defaultRole",
            data: JSON.stringify({"roleID": roleID}),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        }).done(function (data) {
            defaultRoleID = roleID; //update the local variable representing the default role id
            dataT.rows().invalidate().draw(true);
            // re-register the JQuery handlers since we changed the DOM, and new buttons will not have an action bound.
        });
    });

    var dataTableConfig = {
        data: groupRoleData,
        columns: [
            {
                width: "auto",
                title: i18next.t("Role Name"),
                data: "lst_OptionName",
                render: function (data, type, full, meta) {
                    if (type === "display") {
                        if (data === "Student" || data === "Teacher")
                            return (
                                '<input type="text" class="roleName" id="roleName-' +
                                full.lst_OptionID +
                                '" value="' +
                                i18next.t(data) +
                                '" readonly>'
                            );
                        else
                            return (
                                '<input type="text" class="roleName" id="roleName-' +
                                full.lst_OptionID +
                                '" value="' +
                                data +
                                '">'
                            );
                    } else return data;
                },
            },
            {
                width: "auto",
                title: i18next.t("Make Default"),
                render: function (data, type, full, meta) {
                    if (full.lst_OptionID == defaultRoleID) {
                        return (
                            '<strong><i class="fa fa-check"></i>' +
                            i18next.t("Default") +
                            "</strong>"
                        );
                    } else {
                        return (
                            '<button type="button" id="defaultRole-' +
                            full.lst_OptionID +
                            '" class="btn btn-success defaultRole">' +
                            i18next.t("Default") +
                            "</button>"
                        );
                    }
                },
            },
            {
                width: "200px",
                title: i18next.t("Sequence"),
                data: "lst_OptionSequence",
                className: "dt-body-center",
                render: function (data, type, full, meta) {
                    if (type === "display") {
                        var sequenceCell = "";
                        if (data > 1) {
                            sequenceCell +=
                                '<button type="button" id="roleUp-' +
                                full.lst_OptionID +
                                '" class="btn rollOrder"> <i class="fa fa-arrow-up"></i></button>&nbsp;';
                        }
                        sequenceCell += data;
                        if (data != roleCount) {
                            sequenceCell +=
                                '&nbsp;<button type="button" id="roleDown-' +
                                full.lst_OptionID +
                                '" class="btn rollOrder"> <i class="fa fa-arrow-down"></i></button>';
                        }
                        return sequenceCell;
                    } else {
                        return data;
                    }
                },
            },
            {
                width: "auto",
                title: i18next.t("Delete"),
                render: function (data, type, full, meta) {
                    if (
                        full.lst_OptionName === "Student" ||
                        full.lst_OptionName === "Teacher"
                    )
                        return (
                            '<button type="button" id="roleDelete-' +
                            full.lst_OptionID +
                            '" class="btn btn-danger deleteRole" disabled>' +
                            i18next.t("Delete") +
                            "</button>"
                        );
                    else
                        return (
                            '<button type="button" id="roleDelete-' +
                            full.lst_OptionID +
                            '" class="btn btn-danger deleteRole">' +
                            i18next.t("Delete") +
                            "</button>"
                        );
                },
            },
        ],
        order: [[3, "asc"]],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#groupRoleTable").DataTable(dataTableConfig);

    // initialize the event handlers when the document is ready.  Don't do it here, since we need to be able to initialize these handlers on the fly in response to user action.
});

function setGroupRoleOrder(groupID, roleID, groupRoleOrder) {
    $.ajax({
        method: "POST",
        url: window.CRM.root + "/api/groups/" + groupID + "/roles/" + roleID,
        data: JSON.stringify({"groupRoleOrder": groupRoleOrder}),
        contentType: "application/json; charset=utf-8",
        dataType: "json",
    }).done(function (data) {});
}
