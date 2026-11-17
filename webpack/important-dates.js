import { buildAPIUrl } from "./api-utils";
import { confirmAction } from "./common/confirm-action";
import { notifyError, notifySuccess } from "./common/notify";

/**
 * Important Dates card on the person and family views: every yearly date,
 * soonest first, with inline add, edit and remove.
 */

const SOURCE_ICONS = {
  birthday: "fa-cake-candles",
  membership: "fa-id-card",
  friend: "fa-handshake",
  death: "fa-dove",
  wedding: "fa-ring",
  custom: "fa-calendar-day",
};

function esc(text) {
  return window.CRM.escapeHtml(String(text ?? ""));
}

async function request(path, method = "GET", body = undefined) {
  const response = await fetch(buildAPIUrl(path), {
    method,
    headers: { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.message || i18next.t("Could not save the date"));
  }
  return data;
}

function monthNames() {
  const format = new Intl.DateTimeFormat(window.CRM.shortLocale || undefined, { month: "long", timeZone: "UTC" });
  return Array.from({ length: 12 }, (_, i) => format.format(new Date(Date.UTC(2000, i, 1))));
}

function daysInMonth(month) {
  return new Date(Date.UTC(2000, month, 0)).getUTCDate();
}

function relativeBadge(date) {
  if (date.daysUntil === null) {
    return "";
  }
  let tone = "bg-secondary-lt text-secondary";
  if (date.daysUntil === 0) {
    tone = "bg-success-lt text-success";
  } else if (date.daysUntil <= 7) {
    tone = "bg-primary-lt text-primary";
  }
  return `<span class="badge ${tone}">${esc(date.relative)}</span>`;
}

function rowHtml(date, canEdit) {
  const icon = SOURCE_ICONS[date.source] || SOURCE_ICONS.custom;
  const years = date.yearsLabel ? `<span class="text-body-secondary small ms-1">${esc(date.yearsLabel)}</span>` : "";
  const note = date.note ? `<small class="text-body-secondary d-block">${esc(date.note)}</small>` : "";
  const turnedOff =
    date.typeActive === false
      ? `<span class="badge bg-warning-lt text-warning ms-1">${esc(i18next.t("Type turned off"))}</span>`
      : "";
  let actions = "";
  if (canEdit && date.editable) {
    actions = `
      <button type="button" class="btn btn-sm btn-ghost-secondary important-date-edit" data-id="${date.id}" title="${esc(i18next.t("Edit"))}" aria-label="${esc(i18next.t("Edit"))}"><i class="fa-solid fa-pen"></i></button>
      <button type="button" class="btn btn-sm btn-ghost-danger important-date-remove" data-id="${date.id}" title="${esc(i18next.t("Remove"))}" aria-label="${esc(i18next.t("Remove"))}"><i class="fa-solid fa-trash"></i></button>`;
  } else if (canEdit && date.editUrl) {
    actions = `<a class="btn btn-sm btn-ghost-secondary" href="${esc(window.CRM.root + date.editUrl)}" title="${esc(i18next.t("Edit in the record"))}" aria-label="${esc(i18next.t("Edit in the record"))}"><i class="fa-solid fa-pen"></i></a>`;
  }

  return `
    <div class="list-group-item px-0 d-flex align-items-start gap-2" data-source="${esc(date.source)}" data-id="${date.id ?? ""}">
      <i class="fa-solid ${icon} text-body-secondary mt-1"></i>
      <div class="me-auto">
        <strong>${esc(date.label)}</strong>${turnedOff}
        <div class="small">${esc(date.recorded)}${years}</div>
        ${note}
      </div>
      <div class="text-end text-nowrap">
        ${relativeBadge(date)}
        <div class="mt-1">${actions}</div>
      </div>
    </div>`;
}

function initCard(card) {
  const owner = card.dataset.owner;
  const basePath = `${owner}/${card.dataset.ownerId}/important-dates`;
  const list = card.querySelector("#important-dates-list");
  const form = card.querySelector("#important-dates-form");
  const addButton = card.querySelector("#important-dates-add");
  const typeRow = card.querySelector("#important-dates-type-row");
  const typeSelect = form.querySelector("[name=typeId]");
  const monthSelect = form.querySelector("[name=month]");
  const daySelect = form.querySelector("[name=day]");
  let state = { dates: [], types: [], canEdit: false, canManageTypes: false };

  monthSelect.innerHTML = monthNames()
    .map((name, i) => `<option value="${i + 1}">${esc(name)}</option>`)
    .join("");

  function fillDays(selected) {
    const month = Number(monthSelect.value) || 1;
    const keep = Math.min(Number(selected || daySelect.value) || 1, daysInMonth(month));
    daySelect.innerHTML = Array.from({ length: daysInMonth(month) }, (_, i) => i + 1)
      .map((d) => `<option value="${d}"${d === keep ? " selected" : ""}>${d}</option>`)
      .join("");
  }
  monthSelect.addEventListener("change", () => fillDays());

  function emptyState() {
    if (state.types.length === 0) {
      const setup = state.canManageTypes
        ? ` <a href="${esc(card.dataset.manageTypesUrl)}">${esc(i18next.t("Set up date types"))}</a>`
        : "";
      return `<p class="text-body-secondary small mb-0">${esc(i18next.t("No yearly dates recorded."))}${setup}</p>`;
    }
    return `<p class="text-body-secondary small mb-0">${esc(i18next.t("No yearly dates recorded. Add baptisms, dedications and other dates your church celebrates."))}</p>`;
  }

  function render() {
    addButton.classList.toggle("d-none", !(state.canEdit && state.types.some((t) => t.allowMultiple || !t.recorded)));
    list.innerHTML =
      state.dates.length === 0
        ? emptyState()
        : `<div class="list-group list-group-flush">${state.dates.map((d) => rowHtml(d, state.canEdit)).join("")}</div>`;
  }

  function closeForm() {
    form.classList.add("d-none");
    addButton.setAttribute("aria-expanded", "false");
    form.reset();
    form.elements.dateId.value = "";
  }

  function openForm(date = null) {
    form.reset();
    form.elements.dateId.value = date ? date.id : "";
    typeRow.classList.toggle("d-none", date !== null);
    if (date === null) {
      typeSelect.innerHTML = state.types
        .map((t) => {
          const taken = t.recorded && !t.allowMultiple;
          const label = taken ? `${t.name} (${i18next.t("already recorded")})` : t.name;
          return `<option value="${t.id}"${taken ? " disabled" : ""}>${esc(label)}</option>`;
        })
        .join("");
      const firstOpen = state.types.find((t) => t.allowMultiple || !t.recorded);
      if (firstOpen) {
        typeSelect.value = String(firstOpen.id);
      }
    }
    monthSelect.value = String(date ? date.month : 1);
    fillDays(date ? date.day : 1);
    form.elements.year.max = String(new Date().getFullYear());
    form.elements.year.value = date?.year ?? "";
    form.elements.note.value = date?.note ?? "";
    form.classList.remove("d-none");
    addButton.setAttribute("aria-expanded", "true");
    (date === null ? typeSelect : monthSelect).focus();
  }

  async function load() {
    try {
      state = await request(basePath);
      render();
    } catch (e) {
      list.innerHTML = `<p class="text-danger small mb-0">${esc(e.message)}</p>`;
    }
  }

  addButton.addEventListener("click", () => openForm());
  card.querySelector("#important-dates-cancel").addEventListener("click", closeForm);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const dateId = form.elements.dateId.value;
    const body = {
      month: Number(monthSelect.value),
      day: Number(daySelect.value),
      year: form.elements.year.value === "" ? null : Number(form.elements.year.value),
      note: form.elements.note.value,
    };
    try {
      if (dateId === "") {
        body.typeId = Number(typeSelect.value);
        state = await request(basePath, "POST", body);
      } else {
        state = await request(`${basePath}/${dateId}`, "PUT", body);
      }
      closeForm();
      render();
      notifySuccess(i18next.t("Date saved"));
    } catch (e) {
      notifyError(esc(e.message));
    }
  });

  list.addEventListener("click", (event) => {
    const edit = event.target.closest(".important-date-edit");
    const remove = event.target.closest(".important-date-remove");
    if (edit) {
      openForm(state.dates.find((d) => String(d.id) === edit.dataset.id && d.source === "custom"));
    }
    if (remove) {
      const date = state.dates.find((d) => String(d.id) === remove.dataset.id && d.source === "custom");
      confirmAction(
        i18next.t("Remove date"),
        esc(
          i18next.t("Remove {{label}} ({{date}})?", {
            label: date.label,
            date: date.recorded,
            interpolation: { escapeValue: false },
          }),
        ),
        async () => {
          try {
            state = await request(`${basePath}/${date.id}`, "DELETE");
            closeForm();
            render();
            notifySuccess(i18next.t("Date removed"));
          } catch (e) {
            notifyError(esc(e.message));
          }
        },
        { tone: "danger", confirmLabel: i18next.t("Remove") },
      );
    }
  });

  load();
}

window.CRM.onLocalesReady(() => {
  const card = document.getElementById("important-dates-card");
  if (card) {
    initCard(card);
  }
});
