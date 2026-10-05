/**
 * Group view page (/groups/view/{id}): members table, role filters, member actions,
 * group properties and the Actions dropdown.
 *
 * PHP hands off window.CRM.currentGroup, currentGroupName, groupIsActive,
 * groupEmailExport before this bundle loads.
 */
import { initPersonSelect } from "../common/person-select";
import { setMemberRole } from "./group-api";

const MODAL_ID = "groupViewModal";

function createModal(title, bodyHtml) {
  const existing = document.getElementById(MODAL_ID);
  if (existing) {
    existing.querySelectorAll("select").forEach((sel) => {
      try {
        if (sel.tomselect) sel.tomselect.destroy();
      } catch (_e) {}
    });
    const old = window.bootstrap.Modal.getInstance(existing);
    if (old) old.dispose();
    existing.remove();
  }

  const wrapper = document.createElement("div");
  wrapper.id = MODAL_ID;
  wrapper.className = "modal fade";
  wrapper.innerHTML =
    '<div class="modal-dialog modal-dialog-centered">' +
    '<div class="modal-content">' +
    '<div class="modal-header">' +
    '<h5 class="modal-title">' +
    title +
    "</h5>" +
    '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
    "</div>" +
    '<div class="modal-body">' +
    bodyHtml +
    "</div>" +
    '<div class="modal-footer">' +
    '<button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">' +
    i18next.t("Cancel") +
    "</button>" +
    '<button type="button" class="btn btn-primary" id="gvModalConfirmBtn" disabled>' +
    i18next.t("Save") +
    "</button>" +
    "</div></div></div>";

  document.body.appendChild(wrapper);
  const modal = new window.bootstrap.Modal(wrapper);
  const confirmBtn = wrapper.querySelector("#gvModalConfirmBtn");

  wrapper.addEventListener(
    "hidden.bs.modal",
    () => {
      modal.dispose();
      wrapper.remove();
    },
    { once: true },
  );

  return { modal, el: wrapper, confirm: confirmBtn };
}

function populateSelect(selectEl, items) {
  selectEl.innerHTML = "";
  for (const item of items) {
    const opt = document.createElement("option");
    opt.value = item.value;
    opt.textContent = item.text;
    selectEl.appendChild(opt);
  }
}

/**
 * Show a role selection modal for the current group. Calls callback(roleId).
 */
function showRoleModal(title, callback) {
  const roles = window.CRM.groupRoles || [];
  if (roles.length <= 1) {
    callback(roles.length === 1 ? String(roles[0].OptionId) : null);
    return;
  }

  const body =
    '<div class="mb-3">' +
    '<label class="form-label">' +
    i18next.t("Role") +
    "</label>" +
    '<select id="gv-role-select"></select>' +
    "</div>";

  const result = createModal(title, body);
  result.confirm.disabled = false;
  let selectedRoleId = String(roles[0].OptionId);

  const roleEl = document.getElementById("gv-role-select");
  populateSelect(
    roleEl,
    roles.map((r) => ({ value: String(r.OptionId), text: i18next.t(r.OptionName) })),
  );

  result.el.addEventListener(
    "shown.bs.modal",
    () => {
      const ts = new window.TomSelect(roleEl, {
        dropdownParent: "body",
        onChange: (value) => {
          selectedRoleId = value || null;
        },
      });
      // wrapper.remove() leaves ts.dropdown in <body>, so destroy through the closure reference.
      result.el.addEventListener(
        "hidden.bs.modal",
        () => {
          try {
            ts.destroy();
          } catch (_e) {}
        },
        { once: true },
      );
    },
    { once: true },
  );

  result.modal.show();

  result.confirm.addEventListener("click", () => {
    result.confirm.disabled = true;
    result.modal.hide();
    callback(selectedRoleId);
  });
}

/**
 * Show a group + role selection modal. Calls callback({ GroupID, RoleID }).
 */
function showGroupAndRoleModal(title, callback) {
  const body =
    '<div class="mb-3">' +
    '<label class="form-label">' +
    i18next.t("Group") +
    "</label>" +
    '<select id="gv-group-select"></select>' +
    "</div>" +
    '<div class="mb-3 d-none" id="gv-role-wrapper">' +
    '<label class="form-label">' +
    i18next.t("Role") +
    "</label>" +
    '<select id="gv-role-select"></select>' +
    "</div>";

  const result = createModal(title, body);
  let selectedGroupId = null;
  let selectedRoleId = null;

  window.CRM.groups.get().done((groups) => {
    const groupEl = document.getElementById("gv-group-select");
    populateSelect(
      groupEl,
      groups.map((g) => ({ value: String(g.Id), text: g.Name })),
    );

    result.el.addEventListener(
      "shown.bs.modal",
      () => {
        const roleWrapper = document.getElementById("gv-role-wrapper");
        const roleEl = document.getElementById("gv-role-select");
        let tsRole = null;
        // If the modal closes before getRoles() resolves, skip building a TomSelect
        // on a detached element (it would orphan body > .ts-dropdown nodes).
        let isOpen = true;

        const tsGroup = new window.TomSelect(groupEl, {
          placeholder: i18next.t("Search groups..."),
          items: [],
          dropdownParent: "body",
          onChange: (value) => {
            selectedGroupId = value || null;
            if (!value) {
              roleWrapper.classList.add("d-none");
              result.confirm.disabled = true;
              return;
            }
            if (tsRole) {
              try {
                tsRole.destroy();
              } catch (_e) {}
              tsRole = null;
            }
            roleEl.innerHTML = "";
            roleWrapper.classList.add("d-none");

            window.CRM.groups.getRoles(value).done((roles) => {
              if (!isOpen) return;
              if (roles.length === 0) {
                selectedRoleId = null;
                result.confirm.disabled = false;
                return;
              }
              if (roles.length === 1) {
                selectedRoleId = String(roles[0].OptionId);
                result.confirm.disabled = false;
                return;
              }
              populateSelect(
                roleEl,
                roles.map((r) => ({ value: String(r.OptionId), text: i18next.t(r.OptionName) })),
              );
              roleWrapper.classList.remove("d-none");
              result.confirm.disabled = false;
              tsRole = new window.TomSelect(roleEl, {
                dropdownParent: "body",
                onChange: (v) => {
                  selectedRoleId = v || null;
                },
              });
              selectedRoleId = String(roles[0].OptionId);
            });
          },
        });
        result.el.addEventListener(
          "hidden.bs.modal",
          () => {
            isOpen = false;
            try {
              tsGroup.destroy();
            } catch (_e) {}
            try {
              if (tsRole) tsRole.destroy();
            } catch (_e) {}
          },
          { once: true },
        );
      },
      { once: true },
    );

    result.modal.show();
  });

  result.confirm.addEventListener("click", () => {
    if (!selectedGroupId) return;
    result.confirm.disabled = true;
    result.modal.hide();
    callback({ GroupID: selectedGroupId, RoleID: selectedRoleId });
  });
}

function getMembersByRole(roleId) {
  return window.CRM.DataTableAPI.rows()
    .data()
    .toArray()
    .filter((d) => roleId === "" || roleId === undefined || String(d.RoleId) === String(roleId));
}

function buildRolePills() {
  const $pills = $("#role-pills");
  if (!$pills.length || !window.CRM.groupRoles) return;

  const roleCounts = {};
  let totalCount = 0;
  if (window.CRM.DataTableAPI) {
    for (const d of window.CRM.DataTableAPI.rows({ search: "none" }).data().toArray()) {
      roleCounts[d.RoleId] = (roleCounts[d.RoleId] || 0) + 1;
      totalCount++;
    }
  }

  let html =
    '<li class="nav-item">' +
    '<a class="nav-link active role-filter-pill" data-role-id="" href="#">' +
    i18next.t("All") +
    ' <span class="badge bg-primary-lt text-primary ms-1">' +
    totalCount +
    "</span></a></li>";

  window.CRM.groupRoles.forEach((role) => {
    const count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    html +=
      '<li class="nav-item">' +
      '<a class="nav-link role-filter-pill" data-role-id="' +
      role.OptionId +
      '" href="#">' +
      window.CRM.escapeHtml(i18next.t(role.OptionName)) +
      ' <span class="badge bg-secondary-lt text-secondary ms-1">' +
      count +
      "</span></a></li>";
  });
  $pills.html(html);

  const $cartMenu = $("#addToCartMenu");
  $cartMenu.find(".add-role-to-cart").remove();
  window.CRM.groupRoles.forEach((role) => {
    const count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    $cartMenu.append(
      '<a class="dropdown-item add-role-to-cart" data-role-id="' +
        role.OptionId +
        '" href="#">' +
        '<i class="fa-solid fa-user me-2"></i>' +
        window.CRM.escapeHtml(i18next.t(role.OptionName)) +
        ' <span class="badge bg-secondary-lt text-secondary ms-1">' +
        count +
        "</span></a>",
    );
  });
  $("#cartRoleDivider").toggle(totalCount > 0 && window.CRM.groupRoles.length > 0);

  const $copyItems = $("#copyRoleItems").empty();
  const $moveItems = $("#moveRoleItems").empty();
  window.CRM.groupRoles.forEach((role) => {
    const count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    const roleName = window.CRM.escapeHtml(i18next.t(role.OptionName));
    const badge = ` <span class="badge bg-secondary-lt text-secondary ms-1">${count}</span>`;
    $copyItems.append(
      '<a class="dropdown-item copy-role-to-group" data-role-id="' +
        role.OptionId +
        '" href="#">' +
        '<i class="fa-solid fa-user me-2"></i>' +
        roleName +
        badge +
        "</a>",
    );
    $moveItems.append(
      '<a class="dropdown-item move-role-to-group" data-role-id="' +
        role.OptionId +
        '" href="#">' +
        '<i class="fa-solid fa-user me-2"></i>' +
        roleName +
        badge +
        "</a>",
    );
  });

  $pills.off("click", ".role-filter-pill").on("click", ".role-filter-pill", function (e) {
    e.preventDefault();
    $pills.find(".nav-link").removeClass("active");
    $(this).addClass("active");

    const roleId = $(this).data("role-id");
    if (roleId === "" || roleId === undefined) {
      window.CRM.DataTableAPI.column(1).search("").draw();
    } else {
      const role = window.CRM.groupRoles.find((r) => String(r.OptionId) === String(roleId));
      if (role) {
        const escapedName = i18next.t(role.OptionName).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        window.CRM.DataTableAPI.column(1).search(`^${escapedName}$`, true, false).draw();
      }
    }
  });
}

function initDataTable() {
  const DataTableOpts = {
    ajax: {
      url: `${window.CRM.root}/api/groups/${window.CRM.currentGroup}/members`,
      dataSrc: "Person2group2roleP2g2rs",
    },
    columns: [
      {
        width: "auto",
        title: i18next.t("Name"),
        data: "PersonId",
        render: (_data, type, full) => {
          if (type === "sort" || type === "type") {
            return ((full.Person.LastName || "") + " " + (full.Person.FirstName || "")).toLowerCase();
          }
          // GHSA-m649-24q9-q6r4: HTML-escape for HTML content context (not attribute)
          const escapedName = window.CRM.escapeHtml(full.Person.FullName || "");
          return (
            '<div class="d-flex align-items-center">' +
            '<img data-image-entity-type="person" data-image-entity-id="' +
            full.PersonId +
            '" class="avatar avatar-sm me-2">' +
            '<a href="' +
            window.CRM.root +
            "/people/view/" +
            full.PersonId +
            '">' +
            escapedName +
            "</a></div>"
          );
        },
      },
      {
        width: "15%",
        title: i18next.t("Role"),
        data: "RoleId",
        render: (data) => {
          const thisRole = (window.CRM.groupRoles || []).filter((item) => String(item.OptionId) === String(data))[0];
          const escapedRoleName = $("<div>").text(i18next.t(thisRole?.OptionName)).html();
          return '<span class="badge bg-secondary-lt text-secondary">' + escapedRoleName + "</span>";
        },
      },
      {
        width: "15%",
        title: i18next.t("Phone"),
        data: "Person.CellPhone",
        defaultContent: "",
        render: (data) => {
          if (!data) return '<span class="text-muted">—</span>';
          // GHSA-m649-24q9-q6r4: attribute-escape the href value to prevent quote breakout;
          // escapeAttribute preserves @ and + (not HTML-special) while encoding "
          const escaped = $("<div>").text(data).html();
          return '<a href="tel:' + window.CRM.escapeAttribute(data) + '">' + escaped + "</a>";
        },
      },
      {
        width: "20%",
        title: i18next.t("Email"),
        data: "Person.Email",
        defaultContent: "",
        render: (data) => {
          if (!data) return '<span class="text-muted">—</span>';
          // GHSA-m649-24q9-q6r4: attribute-escape the href value to prevent quote breakout;
          // escapeAttribute preserves @ and + (not HTML-special) while encoding "
          const escaped = $("<div>").text(data).html();
          return (
            '<a href="mailto:' +
            window.CRM.escapeAttribute(data) +
            '" target="_blank" rel="noopener noreferrer">' +
            escaped +
            "</a>"
          );
        },
      },
      {
        width: "1",
        title: "",
        data: null,
        orderable: false,
        searchable: false,
        className: "text-end w-1 no-export",
        render: (_data, _type, full) => {
          // GHSA-m649-24q9-q6r4: escapeAttribute for the data-name attribute context (encodes quotes)
          const escapedName = window.CRM.escapeAttribute(full.Person.FullName || "");
          return (
            '<div class="dropdown">' +
            '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static">' +
            '<i class="fa-solid fa-ellipsis-vertical"></i></button>' +
            '<div class="dropdown-menu dropdown-menu-end">' +
            '<a class="dropdown-item" href="' +
            window.CRM.root +
            "/people/view/" +
            full.PersonId +
            '"><i class="fa-solid fa-eye me-2"></i>' +
            i18next.t("View") +
            "</a>" +
            '<button class="dropdown-item changeMembership" data-personid="' +
            full.PersonId +
            '"><i class="fa-solid fa-users me-2"></i>' +
            i18next.t("Change Role") +
            "</button>" +
            '<button class="dropdown-item AddToCart" data-cart-id="' +
            full.PersonId +
            '" data-cart-type="person" data-label-add="' +
            i18next.t("Add to Cart") +
            '" data-label-remove="' +
            i18next.t("Remove from Cart") +
            '"><i class="fa-solid fa-cart-plus me-2"></i><span class="cart-label">' +
            i18next.t("Add to Cart") +
            "</span></button>" +
            '<div class="dropdown-divider"></div>' +
            '<button class="dropdown-item text-danger remove-member-btn" data-personid="' +
            full.PersonId +
            '" data-name="' +
            escapedName +
            '"><i class="fa-solid fa-user-minus me-2"></i>' +
            i18next.t("Remove") +
            "</button></div></div>"
          );
        },
      },
    ],
    responsive: true,
    autoWidth: false,
    drawCallback: (settings) => {
      const api = new $.fn.dataTable.Api(settings);
      const totalMembers = api.rows({ search: "none" }).count();
      $("#iTotalMembers").text(totalMembers);
      $("#memberCountBadge").text(totalMembers);
      buildRolePills();
      if (window.CRM.avatarLoader) window.CRM.avatarLoader.refresh();
    },
  };
  $.extend(DataTableOpts, window.CRM.plugin.dataTable);
  window.CRM.DataTableAPI = $("#membersTable").DataTable(DataTableOpts);
}

function initializeGroupView() {
  $("#deleteGroupButton").on("click", () => {
    bootbox.confirm({
      title: i18next.t("Confirm Delete Group"),
      message:
        '<p class="text-danger">' +
        i18next.t("Please confirm deletion of this group record") +
        ": <strong>" +
        window.CRM.escapeHtml(window.CRM.currentGroupName || "") +
        "</strong></p>" +
        "<p>" +
        i18next.t(
          "This will also delete all Roles and Group-Specific Property data associated with this Group record.",
        ) +
        "</p><p>" +
        i18next.t(
          "All group membership and properties will be destroyed.  The group members themselves will not be altered.",
        ) +
        "</p>",
      buttons: {
        confirm: { label: i18next.t("Delete"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (result) {
          window.CRM.APIRequest({
            method: "DELETE",
            path: `groups/${window.CRM.currentGroup}`,
          }).done(() => {
            window.location.href = `${window.CRM.root}/groups/dashboard`;
          });
        }
      },
    });
  });

  const notifyGroupUpdateFailed = (xhr) => {
    window.CRM.notify(xhr.responseJSON?.message || i18next.t("Failed to update group. Please try again."), {
      type: "danger",
      delay: 5000,
    });
  };

  $("#toggleGroupActive").on("click", (e) => {
    e.preventDefault();
    $.ajax({
      type: "POST",
      url: `${window.CRM.root}/api/groups/${window.CRM.currentGroup}/settings/active/${!window.CRM.groupIsActive}`,
      dataType: "json",
    })
      .done(() => {
        location.reload();
      })
      .fail(notifyGroupUpdateFailed);
  });

  $("#toggleGroupEmailExport").on("click", (e) => {
    e.preventDefault();
    $.ajax({
      type: "POST",
      url: `${window.CRM.root}/api/groups/${window.CRM.currentGroup}/settings/email/export/${!window.CRM.groupEmailExport}`,
      dataType: "json",
    })
      .done(() => {
        location.reload();
      })
      .fail(notifyGroupUpdateFailed);
  });

  // The email action is wired by the email-composer bundle via [data-email-composer].

  let textLoaded = false;
  $("#textDropdownBtn")
    .parent()
    .on("show.bs.dropdown", () => {
      if (textLoaded) return;
      textLoaded = true;
      window.CRM.APIRequest({
        method: "GET",
        path: `groups/${window.CRM.currentGroup}/phones`,
      }).done((data) => {
        const menu = $("#textDropdownMenu");
        menu.empty();
        if (!data.phones || !data.phones.length) {
          menu.html('<span class="dropdown-item text-muted">' + i18next.t("No phone numbers available") + "</span>");
          return;
        }
        menu.append(
          $("<button>", { class: "dropdown-item", "data-action": "copy-phones" })
            .data("phones", data.displayList)
            .html('<i class="fa-solid fa-copy me-2"></i>' + i18next.t("Copy All Numbers")),
        );
        menu.append(
          $("<button>", { class: "dropdown-item", "data-action": "sms" })
            .data("phones", data.phones)
            .html('<i class="fa-solid fa-comment-sms me-2"></i>' + i18next.t("Text All")),
        );
        if (data.roles && Object.keys(data.roles).length > 0) {
          $.each(data.roles, (roleName, roleData) => {
            if (!roleData.phones || !roleData.phones.length) return;
            menu.append('<div class="dropdown-divider"></div>');
            menu.append('<h6 class="dropdown-header">' + window.CRM.escapeHtml(roleName) + "</h6>");
            menu.append(
              $("<button>", { class: "dropdown-item", "data-action": "copy-phones" })
                .data("phones", roleData.displayList)
                .html('<i class="fa-solid fa-copy me-2"></i>' + i18next.t("Copy")),
            );
            menu.append(
              $("<button>", { class: "dropdown-item", "data-action": "sms" })
                .data("phones", roleData.phones)
                .html('<i class="fa-solid fa-comment-sms me-2"></i>' + i18next.t("Text")),
            );
          });
        }
      });
    });

  $("#group-view-toolbar").on("click", "[data-action='copy-phones']", function () {
    window.CRM.comm.copyPhones($(this).data("phones"));
  });
  $("#group-view-toolbar").on("click", "[data-action='sms']", function () {
    let phones = $(this).data("phones");
    if (typeof phones === "string") {
      try {
        phones = JSON.parse(phones);
      } catch (_e) {
        phones = [phones];
      }
    }
    window.CRM.comm.openSms(phones);
  });

  $("#assign-group-property-btn").on("click", () => {
    const select = document.getElementById("group-property-select");
    if (!select) return;
    const propertyId = select.value;
    const promptText = select.options[select.selectedIndex].dataset.prompt;

    const doAssign = (value) => {
      window.CRM.APIRequest({
        method: "POST",
        path: `groups/${window.CRM.currentGroup}/properties/${propertyId}`,
        data: JSON.stringify({ value }),
      }).done(() => {
        location.reload();
      });
    };

    if (promptText) {
      bootbox.prompt({
        title: window.CRM.escapeHtml(promptText),
        callback: (val) => {
          if (val !== null) doAssign(val);
        },
      });
    } else {
      doAssign("");
    }
  });

  $(document).on("click", ".edit-group-property-btn", function () {
    const btn = $(this);
    bootbox.prompt({
      title: window.CRM.escapeHtml(btn.data("pro-prompt")),
      value: btn.data("pro-value"),
      callback: (val) => {
        if (val !== null) {
          window.CRM.APIRequest({
            method: "POST",
            path: `groups/${window.CRM.currentGroup}/properties/${btn.data("pro-id")}`,
            data: JSON.stringify({ value: val }),
          }).done(() => {
            location.reload();
          });
        }
      },
    });
  });

  $(document).on("click", ".remove-group-property-btn", function () {
    const btn = $(this);
    const name = btn.data("pro-name");
    bootbox.confirm({
      title: i18next.t("Remove Property"),
      message:
        i18next.t("Remove") + " <strong>" + window.CRM.escapeHtml(name) + "</strong> " + i18next.t("from this group?"),
      buttons: {
        confirm: { label: i18next.t("Remove"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: (result) => {
        if (result) {
          window.CRM.APIRequest({
            method: "DELETE",
            path: `groups/${window.CRM.currentGroup}/properties/${btn.data("pro-id")}`,
          }).done(() => {
            location.reload();
          });
        }
      },
    });
  });

  $.ajax({
    method: "GET",
    url: `${window.CRM.root}/api/groups/${window.CRM.currentGroup}/roles`,
    dataType: "json",
  }).then((data) => {
    window.CRM.groupRoles = data ?? [];
    initDataTable();
  });

  $(".personSearch").each(function () {
    if (this.tomselect) return;
    initPersonSelect(this, {
      onChange: function (value) {
        if (!value) return;

        const selectedData = this.options[value];
        showRoleModal(i18next.t("Select Role"), (roleId) => {
          window.CRM.groups.addPerson(window.CRM.currentGroup, selectedData.objid, roleId).then(() => {
            this.clear(true);
            this.clearOptions();
            window.CRM.DataTableAPI.ajax.reload();
          });
        });
      },
    });
  });

  $("#addAllToCart").on("click", (e) => {
    e.preventDefault();
    window.CRM.cartManager.addGroup(window.CRM.currentGroup, { showNotification: true });
  });

  $(document).on("click", ".add-role-to-cart", function (e) {
    e.preventDefault();
    const roleId = $(this).data("role-id");
    const ids = getMembersByRole(roleId).map((d) => d.PersonId);
    if (ids.length > 0) window.CRM.cartManager.addPerson(ids, { showNotification: true });
  });

  $(document).on("click", ".copy-role-to-group", function (e) {
    e.preventDefault();
    const roleId = $(this).data("role-id");
    showGroupAndRoleModal(i18next.t("Copy Members to Group"), (data) => {
      getMembersByRole(roleId).forEach((row) => {
        window.CRM.groups.addPerson(data.GroupID, row.PersonId, data.RoleID);
      });
    });
  });

  $(document).on("click", ".move-role-to-group", function (e) {
    e.preventDefault();
    const roleId = $(this).data("role-id");
    const label = roleId === "" ? i18next.t("all members") : $(this).text().trim();
    bootbox.confirm({
      title: i18next.t("Move Members"),
      message:
        i18next.t("Are you sure you want to move") +
        " <strong>" +
        window.CRM.escapeHtml(label) +
        "</strong> " +
        i18next.t("to another group?"),
      buttons: {
        confirm: { label: i18next.t("Move"), className: "btn-warning" },
        cancel: { label: i18next.t("Cancel") },
      },
      callback: (result) => {
        if (result) {
          showGroupAndRoleModal(i18next.t("Move Members to Group"), (data) => {
            getMembersByRole(roleId).forEach((row) => {
              window.CRM.groups.addPerson(data.GroupID, row.PersonId, data.RoleID);
              window.CRM.groups.removePerson(window.CRM.currentGroup, row.PersonId);
            });
            setTimeout(() => {
              window.CRM.DataTableAPI.ajax.reload();
            }, 1000);
          });
        }
      },
    });
  });

  // .view-person-photo click handler is registered globally in avatar-loader.ts

  $(document).on("click", ".changeMembership", (e) => {
    const personId = $(e.currentTarget).data("personid");
    showRoleModal(i18next.t("Change Role"), (roleId) => {
      if (!roleId) return;
      setMemberRole(window.CRM.currentGroup, personId, roleId).done(() => {
        window.CRM.DataTableAPI.row((_idx, data) => {
          if (Number(data.PersonId) === Number(personId)) {
            data.RoleId = Number(roleId);
            return true;
          }
        });
        window.CRM.DataTableAPI.rows().invalidate().draw(true);
      });
    });
    e.stopPropagation();
  });

  $(document).on("click", ".remove-member-btn", function (e) {
    e.stopPropagation();
    const personId = $(this).data("personid");
    const personName = $(this).data("name");
    bootbox.confirm({
      message:
        i18next.t("Are you sure you want to remove") +
        " <b>" +
        window.CRM.escapeHtml(personName) +
        "</b> " +
        i18next.t("from this group?"),
      buttons: {
        confirm: { label: i18next.t("Remove"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel") },
      },
      callback: (result) => {
        if (result) {
          window.CRM.groups.removePerson(window.CRM.currentGroup, personId).then(() => {
            window.CRM.DataTableAPI.row((_idx, data) => Number(data.PersonId) === Number(personId)).remove();
            window.CRM.DataTableAPI.rows().invalidate().draw(true);
          });
        }
      },
    });
  });
}

$(document).ready(() => {
  $("#printGroup").on("click", () => {
    window.print();
  });

  window.CRM.onLocalesReady(initializeGroupView);
});
