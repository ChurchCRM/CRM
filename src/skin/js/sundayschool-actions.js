/**
 * Sunday School class view — Copy/Move members to another group.
 *
 * Uses BS5 modals with TomSelect (same pattern as person-group-manager.js).
 * Reads person IDs from `.ss-member[data-person-id][data-role]` elements.
 */
(function () {
  var SS_MODAL_ID = "ssActionModal";

  function createModal(title, bodyHtml) {
    var existing = document.getElementById(SS_MODAL_ID);
    if (existing) {
      var old = window.bootstrap.Modal.getInstance(existing);
      if (old) old.dispose();
      existing.remove();
    }

    var wrapper = document.createElement("div");
    wrapper.id = SS_MODAL_ID;
    wrapper.className = "modal fade";
    wrapper.innerHTML =
      '<div class="modal-dialog modal-dialog-centered">' +
      '<div class="modal-content">' +
      '<div class="modal-header"><h5 class="modal-title">' +
      title +
      "</h5>" +
      '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
      '<div class="modal-body">' +
      bodyHtml +
      "</div>" +
      '<div class="modal-footer">' +
      '<button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">' +
      i18next.t("Cancel") +
      "</button>" +
      '<button type="button" class="btn btn-primary" id="ssModalConfirmBtn" disabled>' +
      i18next.t("Save") +
      "</button>" +
      "</div></div></div>";

    document.body.appendChild(wrapper);
    var modal = new window.bootstrap.Modal(wrapper);
    var confirmBtn = wrapper.querySelector("#ssModalConfirmBtn");

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

  function populateSelect(selectEl, items) {
    selectEl.innerHTML = "";
    for (var i = 0; i < items.length; i++) {
      var opt = document.createElement("option");
      opt.value = items[i].value;
      opt.textContent = items[i].text;
      selectEl.appendChild(opt);
    }
  }

  function showGroupAndRoleModal(title, callback) {
    var body =
      '<div class="mb-3"><label class="form-label">' +
      i18next.t("Group") +
      "</label>" +
      '<select id="ss-group-select"></select></div>' +
      '<div class="mb-3 d-none" id="ss-role-wrapper"><label class="form-label">' +
      i18next.t("Role") +
      "</label>" +
      '<select id="ss-role-select"></select></div>';

    var result = createModal(title, body);
    var selectedGroupId = null;
    var selectedRoleId = null;

    window.CRM.groups.get().done(function (groups) {
      var groupEl = document.getElementById("ss-group-select");
      populateSelect(
        groupEl,
        groups.map(function (g) {
          return { value: String(g.Id), text: g.Name };
        }),
      );

      result.el.addEventListener(
        "shown.bs.modal",
        function () {
          var roleWrapper = document.getElementById("ss-role-wrapper");
          var roleEl = document.getElementById("ss-role-select");

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
                populateSelect(
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

  function getPersonIdsByRole(role) {
    var ids = [];
    $(".ss-member").each(function () {
      var $el = $(this);
      if (role === "all" || $el.data("role") === role) {
        ids.push(Number($el.data("person-id")));
      }
    });
    return ids;
  }

  $(document).ready(function () {
    // Print button
    $("#printClass").on("click", function () {
      window.print();
    });

    // Copy phone numbers to clipboard
    $(document).on("click", ".copy-phones-btn", function () {
      var phones = $(this).data("phones") || "";
      if (!phones) return;
      if (navigator.clipboard) {
        navigator.clipboard
          .writeText(phones)
          .then(function () {
            window.CRM.notify(i18next.t("Phone numbers copied to clipboard"), {
              type: "success",
              delay: 3000,
            });
          })
          .catch(function () {
            prompt(i18next.t("Press CTRL + C to copy all group members' phone numbers"), phones);
          });
      } else {
        prompt(i18next.t("Press CTRL + C to copy all group members' phone numbers"), phones);
      }
    });

    window.CRM.onLocalesReady(function () {
      // Copy to Group
      $(document).on("click", ".ss-copy-role", function (e) {
        e.preventDefault();
        var role = $(this).data("role");
        showGroupAndRoleModal(i18next.t("Copy Members to Group"), function (data) {
          var ids = getPersonIdsByRole(role);
          ids.forEach(function (personId) {
            window.CRM.groups.addPerson(data.GroupID, personId, data.RoleID);
          });
          if (ids.length > 0) {
            window.CRM.notify(i18next.t("Copied") + " " + ids.length + " " + i18next.t("members"), {
              type: "success",
              delay: 3000,
            });
          }
        });
      });

      // Move to Group
      $(document).on("click", ".ss-move-role", function (e) {
        e.preventDefault();
        var role = $(this).data("role");
        var label = role === "all" ? i18next.t("all members") : $(this).text().trim();
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
            if (!result) return;
            showGroupAndRoleModal(i18next.t("Move Members to Group"), function (data) {
              var ids = getPersonIdsByRole(role);
              ids.forEach(function (personId) {
                window.CRM.groups.addPerson(data.GroupID, personId, data.RoleID);
                window.CRM.groups.removePerson(window.CRM.currentGroup, personId);
              });
              if (ids.length > 0) {
                setTimeout(function () {
                  location.reload();
                }, 1500);
              }
            });
          },
        });
      });
    });
  });
})();
