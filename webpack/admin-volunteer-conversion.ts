/**
 * Admin → Convert Volunteer Opportunities (#10606): show what the conversion would do as
 * the administrator chooses where the opportunities go, then run it.
 */

import { escapeAttribute, escapeHtml } from "./utils/escape-html";

interface ConversionConfig {
  ministries: Array<{ id: number; name: string }>;
  opportunityCount: number;
  unconverted: number;
  version: string;
  defaultMinistryName: string;
  csrfToken: string;
}

interface PlannedOpportunity {
  id: number;
  name: string;
  active: boolean;
  converted: boolean;
  becomes: "position" | "ministry";
  newName: string;
  renamed: boolean;
  people: Array<{ id: number; name: string }>;
  skipped: Array<{ personId: number; reason: string; name: string }>;
  pending: Array<{ id: number; name: string }>;
}

interface Plan {
  version: string;
  target: { id: number | null; name: string; exists: boolean };
  opportunities: PlannedOpportunity[];
  totals: {
    positions: number;
    ministries: number;
    qualifications: number;
    skipped: number;
    converted: number;
    pending: number;
  };
  revision: string;
  message?: string;
}

interface Choice {
  ministryId?: number;
  ministryName?: string;
  ownMinistry: number[];
}

const ownMinistry = new Set<number>();
/** The last plan shown and the choice it was made for; null while a newer choice waits for its plan. */
let latest: { plan: Plan; choice: Choice } | null = null;
let requestSeq = 0;

const root = (): string => window.CRM?.root ?? "";
const config = (): ConversionConfig => window.CRM?.volunteerConversion as ConversionConfig;

function t(key: string, options?: Record<string, unknown>): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  return i18next.t(key, { ...options, interpolation: { escapeValue: false } });
}

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function choice(): Choice {
  const existing = byId<HTMLInputElement>("volunteerConversionTargetExisting")?.checked;
  const target = existing
    ? { ministryId: Number(byId<HTMLSelectElement>("volunteerConversionMinistry")?.value ?? 0) }
    : { ministryName: byId<HTMLInputElement>("volunteerConversionMinistryName")?.value ?? "" };
  return { ...target, ownMinistry: [...ownMinistry].sort((a, b) => a - b) };
}

function sameChoice(a: Choice, b: Choice): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}

function previewUrl(snapshot: Choice): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(snapshot)) {
    params.set(key, Array.isArray(value) ? value.join(",") : String(value));
  }
  return `${root()}/admin/api/volunteer-conversion/preview?${params.toString()}`;
}

/** A changed choice: Convert waits until the plan for it is on screen. */
function invalidate(): void {
  latest = null;
  const run = byId<HTMLButtonElement>("volunteerConversionRun");
  if (run) {
    run.disabled = true;
  }
}

function skippedNote(item: PlannedOpportunity): string {
  if (item.skipped.length === 0) {
    return "";
  }
  const names = item.skipped
    .map((skip) =>
      skip.reason === "deceased"
        ? `${skip.name} (${t("deceased")})`
        : `#${skip.personId} (${t("no longer in ChurchCRM")})`,
    )
    .join(", ");
  return `<div class="text-warning">${escapeHtml(t("Not converted"))}: ${escapeHtml(names)}</div>`;
}

function renderRow(item: PlannedOpportunity): string {
  const name = item.name.trim() === "" ? `<em>${escapeHtml(t("No name"))}</em>` : escapeHtml(item.name);
  const inactive = item.active ? "" : ` <span class="badge bg-secondary-lt">${escapeHtml(t("Inactive"))}</span>`;
  const people = item.people.map((person) => escapeHtml(person.name)).join(", ");
  const volunteers = `<details><summary>${item.people.length}</summary><div class="small text-secondary">${people || "—"}</div></details>`;

  if (item.converted) {
    const pending =
      item.pending.length > 0
        ? `<div class="small text-secondary mt-1">${escapeHtml(t("Linked since: {{names}}", { names: item.pending.map((person) => person.name).join(", ") }))}</div>`
        : "";
    return `<tr data-opportunity="${item.id}"><td>${name}${inactive}</td><td colspan="3"><span class="badge bg-success-lt">${escapeHtml(t("Converted"))}</span>${pending}</td></tr>`;
  }

  const becomes = `<select class="form-select form-select-sm w-auto" data-becomes="${item.id}" aria-label="${escapeAttribute(t("What it becomes"))}">
      <option value="position" ${item.becomes === "position" ? "selected" : ""}>${escapeHtml(t("A position"))}</option>
      <option value="ministry" ${item.becomes === "ministry" ? "selected" : ""}>${escapeHtml(t("Its own ministry"))}</option>
    </select>
    <div class="small text-secondary mt-1" data-new-name="${item.id}">${escapeHtml(item.newName)}</div>`;
  const notes = [
    item.renamed ? `<div>${escapeHtml(t("Renamed to keep names unique"))}</div>` : "",
    skippedNote(item),
  ].join("");

  return `<tr data-opportunity="${item.id}"><td>${name}${inactive}</td><td>${becomes}</td><td>${volunteers}</td><td class="small">${notes || "—"}</td></tr>`;
}

function render(plan: Plan, snapshot: Choice): void {
  latest = { plan, choice: snapshot };
  const tbody = document.querySelector<HTMLTableSectionElement>("#volunteerConversionTable tbody");
  if (tbody) {
    tbody.innerHTML = plan.opportunities.map(renderRow).join("");
  }

  const { positions, ministries, qualifications, skipped, converted, pending } = plan.totals;
  const target = plan.target.exists
    ? t("into the ministry {{name}}", { name: plan.target.name })
    : t("into a new ministry, {{name}}", { name: plan.target.name });
  const lines = [
    `${escapeHtml(t("Positions"))}: <strong>${positions}</strong> ${escapeHtml(target)}`,
    `${escapeHtml(t("New ministries of their own"))}: <strong>${ministries}</strong>`,
    `${escapeHtml(t("Volunteers qualified"))}: <strong>${qualifications}</strong>`,
    `${escapeHtml(t("Links that cannot convert"))}: <strong>${skipped}</strong>`,
    converted > 0 ? `${escapeHtml(t("Already converted"))}: <strong>${converted}</strong>` : "",
    pending > 0 ? `${escapeHtml(t("Volunteers to add to converted positions"))}: <strong>${pending}</strong>` : "",
  ].filter(Boolean);
  const summary = byId("volunteerConversionSummary");
  if (summary) {
    summary.innerHTML = `<ul class="mb-0">${lines.map((line) => `<li>${line}</li>`).join("")}</ul>`;
  }

  const todo = positions + ministries + pending;
  const run = byId<HTMLButtonElement>("volunteerConversionRun");
  if (run) {
    run.disabled = todo === 0;
  }
  const note = byId("volunteerConversionNote");
  if (note) {
    note.textContent =
      todo === 0
        ? t("Everything is converted.")
        : plan.version === "v1"
          ? t(
              "Volunteer Management switches to Both, so the new ministries are visible next to Volunteer Opportunities.",
            )
          : t("No email is sent, and Volunteer Opportunities stay as they are until they are retired.");
  }
}

function renderError(message: string): void {
  const summary = byId("volunteerConversionSummary");
  if (summary) {
    summary.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(message)}</div>`;
  }
  const run = byId<HTMLButtonElement>("volunteerConversionRun");
  if (run) {
    run.disabled = true;
  }
}

async function refresh(): Promise<void> {
  const seq = ++requestSeq;
  const snapshot = choice();
  let response: Response;
  try {
    response = await fetch(previewUrl(snapshot), { headers: { Accept: "application/json" } });
  } catch {
    if (seq === requestSeq) {
      renderError(t("The conversion could not be previewed."));
    }
    return;
  }
  const body = (await response.json().catch(() => null)) as Plan | null;
  if (seq !== requestSeq) {
    return;
  }
  if (!response.ok || body === null) {
    renderError(body?.message || t("The conversion could not be previewed."));
    return;
  }
  render(body, snapshot);
}

async function convert(snapshot: Choice, revision: string): Promise<void> {
  invalidate();
  let response: Response;
  try {
    response = await fetch(`${root()}/admin/api/volunteer-conversion`, {
      method: "POST",
      headers: { "content-type": "application/json", "X-CSRF-Token": config().csrfToken },
      body: JSON.stringify({ ...snapshot, revision }),
    });
  } catch {
    window.CRM?.notify?.(
      escapeHtml(t("The server could not be reached. Reload the page to see whether the conversion ran.")),
      {
        type: "error",
        delay: 8000,
      },
    );
    await refresh();
    return;
  }
  const body = (await response.json().catch(() => null)) as Plan | null;
  if (!response.ok || body === null) {
    window.CRM?.notify?.(escapeHtml(body?.message || t("The conversion failed. Nothing was changed.")), {
      type: "error",
      delay: 5000,
    });
    await refresh();
    return;
  }

  ownMinistry.clear();
  window.CRM?.notify?.(escapeHtml(t("Volunteer Opportunities converted")), { type: "success", delay: 3000 });
  const summary = byId("volunteerConversionSummary");
  await refresh();
  if (summary && body.totals) {
    summary.insertAdjacentHTML(
      "afterbegin",
      `<div class="alert alert-success" id="volunteerConversionDone">${escapeHtml(
        t(
          "Converted: {{positions}} positions and {{ministries}} ministries, {{qualifications}} volunteers qualified.",
          { ...body.totals, qualifications: body.totals.qualifications + body.totals.pending },
        ),
      )} <a href="${escapeAttribute(root())}/ministries/dashboard">${escapeHtml(t("Open Ministries"))}</a></div>`,
    );
  }
}

function confirmConvert(): void {
  const shown = latest;
  if (!shown || !sameChoice(shown.choice, choice())) {
    return;
  }
  if (!window.bootbox) {
    window.CRM?.notify?.(escapeHtml(t("The confirmation could not open. Reload the page and try again.")), {
      type: "error",
      delay: 5000,
    });
    return;
  }
  const { positions, ministries, pending } = shown.plan.totals;
  const message = escapeHtml(
    positions + ministries > 0
      ? t(
          "Convert {{total}} opportunities now? The V1 data is kept as it is until Volunteer Opportunities are retired.",
          {
            total: positions + ministries,
          },
        )
      : t("Add {{total}} volunteers to positions converted before?", { total: pending }),
  );
  window.bootbox.confirm({
    title: t("Convert Volunteer Opportunities"),
    message,
    buttons: { confirm: { label: t("Convert"), className: "btn-primary" }, cancel: { label: t("Cancel") } },
    callback: (yes: boolean) => {
      if (yes && latest === shown && sameChoice(shown.choice, choice())) {
        void convert(shown.choice, shown.plan.revision);
      }
    },
  });
}

function start(): void {
  if (!byId("volunteerConversionTable")) {
    return;
  }

  const name = byId<HTMLInputElement>("volunteerConversionMinistryName");
  const select = byId<HTMLSelectElement>("volunteerConversionMinistry");
  let typing = 0;
  for (const radio of document.querySelectorAll<HTMLInputElement>('input[name="target"]')) {
    radio.addEventListener("change", () => {
      const existing = radio.value === "existing" && radio.checked;
      if (name) {
        name.disabled = existing;
      }
      if (select) {
        select.disabled = !existing;
      }
      invalidate();
      void refresh();
    });
  }
  name?.addEventListener("input", () => {
    invalidate();
    window.clearTimeout(typing);
    typing = window.setTimeout(() => void refresh(), 300);
  });
  select?.addEventListener("change", () => {
    invalidate();
    void refresh();
  });

  document.querySelector("#volunteerConversionTable tbody")?.addEventListener("change", (event) => {
    const target = event.target as HTMLSelectElement;
    const id = Number(target.dataset.becomes ?? 0);
    if (!id) {
      return;
    }
    if (target.value === "ministry") {
      ownMinistry.add(id);
    } else {
      ownMinistry.delete(id);
    }
    invalidate();
    void refresh();
  });

  byId("volunteerConversionRun")?.addEventListener("click", confirmConvert);
  void refresh();
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(start);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
