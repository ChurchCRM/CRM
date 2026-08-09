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
          window.location.href = `${window.CRM.root}/groups/sundayschool/dashboard`;
        } else {
          window.location.href = `${window.CRM.root}/groups/dashboard`;
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

  // Add Role modal wiring
  $("#newRole").on("input", () => {
    const hasValue = $("#newRole").val().trim().length > 0;
    $("#submitNewRole").prop("disabled", !hasValue);
  });

  // Allow Enter key to submit the add-role modal
  $("#newRole").on("keydown", (e) => {
    if (e.key === "Enter" && !$("#submitNewRole").prop("disabled")) {
      $("#submitNewRole").trigger("click");
    }
  });

  // Clear input and reset button state when modal hides
  $("#addRoleModal").on("hidden.bs.modal", () => {
    $("#newRole").val("");
    $("#submitNewRole").prop("disabled", true);
  });

  $("#submitNewRole").click(() => {
    const newRoleName = $("#newRole").val().trim();
    if (!newRoleName) {
      return;
    }

    $("#submitNewRole").prop("disabled", true);

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
        dataT.row.add(newRow).draw(false);
        $("#addRoleModal").modal("hide");
        window.CRM.notify(i18next.t("Role added successfully."), {
          type: "success",
          delay: 3000,
        });
      })
      .fail((xhr, status, error) => {
        console.error("Failed to add new role:", error);
        $("#submitNewRole").prop("disabled", false);
        window.CRM.notify(i18next.t("Failed to add role. Please try again."), {
          type: "danger",
          delay: 5000,
        });
      });
  });

  // Store the role ID pending delete confirmation
  let pendingDeleteRoleID = null;

  $(document).on("click", ".deleteRole", (e) => {
    const btn = e.currentTarget;
    if (btn.disabled) return; // guard against programmatic clicks on protected-role buttons
    const roleID = btn.id.split("-")[1];
    // Prefer the live input value so renames are reflected before page refresh
    const roleNameInput = document.querySelector(`.roleName[id$="-${roleID}"]`);
    const roleName = roleNameInput ? roleNameInput.value : String($(btn).data("role-name") || "");

    pendingDeleteRoleID = roleID;

    // Populate modal message using safe text insertion
    const msg = i18next.t("Are you sure you want to remove the role '{{name}}'?", {
      name: roleName,
      interpolation: { escapeValue: false },
    });
    $("#deleteRoleMessage").text(msg);

    // Show last-role warning and block confirm when this is the only role
    if (roleCount <= 1) {
      $("#lastRoleWarning").removeClass("d-none");
      $("#confirmDeleteRole").prop("disabled", true);
    } else {
      $("#lastRoleWarning").addClass("d-none");
      $("#confirmDeleteRole").prop("disabled", false);
    }

    $("#deleteRoleModal").modal("show");
  });

  $("#deleteRoleModal").on("hidden.bs.modal", () => {
    pendingDeleteRoleID = null;
  });

  $("#confirmDeleteRole").click(() => {
    const roleID = pendingDeleteRoleID;
    if (!roleID) {
      return;
    }

    $("#confirmDeleteRole").prop("disabled", true);

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
        roleCount = data.length;
        dataT.rows().invalidate().draw(true);
        $("#deleteRoleModal").modal("hide");
        window.CRM.notify(i18next.t("Role deleted successfully."), {
          type: "success",
          delay: 3000,
        });
      })
      .fail((xhr, status, error) => {
        console.error("Failed to delete role:", error);
        $("#confirmDeleteRole").prop("disabled", false);
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
            // i18next-disable-next-line
            const displayValue = isReadOnly ? i18next.t(data) : data;
            const readOnlyAttr = isReadOnly ? " readonly" : "";
            // Escape HTML to prevent XSS
            const escapedValue = $("<div>").text(displayValue).html();
            return `<input type="text" class="roleName" id="roleName-${full.lst_OptionID}" value="${escapedValue}"${readOnlyAttr}>`;
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
            return `<strong><i class="fa-solid fa-check me-1"></i>${i18next.t("Default")}</strong>`;
          } else {
            return `<button type="button" id="defaultRole-${full.lst_OptionID}" class="btn btn-success defaultRole">${i18next.t("Default")}</button>`;
          }
        },
      },
      {
        width: "auto",
        title: i18next.t("Sequence"),
        data: "lst_OptionSequence",
        className: "dt-body-center",
        render: (data, type, full, meta) => {
          if (type === "display") {
            let sequenceCell = "";
            if (data > 1) {
              sequenceCell += `<button type="button" id="roleUp-${full.lst_OptionID}" class="btn btn-sm btn-ghost-secondary rollOrder" title="${i18next.t("Move up")}"><i class="fa-solid fa-arrow-up"></i></button> `;
            }
            sequenceCell += data;
            if (data < roleCount) {
              sequenceCell += ` <button type="button" id="roleDown-${full.lst_OptionID}" class="btn btn-sm btn-ghost-secondary rollOrder" title="${i18next.t("Move down")}"><i class="fa-solid fa-arrow-down"></i></button>`;
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
          const escapedName = window.CRM.escapeAttribute(full.lst_OptionName);
          const title = isProtected ? i18next.t("This role cannot be deleted.") : i18next.t("Delete role");
          const disabledAttr = isProtected ? " disabled" : "";
          // disabledAttr is used both in the class (Bootstrap visual disabled style)
          // and as a standalone HTML attribute (functionally disables the button,
          // preventing click events and making it detectable via [disabled] selector).
          return `<button type="button" id="roleDelete-${full.lst_OptionID}" class="btn btn-sm btn-ghost-danger deleteRole${disabledAttr}" title="${title}" data-role-name="${escapedName}"${disabledAttr}><i class="fa-solid fa-trash"></i></button>`;
        },
      },
    ],
    order: [[2, "asc"]],
  };
  $.extend(dataTableConfig, window.CRM.plugin.dataTable);
  dataT = $("#groupRoleTable").DataTable(dataTableConfig);
}

// Wait for locales to load before initializing
$(document).ready(() => {
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
