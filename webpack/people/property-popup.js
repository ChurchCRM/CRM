/**
 * Add / edit property popup shared by the person and family pages.
 * The select lists unassigned properties only; an assigned property that takes a value
 * is edited through its pencil button, which reopens the popup on that property.
 */
export function initPropertyPopup({ modalId, selectId, promptBoxId, assignBtnId, onSave }) {
  const modalEl = document.getElementById(modalId);
  const select = document.getElementById(selectId);
  const promptBox = document.getElementById(promptBoxId);
  const assignBtn = document.getElementById(assignBtnId);
  if (!modalEl || !select || !promptBox || !assignBtn) return;

  const title = modalEl.querySelector(".modal-title");
  const selectWrap = select.closest(".mb-3");
  const labels = { add: title.textContent, assign: assignBtn.innerHTML };
  let editId = null;

  const showPrompt = (prompt, value) => {
    promptBox.replaceChildren();
    if (!prompt) return;
    const label = document.createElement("label");
    label.className = "form-label";
    label.textContent = prompt;
    const textarea = document.createElement("textarea");
    textarea.rows = 3;
    textarea.className = "form-control";
    textarea.value = value || "";
    promptBox.append(label, textarea);
  };

  select.addEventListener("change", () => showPrompt(select.selectedOptions[0]?.dataset.pro_prompt, ""));

  for (const btn of document.querySelectorAll(".edit-property-btn")) {
    btn.addEventListener("click", () => {
      editId = btn.dataset.property_id;
      title.textContent = `${modalEl.dataset.editTitle}: ${btn.dataset.property_name}`;
      assignBtn.innerHTML = `<i class="fa-solid fa-check me-1"></i>${modalEl.dataset.saveLabel}`;
      selectWrap.classList.add("d-none");
      showPrompt(btn.dataset.prompt, btn.dataset.value);
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
  }

  modalEl.addEventListener("hidden.bs.modal", () => {
    editId = null;
    title.textContent = labels.add;
    assignBtn.innerHTML = labels.assign;
    selectWrap.classList.remove("d-none");
    select.value = "";
    promptBox.replaceChildren();
  });

  assignBtn.addEventListener("click", () => {
    const propertyId = editId ?? select.value;
    if (!propertyId) return;
    onSave(propertyId, promptBox.querySelector("textarea")?.value ?? "");
  });
}
