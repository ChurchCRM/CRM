/**
 * Group interaction manager for PersonView.php
 *
 * Handles:
 * - Add person to a group (searchable TomSelect dropdown + role)
 * - Change person's role in a group (only when >1 role exists)
 * - Remove person from a group
 *
 * Uses Bootstrap 5 Modal API (via Tabler) and TomSelect (from churchcrm bundle).
 * Replaces the legacy jQuery handlers in skin/js/PersonView.js.
 */
// TomSelect is loaded globally by churchcrm.min.js as window.TomSelect.
// Do NOT import it here — that would bundle a second copy and add ~500KB.
const TomSelect = window.TomSelect;

const MODAL_ID = "personGroupModal";

/**
 * Create and show a Bootstrap 5 modal with the given title and body HTML.
 * Returns { modal, el, confirm } where confirm is the OK button element.
 */
function showModal(title, bodyHtml) {
  let existing = document.getElementById(MODAL_ID);
  if (existing) {
    const bsModal = window.bootstrap.Modal.getInstance(existing);
    if (bsModal) bsModal.dispose();
    existing.remove();
  }

  const wrapper = document.createElement("div");
  wrapper.id = MODAL_ID;
  wrapper.className = "modal modal-blur fade";
  wrapper.setAttribute("tabindex", "-1");
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
    '<button type="button" class="btn btn-primary" id="personGroupConfirmBtn" disabled>' +
    i18next.t("Save") +
    "</button>" +
    "</div>" +
    "</div>" +
    "</div>";

  document.body.appendChild(wrapper);

  const modal = new window.bootstrap.Modal(wrapper);
  const confirmBtn = wrapper.querySelector("#personGroupConfirmBtn");

  // Remove tabindex after shown so TomSelect dropdowns work
  wrapper.addEventListener(
    "shown.bs.modal",
    function () {
      wrapper.removeAttribute("tabindex");
    },
    { once: true },
  );

  // Cleanup on close
  wrapper.addEventListener(
    "hidden.bs.modal",
    function () {
      modal.dispose();
      wrapper.remove();
    },
    { once: true },
  );

  modal.show();
  return { modal, el: wrapper, confirm: confirmBtn };
}

/**
 * Populate a <select> with <option> elements from an array of {value, text}.
 */
function populateSelect(selectEl, items) {
  selectEl.innerHTML = "";
  for (let i = 0; i < items.length; i++) {
    const opt = document.createElement("option");
    opt.value = items[i].value;
    opt.textContent = items[i].text;
    selectEl.appendChild(opt);
  }
}

/**
 * Add person to a group — shows searchable group picker, then role picker.
 */
function handleAddToGroup(personId) {
  const body =
    '<div class="mb-3">' +
    '<label class="form-label">' +
    i18next.t("Group") +
    "</label>" +
    '<select id="pgm-group-select"></select>' +
    "</div>" +
    '<div class="mb-3 d-none" id="pgm-role-wrapper">' +
    '<label class="form-label">' +
    i18next.t("Role") +
    "</label>" +
    '<select id="pgm-role-select"></select>' +
    "</div>";

  const { modal, el, confirm } = showModal(i18next.t("Add to Group"), body);

  let selectedGroupId = null;
  let selectedRoleId = null;

  el.addEventListener(
    "shown.bs.modal",
    function () {
      const groupEl = document.getElementById("pgm-group-select");
      const roleWrapper = document.getElementById("pgm-role-wrapper");
      const roleEl = document.getElementById("pgm-role-select");

      // Fetch all groups, then init TomSelect
      window.CRM.groups.get().done(function (groups) {
        populateSelect(
          groupEl,
          groups.map(function (g) {
            return { value: String(g.Id), text: g.Name };
          }),
        );

        // Add empty placeholder option at start
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "";
        groupEl.insertBefore(placeholder, groupEl.firstChild);
        groupEl.value = "";

        new TomSelect(groupEl, {
          placeholder: i18next.t("Search groups..."),
          allowEmptyOption: true,
          onChange: function (value) {
            selectedGroupId = value || null;
            if (!value) {
              roleWrapper.classList.add("d-none");
              confirm.disabled = true;
              return;
            }
            loadRoles(value, roleEl, roleWrapper, confirm, function (roleId) {
              selectedRoleId = roleId;
            });
          },
        });
      });
    },
    { once: true },
  );

  confirm.addEventListener("click", function () {
    if (!selectedGroupId) return;
    confirm.disabled = true;
    window.CRM.groups.addPerson(selectedGroupId, personId, selectedRoleId).done(function () {
      modal.hide();
      location.reload();
    });
  });
}

/**
 * Load roles for a group into a select element. If only 1 role, auto-select it
 * and hide the wrapper. If >1, show TomSelect.
 */
function loadRoles(groupId, roleEl, roleWrapper, confirmBtn, onRoleSelected) {
  // Destroy previous TomSelect if any
  if (roleEl.tomselect) roleEl.tomselect.destroy();
  roleEl.innerHTML = "";
  roleWrapper.classList.add("d-none");

  window.CRM.groups.getRoles(groupId).done(function (roles) {
    if (roles.length === 0) {
      onRoleSelected(null);
      confirmBtn.disabled = false;
      return;
    }

    if (roles.length === 1) {
      // Auto-select the only role, no need to show picker
      onRoleSelected(String(roles[0].OptionId));
      confirmBtn.disabled = false;
      return;
    }

    // Multiple roles — show picker
    populateSelect(
      roleEl,
      roles.map(function (r) {
        return { value: String(r.OptionId), text: i18next.t(r.OptionName) };
      }),
    );

    roleWrapper.classList.remove("d-none");
    confirmBtn.disabled = false;

    new TomSelect(roleEl, {
      onChange: function (value) {
        onRoleSelected(value || null);
      },
    });

    // Default to first role
    onRoleSelected(String(roles[0].OptionId));
  });
}

/**
 * Change a person's role in a group. Fetches roles first — if only 1 exists,
 * shows a notification instead of a modal.
 */
function handleChangeRole(personId, groupId) {
  window.CRM.groups.getRoles(groupId).done(function (roles) {
    if (roles.length <= 1) {
      window.CRM.notify(i18next.t("This group only has one role."), { type: "info" });
      return;
    }

    const body =
      '<div class="mb-3">' +
      '<label class="form-label">' +
      i18next.t("New Role") +
      "</label>" +
      '<select id="pgm-role-select"></select>' +
      "</div>";

    const { modal, el, confirm } = showModal(i18next.t("Change Role"), body);
    confirm.disabled = false;

    let selectedRoleId = String(roles[0].OptionId);

    el.addEventListener(
      "shown.bs.modal",
      function () {
        const roleEl = document.getElementById("pgm-role-select");
        populateSelect(
          roleEl,
          roles.map(function (r) {
            return { value: String(r.OptionId), text: i18next.t(r.OptionName) };
          }),
        );

        new TomSelect(roleEl, {
          onChange: function (value) {
            selectedRoleId = value || null;
          },
        });
      },
      { once: true },
    );

    confirm.addEventListener("click", function () {
      if (!selectedRoleId) return;
      confirm.disabled = true;
      window.CRM.groups.addPerson(groupId, personId, selectedRoleId).done(function () {
        modal.hide();
        location.reload();
      });
    });
  });
}

/**
 * Remove a person from a group with a confirmation dialog.
 */
function handleRemoveFromGroup(personId, groupId, groupName) {
  bootbox.confirm({
    title: i18next.t("Remove from Group"),
    message: i18next.t("Are you sure you want to remove this person from") + " <strong>" + groupName + "</strong>?",
    buttons: {
      cancel: { label: i18next.t("Cancel"), className: "btn-ghost-secondary" },
      confirm: { label: i18next.t("Remove"), className: "btn-danger" },
    },
    callback: function (result) {
      if (result) {
        window.CRM.groups.removePerson(groupId, personId).done(function () {
          location.reload();
        });
      }
    },
  });
}

/**
 * Initialise all group interaction handlers via event delegation.
 * Call once on DOMContentLoaded.
 */
export function initGroupManager() {
  const personId = window.CRM && window.CRM.currentPersonID;
  if (!personId) return;

  document.addEventListener("click", function (e) {
    // Add to Group (toolbar dropdown item or empty-state button)
    const addBtn = e.target.closest("#addGroup, #addGroupFromEmpty");
    if (addBtn) {
      e.preventDefault();
      handleAddToGroup(personId);
      return;
    }

    // Change Role (group row dropdown item)
    const roleBtn = e.target.closest(".changeRole");
    if (roleBtn) {
      e.preventDefault();
      handleChangeRole(personId, roleBtn.dataset.groupid);
      return;
    }

    // Remove from Group (group row dropdown item)
    const removeBtn = e.target.closest(".groupRemove");
    if (removeBtn) {
      e.preventDefault();
      handleRemoveFromGroup(personId, removeBtn.dataset.groupid, removeBtn.dataset.groupname);
      return;
    }
  });
}
