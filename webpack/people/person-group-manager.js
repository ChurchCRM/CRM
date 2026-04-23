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

const MODAL_ID = "personGroupModal";

/**
 * Create a Bootstrap 5 modal (not yet shown). Returns { modal, el, confirm }.
 * Caller must attach any `shown.bs.modal` listeners, then call `modal.show()`.
 */
function createModal(title, bodyHtml) {
  const existing = document.getElementById(MODAL_ID);
  if (existing) {
    const bsModal = window.bootstrap.Modal.getInstance(existing);
    if (bsModal) bsModal.dispose();
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
    '<button type="button" class="btn btn-primary" id="personGroupConfirmBtn" disabled>' +
    i18next.t("Save") +
    "</button>" +
    "</div>" +
    "</div>" +
    "</div>";

  document.body.appendChild(wrapper);

  const modal = new window.bootstrap.Modal(wrapper);
  const confirmBtn = wrapper.querySelector("#personGroupConfirmBtn");

  // Cleanup on close
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
  // Fetch groups first so data is ready when the modal opens
  window.CRM.groups.get().done((groups) => {
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

    const { modal, el, confirm } = createModal(i18next.t("Add to Group"), body);

    let selectedGroupId = null;
    let selectedRoleId = null;

    // Populate <option> elements now (while modal is hidden, before TomSelect)
    const groupEl = document.getElementById("pgm-group-select");
    groups.forEach((g) => {
      const opt = document.createElement("option");
      opt.value = String(g.Id);
      opt.textContent = g.Name;
      groupEl.appendChild(opt);
    });

    // TomSelect must init AFTER modal is visible (needs layout dimensions).
    // Register listener BEFORE show() to avoid race condition.
    el.addEventListener(
      "shown.bs.modal",
      () => {
        const roleWrapper = document.getElementById("pgm-role-wrapper");
        const roleEl = document.getElementById("pgm-role-select");

        new window.TomSelect(groupEl, {
          placeholder: i18next.t("Search groups..."),
          items: [],
          onChange: (value) => {
            selectedGroupId = value || null;
            if (!value) {
              roleWrapper.classList.add("d-none");
              confirm.disabled = true;
              return;
            }
            loadRoles(value, roleEl, roleWrapper, confirm, (roleId) => {
              selectedRoleId = roleId;
            });
          },
        });
      },
      { once: true },
    );

    // NOW show — the listener above will fire after fade animation completes
    modal.show();

    confirm.addEventListener("click", () => {
      if (!selectedGroupId) return;
      confirm.disabled = true;
      window.CRM.groups.addPerson(selectedGroupId, personId, selectedRoleId).done(() => {
        modal.hide();
        location.reload();
      });
    });
  });
}

/**
 * Load roles for a group into a select element. If only 1 role, auto-select it
 * and hide the wrapper. If >1, show TomSelect.
 */
function loadRoles(groupId, roleEl, roleWrapper, confirmBtn, onRoleSelected) {
  if (roleEl.tomselect) roleEl.tomselect.destroy();
  roleEl.innerHTML = "";
  roleWrapper.classList.add("d-none");

  window.CRM.groups.getRoles(groupId).done((roles) => {
    if (roles.length === 0) {
      onRoleSelected(null);
      confirmBtn.disabled = false;
      return;
    }

    if (roles.length === 1) {
      onRoleSelected(String(roles[0].OptionId));
      confirmBtn.disabled = false;
      return;
    }

    // Multiple roles — show picker
    populateSelect(
      roleEl,
      roles.map((r) => ({ value: String(r.OptionId), text: i18next.t(r.OptionName) })),
    );

    roleWrapper.classList.remove("d-none");
    confirmBtn.disabled = false;

    new window.TomSelect(roleEl, {
      onChange: (value) => {
        onRoleSelected(value || null);
      },
    });

    onRoleSelected(String(roles[0].OptionId));
  });
}

/**
 * Change a person's role in a group. Fetches roles first — if only 1 exists,
 * shows a notification instead of a modal. Pre-selects current role.
 */
function handleChangeRole(personId, groupId, currentRoleId) {
  window.CRM.groups.getRoles(groupId).done((roles) => {
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

    const { modal, el, confirm } = createModal(i18next.t("Change Role"), body);
    confirm.disabled = false;

    let selectedRoleId = currentRoleId ? String(currentRoleId) : String(roles[0].OptionId);

    // Populate options now
    const roleEl = document.getElementById("pgm-role-select");
    populateSelect(
      roleEl,
      roles.map((r) => ({ value: String(r.OptionId), text: i18next.t(r.OptionName) })),
    );

    // Init TomSelect after modal is visible
    el.addEventListener(
      "shown.bs.modal",
      () => {
        const ts = new window.TomSelect(roleEl, {
          onChange: (value) => {
            selectedRoleId = value || null;
          },
        });

        if (currentRoleId) {
          ts.setValue(String(currentRoleId), true);
        }
      },
      { once: true },
    );

    modal.show();

    confirm.addEventListener("click", () => {
      if (!selectedRoleId) return;
      confirm.disabled = true;
      window.CRM.groups.addPerson(groupId, personId, selectedRoleId).done(() => {
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
    message: `${i18next.t("Are you sure you want to remove this person from")} <strong>${groupName}</strong>?`,
    buttons: {
      cancel: { label: i18next.t("Cancel"), className: "btn-ghost-secondary" },
      confirm: { label: i18next.t("Remove"), className: "btn-danger" },
    },
    callback: (result) => {
      if (result) {
        window.CRM.groups.removePerson(groupId, personId).done(() => {
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
  const personId = window.CRM?.currentPersonID;
  if (!personId) return;

  document.addEventListener("click", (e) => {
    const addBtn = e.target.closest("#addGroup, #addGroupFromEmpty");
    if (addBtn) {
      e.preventDefault();
      handleAddToGroup(personId);
      return;
    }

    const roleBtn = e.target.closest(".changeRole");
    if (roleBtn) {
      e.preventDefault();
      handleChangeRole(personId, roleBtn.dataset.groupid, roleBtn.dataset.currentRoleId);
      return;
    }

    const removeBtn = e.target.closest(".groupRemove");
    if (removeBtn) {
      e.preventDefault();
      handleRemoveFromGroup(personId, removeBtn.dataset.groupid, removeBtn.dataset.groupname);
      return;
    }
  });
}
