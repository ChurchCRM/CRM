import { buildAdminAPIUrl } from "./api-utils";
import { confirmAction, promptAction } from "./common/confirm-action";
import { notifyError, notifySuccess } from "./common/notify";

/**
 * People Settings → Important Date Types: add types from suggestions or by name,
 * rename them, and turn their options on and off in place.
 */

function esc(text) {
  return window.CRM.escapeHtml(String(text ?? ""));
}

async function request(path, method = "GET", body = undefined) {
  const response = await fetch(buildAdminAPIUrl(path), {
    method,
    headers: { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.message || i18next.t("Could not save the date type"));
  }
  return data;
}

function appliesToLabel(appliesTo) {
  return appliesTo === "family" ? i18next.t("Families") : i18next.t("People");
}

function toggle(type, field, label) {
  return `<td class="text-center">
    <label class="form-check form-switch d-inline-block m-0">
      <input class="form-check-input idt-toggle" type="checkbox" data-id="${type.id}" data-field="${field}"${type[field] ? " checked" : ""} aria-label="${esc(`${label}: ${type.name}`)}">
    </label>
  </td>`;
}

function rowHtml(type) {
  const usage = i18next.t("{{count}} recorded", { count: type.usage });
  const action =
    type.usage === 0
      ? `<button type="button" class="btn btn-sm btn-ghost-danger idt-delete" data-id="${type.id}" title="${esc(i18next.t("Delete"))}" aria-label="${esc(i18next.t("Delete"))}"><i class="fa-solid fa-trash"></i></button>`
      : "";
  return `<tr data-id="${type.id}"${type.active ? "" : ' class="text-body-secondary"'}>
    <td>
      <button type="button" class="btn btn-link p-0 idt-rename" data-id="${type.id}" title="${esc(i18next.t("Rename"))}">${esc(type.name)}</button>
    </td>
    <td>${esc(appliesToLabel(type.appliesTo))}<div class="small text-body-secondary">${esc(usage)}</div></td>
    ${toggle(type, "allowMultiple", i18next.t("Several per record"))}
    ${toggle(type, "showOnCalendar", i18next.t("On staff calendar"))}
    ${toggle(type, "active", i18next.t("On"))}
    <td>${action}</td>
  </tr>`;
}

function init(root) {
  const tbody = root.querySelector("#important-date-types-table tbody");
  const presets = root.querySelector("#important-date-presets");
  const presetsCard = root.querySelector("#important-date-presets-card");
  const form = root.querySelector("#important-date-type-form");
  let state = { types: [], presets: [] };

  function render() {
    tbody.innerHTML =
      state.types.length === 0
        ? `<tr><td colspan="6" class="text-body-secondary">${esc(i18next.t("No date types yet. Add a suggested type or your own."))}</td></tr>`
        : state.types.map(rowHtml).join("");
    presetsCard.classList.toggle("d-none", state.presets.length === 0);
    presets.innerHTML = state.presets
      .map(
        (p) =>
          `<button type="button" class="btn btn-outline-primary btn-sm idt-preset" data-key="${esc(p.key)}"><i class="fa-solid fa-plus me-1"></i>${esc(p.name)} <span class="text-body-secondary ms-1">${esc(appliesToLabel(p.appliesTo))}</span></button>`,
      )
      .join("");
  }

  async function run(action, success) {
    try {
      state = await action();
      render();
      if (success) {
        notifySuccess(success);
      }
    } catch (e) {
      notifyError(esc(e.message));
      render();
    }
  }

  presets.addEventListener("click", (event) => {
    const button = event.target.closest(".idt-preset");
    if (button) {
      run(() => request("important-date-types", "POST", { preset: button.dataset.key }), i18next.t("Date type added"));
    }
  });

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const body = {
      name: form.elements.name.value,
      appliesTo: form.elements.appliesTo.value,
      allowMultiple: form.elements.allowMultiple.checked,
      showOnCalendar: form.elements.showOnCalendar.checked,
    };
    run(async () => {
      const result = await request("important-date-types", "POST", body);
      form.reset();
      form.elements.showOnCalendar.checked = true;
      return result;
    }, i18next.t("Date type added"));
  });

  tbody.addEventListener("change", (event) => {
    const input = event.target.closest(".idt-toggle");
    if (input) {
      run(() => request(`important-date-types/${input.dataset.id}`, "PATCH", { [input.dataset.field]: input.checked }));
    }
  });

  tbody.addEventListener("click", (event) => {
    const rename = event.target.closest(".idt-rename");
    const remove = event.target.closest(".idt-delete");
    if (rename) {
      const type = state.types.find((t) => String(t.id) === rename.dataset.id);
      promptAction(
        i18next.t("Rename date type"),
        (value) =>
          run(
            () => request(`important-date-types/${type.id}`, "PATCH", { name: value }),
            i18next.t("Date type renamed"),
          ),
        { value: type.name, confirmLabel: i18next.t("Rename") },
      );
    }
    if (remove) {
      const type = state.types.find((t) => String(t.id) === remove.dataset.id);
      confirmAction(
        i18next.t("Delete date type"),
        esc(i18next.t("Delete {{name}}?", { name: type.name, interpolation: { escapeValue: false } })),
        () => run(() => request(`important-date-types/${type.id}`, "DELETE"), i18next.t("Date type deleted")),
        { tone: "danger", confirmLabel: i18next.t("Delete") },
      );
    }
  });

  run(() => request("important-date-types"));
}

window.CRM.onLocalesReady(() => {
  const root = document.getElementById("important-date-types");
  if (root) {
    init(root);
  }
});
