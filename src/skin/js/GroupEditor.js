function initializeGroupEditor() {
    $(".groupSpecificProperties").click((e) => {
        const groupPropertyAction = e.currentTarget.id;
        if (groupPropertyAction === "enableGroupProps") {
            $("#groupSpecificPropertiesModal").modal("show");
            $("#gsproperties-label").text(i18next.t("Confirm Enable Group Specific Properties"));
            $("#groupSpecificPropertiesModal .modal-body span").text(
                i18next.t(
                    "This will create a group-specific properties table for this group.  You should then add needed properties with the Group-Specific Properties Form Editor.",
                ),
            );
            $("#setgroupSpecificProperties").text(i18next.t("Enable Group Specific Properties"));
            $("#setgroupSpecificProperties").data("action", 1);
        } else {
            $("#groupSpecificPropertiesModal").modal("show");
            $("#gsproperties-label").text(i18next.t("Confirm Disable Group Specific Properties"));
            $("#groupSpecificPropertiesModal .modal-body span").text(
                i18next.t(
                    "Are you sure you want to remove the group-specific person properties?  All group member properties data will be lost!",
                ),
            );
            $("#setgroupSpecificProperties").text(i18next.t("Disable Group Specific Properties"));
            $("#setgroupSpecificProperties").data("action", 0);
        }
    });

    $("#setgroupSpecificProperties").click((e) => {
        const action = $("#setgroupSpecificProperties").data("action");
        $.ajax({
            method: "POST",
            url: `${window.CRM.root}/api/groups/${groupID}/setGroupSpecificPropertyStatus`,
            data: JSON.stringify({ GroupSpecificPropertyStatus: action }),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        })
            .done((data) => {
                location.reload();
            })
            .fail((xhr, status, error) => {
                console.error("Failed to set group specific property status:", error);
                window.CRM.notify(i18next.t("Failed to update properties. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    $("#selectGroupIDDiv").hide();
    $("#cloneGroupRole").click((e) => {
        if (e.target.checked) {
            $("#selectGroupIDDiv").show();
        } else {
            $("#selectGroupIDDiv").hide();
            $("#seedGroupID").prop("selectedIndex", 0);
        }
    });

    $("#groupEditForm").submit((e) => {
        e.preventDefault();

        const formData = {
            groupName: $("input[name='Name']").val(),
            description: $("textarea[name='Description']").val(),
            groupType: $("select[name='GroupType'] option:selected").val(),
        };

        $.ajax({
            method: "POST",
            url: `${window.CRM.root}/api/groups/${groupID}`,
            data: JSON.stringify(formData),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        })
            .done((data) => {
                if (data.groupType === i18next.t("Sunday School")) {
                    window.location.href = `${window.CRM.root}/sundayschool/SundaySchoolDashboard.php`;
                } else {
                    window.location.href = `${window.CRM.root}/GroupList.php`;
                }
            })
            .fail((xhr, status, error) => {
                console.error("Failed to update group:", error);
                window.CRM.notify(i18next.t("Failed to update group. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    $("#addNewRole").click((e) => {
        const newRoleName = $("#newRole").val();

        $.ajax({
            method: "POST",
            url: `${window.CRM.root}/api/groups/${groupID}/roles`,
            data: JSON.stringify({ roleName: newRoleName }),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        })
            .done((data) => {
                const newRole = data.newRole;
                const newRow = {
                    lst_OptionName: newRole.roleName,
                    lst_OptionID: newRole.roleID,
                    lst_OptionSequence: newRole.sequence,
                };
                roleCount += 1;
                dataT.row.add(newRow);
                dataT.rows().invalidate().draw(true);
                $("#newRole").val("");
                window.CRM.notify(i18next.t("Role added successfully."), {
                    type: "success",
                    delay: 3000,
                });
            })
            .fail((xhr, status, error) => {
                console.error("Failed to add new role:", error);
                window.CRM.notify(i18next.t("Failed to add role. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    $(document).on("click", ".deleteRole", (e) => {
        const roleID = e.currentTarget.id.split("-")[1];
        $.ajax({
            method: "DELETE",
            url: `${window.CRM.root}/api/groups/${groupID}/roles/${roleID}`,
            encode: true,
            dataType: "json",
        })
            .done((data) => {
                dataT.clear();
                dataT.rows.add(data);
                // If we delete the default group role, set the default group role to 1 before re-rendering
                if (roleID == defaultRoleID) {
                    defaultRoleID = 1;
                }
                dataT.rows().invalidate().draw(true);
                window.CRM.notify(i18next.t("Role deleted successfully."), {
                    type: "success",
                    delay: 3000,
                });
            })
            .fail((xhr, status, error) => {
                console.error("Failed to delete role:", error);
                window.CRM.notify(i18next.t("Failed to delete role. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    $(document).on("click", ".rollOrder", (e) => {
        const roleID = e.currentTarget.id.split("-")[1];
        const roleSequenceAction = e.currentTarget.id.split("-")[0];
        let newRoleSequence = 0;

        const currentRoleSequence = dataT
            .cell((idx, data, node) => {
                return data.lst_OptionID == roleID;
            }, 2)
            .data();

        if (roleSequenceAction === "roleUp") {
            newRoleSequence = Number(currentRoleSequence) - 1;
        } else if (roleSequenceAction === "roleDown") {
            newRoleSequence = Number(currentRoleSequence) + 1;
        }

        const replaceRow = dataT.row((idx, data, node) => {
            return data.lst_OptionSequence == newRoleSequence;
        });

        const d = replaceRow.data();
        d.lst_OptionSequence = currentRoleSequence;
        setGroupRoleOrder(groupID, d.lst_OptionID, d.lst_OptionSequence);
        replaceRow.data(d);

        dataT
            .cell((idx, data, node) => {
                return data.lst_OptionID == roleID;
            }, 2)
            .data(newRoleSequence);

        setGroupRoleOrder(groupID, roleID, newRoleSequence);
        dataT.rows().invalidate().draw(true);
        dataT.order([[2, "asc"]]).draw();
    });

    $(document).on("change", ".roleName", (e) => {
        const groupRoleName = e.target.value;
        const roleID = e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url: `${window.CRM.root}/api/groups/${groupID}/roles/${roleID}`,
            data: JSON.stringify({ groupRoleName }),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        })
            .done((data) => {
                // Role name updated successfully
                window.CRM.notify(i18next.t("Role name updated."), {
                    type: "success",
                    delay: 3000,
                });
            })
            .fail((xhr, status, error) => {
                console.error("Failed to update role name:", error);
                window.CRM.notify(i18next.t("Failed to update role name. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    $(document).on("click", ".defaultRole", (e) => {
        const roleID = e.target.id.split("-")[1];
        $.ajax({
            method: "POST",
            url: `${window.CRM.root}/api/groups/${groupID}/defaultRole`,
            data: JSON.stringify({ roleID }),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
        })
            .done((data) => {
                defaultRoleID = roleID;
                dataT.rows().invalidate().draw(true);
                window.CRM.notify(i18next.t("Default role updated."), {
                    type: "success",
                    delay: 3000,
                });
            })
            .fail((xhr, status, error) => {
                console.error("Failed to set default role:", error);
                window.CRM.notify(i18next.t("Failed to set default role. Please try again."), {
                    type: "danger",
                    delay: 5000,
                });
            });
    });

    const dataTableConfig = {
        data: groupRoleData,
        columns: [
            {
                width: "auto",
                title: i18next.t("Role Name"),
                data: "lst_OptionName",
                render: (data, type, full, meta) => {
                    if (type === "display") {
                        const isReadOnly = data === "Student" || data === "Teacher";
                        const displayValue = isReadOnly ? i18next.t(data) : data;
                        const readOnlyAttr = isReadOnly ? " readonly" : "";
                        return `<input type="text" class="roleName" id="roleName-${full.lst_OptionID}" value="${displayValue}"${readOnlyAttr}>`;
                    }
                    return data;
                },
            },
            {
                width: "auto",
                title: i18next.t("Make Default"),
                data: null,
                render: (data, type, full, meta) => {
                    if (full.lst_OptionID == defaultRoleID) {
                        return `<strong><i class="fa-solid fa-check"></i>${i18next.t("Default")}</strong>`;
                    } else {
                        return `<button type="button" id="defaultRole-${full.lst_OptionID}" class="btn btn-success defaultRole">${i18next.t("Default")}</button>`;
                    }
                },
            },
            {
                width: "200px",
                title: i18next.t("Sequence"),
                data: "lst_OptionSequence",
                className: "dt-body-center",
                render: (data, type, full, meta) => {
                    if (type === "display") {
                        let sequenceCell = "";
                        if (data > 1) {
                            sequenceCell += `<button type="button" id="roleUp-${full.lst_OptionID}" class="btn rollOrder"> <i class="fa-solid fa-arrow-up"></i></button>&nbsp;`;
                        }
                        sequenceCell += data;
                        if (data != roleCount) {
                            sequenceCell += `&nbsp;<button type="button" id="roleDown-${full.lst_OptionID}" class="btn rollOrder"> <i class="fa-solid fa-arrow-down"></i></button>`;
                        }
                        return sequenceCell;
                    }
                    return data;
                },
            },
            {
                width: "auto",
                title: i18next.t("Delete"),
                data: null,
                render: (data, type, full, meta) => {
                    const isProtected = full.lst_OptionName === "Student" || full.lst_OptionName === "Teacher";
                    const disabledAttr = isProtected ? " disabled" : "";
                    return `<button type="button" id="roleDelete-${full.lst_OptionID}" class="btn btn-danger deleteRole"${disabledAttr}>${i18next.t("Delete")}</button>`;
                },
            },
        ],
        order: [[3, "asc"]],
    };
    $.extend(dataTableConfig, window.CRM.plugin.dataTable);
    dataT = $("#groupRoleTable").DataTable(dataTableConfig);
}

// Wait for locales to load before initializing
$(document).ready(function () {
    window.CRM.onLocalesReady(initializeGroupEditor);
});

function setGroupRoleOrder(groupID, roleID, groupRoleOrder) {
    $.ajax({
        method: "POST",
        url: `${window.CRM.root}/api/groups/${groupID}/roles/${roleID}`,
        data: JSON.stringify({ groupRoleOrder }),
        contentType: "application/json; charset=utf-8",
        dataType: "json",
    })
        .done((data) => {
            // Role order updated successfully
        })
        .fail((xhr, status, error) => {
            console.error("Failed to update role order:", error);
        });
}
