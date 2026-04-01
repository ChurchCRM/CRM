// ------------------------------------------------------------------ //
// BS5 Modal helpers (mirrors webpack/people/person-group-manager.js)
// ------------------------------------------------------------------ //
var GV_MODAL_ID = "groupViewModal";

function _createModal(title, bodyHtml) {
  var existing = document.getElementById(GV_MODAL_ID);
  if (existing) {
    var old = window.bootstrap.Modal.getInstance(existing);
    if (old) old.dispose();
    existing.remove();
  }

  var wrapper = document.createElement("div");
  wrapper.id = GV_MODAL_ID;
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
  var modal = new window.bootstrap.Modal(wrapper);
  var confirmBtn = wrapper.querySelector("#gvModalConfirmBtn");

  wrapper.addEventListener(
    "hidden.bs.modal",
    function () {
      modal.dispose();
      wrapper.remove();
    },
    { once: true },
  );

  return { modal: modal, el: wrapper, confirm: confirmBtn };
}

function _populateSelect(selectEl, items) {
  selectEl.innerHTML = "";
  for (var i = 0; i < items.length; i++) {
    var opt = document.createElement("option");
    opt.value = items[i].value;
    opt.textContent = items[i].text;
    selectEl.appendChild(opt);
  }
}

/**
 * Show a role selection modal for the current group. Calls callback(roleId).
 */
function _showRoleModal(title, callback) {
  var roles = window.CRM.groupRoles || [];
  if (roles.length <= 1) {
    // Auto-select the only role (or null)
    callback(roles.length === 1 ? String(roles[0].OptionId) : null);
    return;
  }

  var body =
    '<div class="mb-3">' +
    '<label class="form-label">' +
    i18next.t("Role") +
    "</label>" +
    '<select id="gv-role-select"></select>' +
    "</div>";

  var result = _createModal(title, body);
  result.confirm.disabled = false;
  var selectedRoleId = String(roles[0].OptionId);

  var roleEl = document.getElementById("gv-role-select");
  _populateSelect(
    roleEl,
    roles.map(function (r) {
      return { value: String(r.OptionId), text: i18next.t(r.OptionName) };
    }),
  );

  result.el.addEventListener(
    "shown.bs.modal",
    function () {
      new window.TomSelect(roleEl, {
        onChange: function (value) {
          selectedRoleId = value || null;
        },
      });
    },
    { once: true },
  );

  result.modal.show();

  result.confirm.addEventListener("click", function () {
    result.confirm.disabled = true;
    result.modal.hide();
    callback(selectedRoleId);
  });
}

/**
 * Show a group + role selection modal. Calls callback({ GroupID, RoleID }).
 */
function _showGroupAndRoleModal(title, callback) {
  var body =
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

  var result = _createModal(title, body);
  var selectedGroupId = null;
  var selectedRoleId = null;

  // Fetch all groups
  window.CRM.groups.get().done(function (groups) {
    var groupEl = document.getElementById("gv-group-select");
    _populateSelect(
      groupEl,
      groups.map(function (g) {
        return { value: String(g.Id), text: g.Name };
      }),
    );

    result.el.addEventListener(
      "shown.bs.modal",
      function () {
        var roleWrapper = document.getElementById("gv-role-wrapper");
        var roleEl = document.getElementById("gv-role-select");

        new window.TomSelect(groupEl, {
          placeholder: i18next.t("Search groups..."),
          items: [],
          onChange: function (value) {
            selectedGroupId = value || null;
            if (!value) {
              roleWrapper.classList.add("d-none");
              result.confirm.disabled = true;
              return;
            }
            // Load roles for selected group
            if (roleEl.tomselect) roleEl.tomselect.destroy();
            roleEl.innerHTML = "";
            roleWrapper.classList.add("d-none");

            window.CRM.groups.getRoles(value).done(function (roles) {
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
              _populateSelect(
                roleEl,
                roles.map(function (r) {
                  return { value: String(r.OptionId), text: i18next.t(r.OptionName) };
                }),
              );
              roleWrapper.classList.remove("d-none");
              result.confirm.disabled = false;
              new window.TomSelect(roleEl, {
                onChange: function (v) {
                  selectedRoleId = v || null;
                },
              });
              selectedRoleId = String(roles[0].OptionId);
            });
          },
        });
      },
      { once: true },
    );

    result.modal.show();
  });

  result.confirm.addEventListener("click", function () {
    if (!selectedGroupId) return;
    result.confirm.disabled = true;
    result.modal.hide();
    callback({ GroupID: selectedGroupId, RoleID: selectedRoleId });
  });
}

// ------------------------------------------------------------------ //
// Main initializer
// ------------------------------------------------------------------ //
function initializeGroupView() {
  // ------------------------------------------------------------------ //
  // Delete group
  // ------------------------------------------------------------------ //
  $("#deleteGroupButton").on("click", function () {
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
      callback: function (result) {
        if (result) {
          window.CRM.APIRequest({
            method: "DELETE",
            path: "groups/" + window.CRM.currentGroup,
          }).done(function (data) {
            if (data.status === "success") {
              window.location.href = window.CRM.root + "/groups/dashboard";
            }
          });
        }
      },
    });
  });

  // ------------------------------------------------------------------ //
  // Toggle Active / Email Export from Actions dropdown
  // ------------------------------------------------------------------ //
  $("#toggleGroupActive").on("click", function (e) {
    e.preventDefault();
    $.ajax({
      type: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/settings/active/" + !window.CRM.groupIsActive,
      dataType: "json",
    }).done(function () {
      location.reload();
    });
  });

  $("#toggleGroupEmailExport").on("click", function (e) {
    e.preventDefault();
    $.ajax({
      type: "POST",
      url:
        window.CRM.root +
        "/api/groups/" +
        window.CRM.currentGroup +
        "/settings/email/export/" +
        !window.CRM.groupEmailExport,
      dataType: "json",
    }).done(function () {
      location.reload();
    });
  });

  // ------------------------------------------------------------------ //
  // Email dropdown: populate on first open, then handle clicks
  // ------------------------------------------------------------------ //
  var emailLoaded = false;
  $("#emailDropdownBtn")
    .parent()
    .on("show.bs.dropdown", function () {
      if (emailLoaded) return;
      emailLoaded = true;
      window.CRM.APIRequest({
        method: "GET",
        path: "groups/" + window.CRM.currentGroup + "/emails",
      }).done(function (data) {
        var menu = $("#emailDropdownMenu");
        menu.empty();
        if (!data.all) {
          menu.html('<span class="dropdown-item text-muted">' + i18next.t("No email addresses available") + "</span>");
          return;
        }
        // All section
        menu.append(
          '<button class="dropdown-item" data-action="copy-emails" data-emails="' +
            window.CRM.escapeHtml(data.all) +
            '"><i class="fa-solid fa-copy me-2"></i>' +
            i18next.t("Copy All Emails") +
            "</button>",
        );
        menu.append(
          '<button class="dropdown-item" data-action="mailto" data-emails="' +
            window.CRM.escapeHtml(data.all) +
            '"><i class="fa-solid fa-envelope me-2"></i>' +
            i18next.t("Email All") +
            "</button>",
        );
        menu.append(
          '<button class="dropdown-item" data-action="bcc" data-emails="' +
            window.CRM.escapeHtml(data.all) +
            '"><i class="fa-solid fa-user-secret me-2"></i>' +
            i18next.t("BCC All") +
            "</button>",
        );
        // Per-role sections
        if (data.roles && Object.keys(data.roles).length > 0) {
          $.each(data.roles, function (roleName, emails) {
            menu.append('<div class="dropdown-divider"></div>');
            menu.append('<h6 class="dropdown-header">' + window.CRM.escapeHtml(roleName) + "</h6>");
            menu.append(
              '<button class="dropdown-item" data-action="copy-emails" data-emails="' +
                window.CRM.escapeHtml(emails) +
                '"><i class="fa-solid fa-copy me-2"></i>' +
                i18next.t("Copy") +
                "</button>",
            );
            menu.append(
              '<button class="dropdown-item" data-action="mailto" data-emails="' +
                window.CRM.escapeHtml(emails) +
                '"><i class="fa-solid fa-envelope me-2"></i>' +
                i18next.t("Email") +
                "</button>",
            );
          });
        }
      });
    });

  // Handle email actions (delegated from dynamic items)
  $("#group-view-toolbar").on("click", "[data-action='copy-emails']", function () {
    window.CRM.comm.copyEmails($(this).data("emails"));
  });
  $("#group-view-toolbar").on("click", "[data-action='mailto']", function () {
    window.CRM.comm.openMailto($(this).data("emails"));
  });
  $("#group-view-toolbar").on("click", "[data-action='bcc']", function () {
    window.CRM.comm.openBcc($(this).data("emails"));
  });

  // ------------------------------------------------------------------ //
  // Text dropdown: populate on first open, then handle clicks
  // ------------------------------------------------------------------ //
  var textLoaded = false;
  $("#textDropdownBtn")
    .parent()
    .on("show.bs.dropdown", function () {
      if (textLoaded) return;
      textLoaded = true;
      window.CRM.APIRequest({
        method: "GET",
        path: "groups/" + window.CRM.currentGroup + "/phones",
      }).done(function (data) {
        var menu = $("#textDropdownMenu");
        menu.empty();
        if (!data.phones || !data.phones.length) {
          menu.html('<span class="dropdown-item text-muted">' + i18next.t("No phone numbers available") + "</span>");
          return;
        }
        // All section
        menu.append(
          '<button class="dropdown-item" data-action="copy-phones" data-phones="' +
            window.CRM.escapeHtml(data.displayList) +
            '"><i class="fa-solid fa-copy me-2"></i>' +
            i18next.t("Copy All Numbers") +
            "</button>",
        );
        menu.append(
          '<button class="dropdown-item" data-action="sms" data-phones="' +
            window.CRM.escapeHtml(JSON.stringify(data.phones)) +
            '"><i class="fa-solid fa-comment-sms me-2"></i>' +
            i18next.t("Text All") +
            "</button>",
        );
        // Per-role sections
        if (data.roles && Object.keys(data.roles).length > 0) {
          $.each(data.roles, function (roleName, roleData) {
            if (!roleData.phones || !roleData.phones.length) return;
            menu.append('<div class="dropdown-divider"></div>');
            menu.append('<h6 class="dropdown-header">' + window.CRM.escapeHtml(roleName) + "</h6>");
            menu.append(
              '<button class="dropdown-item" data-action="copy-phones" data-phones="' +
                window.CRM.escapeHtml(roleData.displayList) +
                '"><i class="fa-solid fa-copy me-2"></i>' +
                i18next.t("Copy") +
                "</button>",
            );
            menu.append(
              '<button class="dropdown-item" data-action="sms" data-phones="' +
                window.CRM.escapeHtml(JSON.stringify(roleData.phones)) +
                '"><i class="fa-solid fa-comment-sms me-2"></i>' +
                i18next.t("Text") +
                "</button>",
            );
          });
        }
      });
    });

  // Handle text actions (delegated from dynamic items)
  $("#group-view-toolbar").on("click", "[data-action='copy-phones']", function () {
    window.CRM.comm.copyPhones($(this).data("phones"));
  });
  $("#group-view-toolbar").on("click", "[data-action='sms']", function () {
    var phones = $(this).data("phones");
    if (typeof phones === "string") phones = JSON.parse(phones);
    window.CRM.comm.openSms(phones);
  });

  // ------------------------------------------------------------------ //
  // Assign new group property
  // ------------------------------------------------------------------ //
  $("#assign-group-property-btn").on("click", function () {
    var select = document.getElementById("group-property-select");
    if (!select) return;
    var propertyId = select.value;
    var promptText = select.options[select.selectedIndex].dataset.prompt;

    function doAssign(value) {
      window.CRM.APIRequest({
        method: "POST",
        path: "groups/" + window.CRM.currentGroup + "/properties/" + propertyId,
        data: JSON.stringify({ value: value }),
      }).done(function () {
        location.reload();
      });
    }

    if (promptText) {
      bootbox.prompt({
        title: window.CRM.escapeHtml(promptText),
        callback: function (val) {
          if (val !== null) doAssign(val);
        },
      });
    } else {
      doAssign("");
    }
  });

  // ------------------------------------------------------------------ //
  // Edit group property value (has prompt)
  // ------------------------------------------------------------------ //
  $(document).on("click", ".edit-group-property-btn", function () {
    var btn = $(this);
    bootbox.prompt({
      title: window.CRM.escapeHtml(btn.data("pro-prompt")),
      value: btn.data("pro-value"),
      callback: function (val) {
        if (val !== null) {
          window.CRM.APIRequest({
            method: "POST",
            path: "groups/" + window.CRM.currentGroup + "/properties/" + btn.data("pro-id"),
            data: JSON.stringify({ value: val }),
          }).done(function () {
            location.reload();
          });
        }
      },
    });
  });

  // ------------------------------------------------------------------ //
  // Remove group property assignment
  // ------------------------------------------------------------------ //
  $(document).on("click", ".remove-group-property-btn", function () {
    var btn = $(this);
    var name = btn.data("pro-name");
    bootbox.confirm({
      title: i18next.t("Remove Property"),
      message:
        i18next.t("Remove") + " <strong>" + window.CRM.escapeHtml(name) + "</strong> " + i18next.t("from this group?"),
      buttons: {
        confirm: { label: i18next.t("Remove"), className: "btn-danger" },
        cancel: { label: i18next.t("Cancel"), className: "btn-secondary" },
      },
      callback: function (result) {
        if (result) {
          window.CRM.APIRequest({
            method: "DELETE",
            path: "groups/" + window.CRM.currentGroup + "/properties/" + btn.data("pro-id"),
          }).done(function () {
            location.reload();
          });
        }
      },
    });
  });

  // ------------------------------------------------------------------ //
  // Load roles, build pill filters, then init DataTable
  // ------------------------------------------------------------------ //
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/roles",
    dataType: "json",
  }).then(function (data) {
    window.CRM.groupRoles = data ?? [];
    initDataTable();
  });

  // Person search for "Add Member" — uses BS5 modal for role selection
  $(".personSearch").each(function () {
    if (this.tomselect) return;
    new TomSelect(this, {
      valueField: "objid",
      labelField: "text",
      searchField: "text",
      load: function (query, callback) {
        if (query.length < 2) return callback();
        fetch(window.CRM.root + "/api/persons/search/" + encodeURIComponent(query))
          .then(function (res) {
            return res.json();
          })
          .then(function (data) {
            callback(data);
          })
          .catch(function () {
            callback();
          });
      },
      onChange: function (value) {
        if (!value) return;
        var tsInstance = this;
        var selectedData = tsInstance.options[value];
        _showRoleModal(i18next.t("Select Role"), function (roleId) {
          window.CRM.groups.addPerson(window.CRM.currentGroup, selectedData.objid, roleId).then(function () {
            tsInstance.clear(true);
            tsInstance.clearOptions();
            window.CRM.DataTableAPI.ajax.reload();
          });
        });
      },
    });
  });

  // ------------------------------------------------------------------ //
  // Cart: Add All Members
  // ------------------------------------------------------------------ //
  $("#addAllToCart").on("click", function (e) {
    e.preventDefault();
    window.CRM.cartManager.addGroup(window.CRM.currentGroup, { showNotification: true });
  });

  // Cart: Add by Role
  $(document).on("click", ".add-role-to-cart", function (e) {
    e.preventDefault();
    var ids = [];
    var roleId = $(this).data("role-id");
    window.CRM.DataTableAPI.rows().every(function () {
      var d = this.data();
      if (String(d.RoleId) === String(roleId)) ids.push(d.PersonId);
    });
    if (ids.length > 0) window.CRM.cartManager.addPerson(ids, { showNotification: true });
  });

  // ------------------------------------------------------------------ //
  // Copy members to another group (by role) — BS5 modal
  // ------------------------------------------------------------------ //
  $(document).on("click", ".copy-role-to-group", function (e) {
    e.preventDefault();
    var roleId = $(this).data("role-id");
    _showGroupAndRoleModal(i18next.t("Copy Members to Group"), function (data) {
      _getMembersByRole(roleId).forEach(function (row) {
        window.CRM.groups.addPerson(data.GroupID, row.PersonId, data.RoleID);
      });
    });
  });

  // Move members to another group (by role) — BS5 modal
  $(document).on("click", ".move-role-to-group", function (e) {
    e.preventDefault();
    var roleId = $(this).data("role-id");
    var label = roleId === "" ? i18next.t("all members") : $(this).text().trim();
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
      callback: function (result) {
        if (result) {
          _showGroupAndRoleModal(i18next.t("Move Members to Group"), function (data) {
            _getMembersByRole(roleId).forEach(function (row) {
              window.CRM.groups.addPerson(data.GroupID, row.PersonId, data.RoleID);
              window.CRM.groups.removePerson(window.CRM.currentGroup, row.PersonId);
            });
            setTimeout(function () {
              window.CRM.DataTableAPI.ajax.reload();
            }, 1000);
          });
        }
      },
    });
  });

  // ------------------------------------------------------------------ //
  // Inline member actions (delegated)
  // ------------------------------------------------------------------ //
  $(document).on("click", ".view-person-photo", function (e) {
    window.CRM.showPhotoLightbox("person", $(e.currentTarget).data("person-id"));
    e.stopPropagation();
  });

  // Change role — BS5 modal (not bootbox)
  $(document).on("click", ".changeMembership", function (e) {
    var PersonID = $(e.currentTarget).data("personid");
    _showRoleModal(i18next.t("Change Role"), function (roleId) {
      if (!roleId) return;
      window.CRM.groups.addPerson(window.CRM.currentGroup, PersonID, roleId).done(function () {
        window.CRM.DataTableAPI.row(function (idx, data) {
          if (Number(data.PersonId) === Number(PersonID)) {
            data.RoleId = roleId;
            return true;
          }
        });
        window.CRM.DataTableAPI.rows().invalidate().draw(true);
      });
    });
    e.stopPropagation();
  });

  // Remove member
  $(document).on("click", ".remove-member-btn", function (e) {
    e.stopPropagation();
    var personId = $(this).data("personid");
    var personName = $(this).data("name");
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
      callback: function (result) {
        if (result) {
          window.CRM.groups.removePerson(window.CRM.currentGroup, personId).then(function () {
            window.CRM.DataTableAPI.row(function (idx, data) {
              return Number(data.PersonId) === Number(personId);
            }).remove();
            window.CRM.DataTableAPI.rows().invalidate().draw(true);
          });
        }
      },
    });
  });
}

// Wait for locales to load before initializing
$(document).ready(function () {
  // Print button
  $("#printGroup").on("click", function () {
    window.print();
  });

  window.CRM.onLocalesReady(initializeGroupView);
});

// ------------------------------------------------------------------ //
// Helpers
// ------------------------------------------------------------------ //

function _getMembersByRole(roleId) {
  var rows = [];
  window.CRM.DataTableAPI.rows().every(function () {
    var d = this.data();
    if (roleId === "" || roleId === undefined || String(d.RoleId) === String(roleId)) {
      rows.push(d);
    }
  });
  return rows;
}

// ------------------------------------------------------------------ //
// Role pill filter builder + DataTable init
// ------------------------------------------------------------------ //
function buildRolePills() {
  var $pills = $("#role-pills");
  if (!$pills.length || !window.CRM.groupRoles) return;

  var roleCounts = {};
  var totalCount = 0;
  if (window.CRM.DataTableAPI) {
    window.CRM.DataTableAPI.rows({ search: "none" }).every(function () {
      var d = this.data();
      roleCounts[d.RoleId] = (roleCounts[d.RoleId] || 0) + 1;
      totalCount++;
    });
  }

  // Pill filters
  var html =
    '<li class="nav-item">' +
    '<a class="nav-link active role-filter-pill" data-role-id="" href="#">' +
    i18next.t("All") +
    ' <span class="badge bg-primary-lt text-primary ms-1">' +
    totalCount +
    "</span></a></li>";

  window.CRM.groupRoles.forEach(function (role) {
    var count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    html +=
      '<li class="nav-item">' +
      '<a class="nav-link role-filter-pill" data-role-id="' +
      role.OptionId +
      '" href="#">' +
      i18next.t(role.OptionName) +
      ' <span class="badge bg-secondary-lt text-secondary ms-1">' +
      count +
      "</span></a></li>";
  });
  $pills.html(html);

  // Cart role items
  var $cartMenu = $("#addToCartMenu");
  $cartMenu.find(".add-role-to-cart").remove();
  window.CRM.groupRoles.forEach(function (role) {
    var count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    $cartMenu.append(
      '<a class="dropdown-item add-role-to-cart" data-role-id="' +
        role.OptionId +
        '" href="#">' +
        '<i class="fa-solid fa-user me-2"></i>' +
        i18next.t(role.OptionName) +
        ' <span class="badge bg-secondary-lt text-secondary ms-1">' +
        count +
        "</span></a>",
    );
  });
  $("#cartRoleDivider").toggle(totalCount > 0 && window.CRM.groupRoles.length > 0);

  // Copy/Move role items in Actions dropdown
  var $copyItems = $("#copyRoleItems").empty();
  var $moveItems = $("#moveRoleItems").empty();
  window.CRM.groupRoles.forEach(function (role) {
    var count = roleCounts[role.OptionId] || 0;
    if (count === 0) return;
    var roleName = i18next.t(role.OptionName);
    var badge = ' <span class="badge bg-secondary-lt text-secondary ms-1">' + count + "</span>";
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

  // Pill click filter
  $pills.off("click", ".role-filter-pill").on("click", ".role-filter-pill", function (e) {
    e.preventDefault();
    $pills.find(".nav-link").removeClass("active");
    $(this).addClass("active");

    var roleId = $(this).data("role-id");
    if (roleId === "" || roleId === undefined) {
      window.CRM.DataTableAPI.column(1).search("").draw();
    } else {
      var role = window.CRM.groupRoles.find(function (r) {
        return r.OptionId == roleId;
      });
      if (role) {
        var escapedName = i18next.t(role.OptionName).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        window.CRM.DataTableAPI.column(1)
          .search("^" + escapedName + "$", true, false)
          .draw();
      }
    }
  });
}

function initDataTable() {
  var DataTableOpts = {
    ajax: {
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/members",
      dataSrc: "Person2group2roleP2g2rs",
    },
    columns: [
      {
        width: "auto",
        title: i18next.t("Name"),
        data: "PersonId",
        render: function (data, type, full) {
          var escapedName = $("<div>").text(full.Person.FullName).html();
          return (
            '<div class="d-flex align-items-center">' +
            '<img data-image-entity-type="person" data-image-entity-id="' +
            full.PersonId +
            '" class="avatar avatar-sm me-2">' +
            '<a href="' +
            window.CRM.root +
            "/PersonView.php?PersonID=" +
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
        render: function (data) {
          var thisRole = (window.CRM.groupRoles || []).filter(function (item) {
            return item.OptionId == data;
          })[0];
          var escapedRoleName = $("<div>").text(i18next.t(thisRole?.OptionName)).html();
          return '<span class="badge bg-secondary-lt text-secondary">' + escapedRoleName + "</span>";
        },
      },
      {
        width: "15%",
        title: i18next.t("Phone"),
        data: "Person.CellPhone",
        defaultContent: "",
        render: function (data) {
          if (!data) return '<span class="text-muted">\u2014</span>';
          var escaped = $("<div>").text(data).html();
          return '<a href="tel:' + escaped + '">' + escaped + "</a>";
        },
      },
      {
        width: "20%",
        title: i18next.t("Email"),
        data: "Person.Email",
        defaultContent: "",
        render: function (data) {
          if (!data) return '<span class="text-muted">\u2014</span>';
          var escaped = $("<div>").text(data).html();
          return '<a href="mailto:' + escaped + '">' + escaped + "</a>";
        },
      },
      {
        width: "1",
        title: "",
        data: null,
        orderable: false,
        searchable: false,
        className: "text-end w-1 no-export",
        render: function (data, type, full) {
          var escapedName = $("<div>").text(full.Person.FullName).html();
          return (
            '<div class="dropdown">' +
            '<button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static">' +
            '<i class="ti ti-dots-vertical"></i></button>' +
            '<div class="dropdown-menu dropdown-menu-end">' +
            '<a class="dropdown-item" href="' +
            window.CRM.root +
            "/PersonView.php?PersonID=" +
            full.PersonId +
            '"><i class="ti ti-eye me-2"></i>' +
            i18next.t("View") +
            "</a>" +
            '<button class="dropdown-item changeMembership" data-personid="' +
            full.PersonId +
            '"><i class="ti ti-users me-2"></i>' +
            i18next.t("Change Role") +
            "</button>" +
            '<button class="dropdown-item AddToCart" data-cart-id="' +
            full.PersonId +
            '" data-cart-type="person" data-label-add="' +
            i18next.t("Add to Cart") +
            '" data-label-remove="' +
            i18next.t("Remove from Cart") +
            '"><i class="ti ti-shopping-cart-plus me-2"></i><span class="cart-label">' +
            i18next.t("Add to Cart") +
            "</span></button>" +
            '<div class="dropdown-divider"></div>' +
            '<button class="dropdown-item text-danger remove-member-btn" data-personid="' +
            full.PersonId +
            '" data-name="' +
            escapedName +
            '"><i class="ti ti-user-minus me-2"></i>' +
            i18next.t("Remove") +
            "</button></div></div>"
          );
        },
      },
    ],
    responsive: true,
    autoWidth: false,
    drawCallback: function (settings) {
      var api = new $.fn.dataTable.Api(settings);
      var totalMembers = api.rows({ search: "none" }).count();
      $("#iTotalMembers").text(totalMembers);
      $("#memberCountBadge").text(totalMembers);
      buildRolePills();
      if (window.CRM.avatarLoader) window.CRM.avatarLoader.refresh();
    },
  };
  $.extend(DataTableOpts, window.CRM.plugin.dataTable);
  window.CRM.DataTableAPI = $("#membersTable").DataTable(DataTableOpts);
}
