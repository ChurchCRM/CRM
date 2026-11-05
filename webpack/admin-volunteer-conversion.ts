/**
 * Admin → Convert Volunteer Opportunities (#10606): show what the conversion would do as
 * the administrator chooses where the opportunities go, then run it.
 */

export {};

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
}

interface Plan {
  version: string;
  target: { id: number | null; name: string; exists: boolean };
  opportunities: PlannedOpportunity[];
  totals: { positions: number; ministries: number; qualifications: number; skipped: number; converted: number };
  message?: string;
}

const ownMinistry = new Set<number>();
let latest: Plan | null = null;
let requestSeq = 0;

const root = (): string => window.CRM?.root ?? "";
const config = (): ConversionConfig => window.CRM?.volunteerConversion as ConversionConfig;

function t(key: string, options?: Record<string, unknown>): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  return i18next.t(key, options);
}

function escapeHtml(value: string): string {
  const div = document.createElement("div");
  div.textContent = value;
  return div.innerHTML;
}

function byId<T extends HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

function choice(): Record<string, unknown> {
  const existing = byId<HTMLInputElement>("volunteerConversionTargetExisting")?.checked;
  const target = existing
    ? { ministryId: Number(byId<HTMLSelectElement>("volunteerConversionMinistry")?.value ?? 0) }
    : { ministryName: byId<HTMLInputElement>("volunteerConversionMinistryName")?.value ?? "" };
  return { ...target, ownMinistry: [...ownMinistry] };
}

function previewUrl(): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(choice())) {
    params.set(key, Array.isArray(value) ? value.join(",") : String(value));
  }
  return `${root()}/admin/api/volunteer-conversion/preview?${params.toString()}`;
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
    return `<tr data-opportunity="${item.id}"><td>${name}${inactive}</td><td colspan="3"><span class="badge bg-success-lt">${escapeHtml(t("Converted"))}</span></td></tr>`;
  }

  const becomes = `<select class="form-select form-select-sm w-auto" data-becomes="${item.id}" aria-label="${escapeHtml(t("What it becomes"))}">
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

function render(plan: Plan): void {
  latest = plan;
  const tbody = document.querySelector<HTMLTableSectionElement>("#volunteerConversionTable tbody");
  if (tbody) {
    tbody.innerHTML = plan.opportunities.map(renderRow).join("");
  }

  const { positions, ministries, qualifications, skipped, converted } = plan.totals;
  const target = plan.target.exists
    ? t("into the ministry {{name}}", { name: plan.target.name })
    : t("into a new ministry, {{name}}", { name: plan.target.name });
  const lines = [
    `${escapeHtml(t("Positions"))}: <strong>${positions}</strong> ${escapeHtml(target)}`,
    `${escapeHtml(t("New ministries of their own"))}: <strong>${ministries}</strong>`,
    `${escapeHtml(t("Volunteers qualified"))}: <strong>${qualifications}</strong>`,
    `${escapeHtml(t("Links that cannot convert"))}: <strong>${skipped}</strong>`,
    converted > 0 ? `${escapeHtml(t("Already converted"))}: <strong>${converted}</strong>` : "",
  ].filter(Boolean);
  const summary = byId("volunteerConversionSummary");
  if (summary) {
    summary.innerHTML = `<ul class="mb-0">${lines.map((line) => `<li>${line}</li>`).join("")}</ul>`;
  }

  const todo = positions + ministries;
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
  const response = await fetch(previewUrl(), { headers: { Accept: "application/json" } });
  const body = (await response.json().catch(() => null)) as Plan | null;
  if (seq !== requestSeq) {
    return;
  }
  if (!response.ok || body === null) {
    renderError(body?.message || t("The conversion could not be previewed."));
    return;
  }
  render(body);
}

async function convert(): Promise<void> {
  const run = byId<HTMLButtonElement>("volunteerConversionRun");
  if (run) {
    run.disabled = true;
  }
  const response = await fetch(`${root()}/admin/api/volunteer-conversion`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": config().csrfToken },
    body: JSON.stringify(choice()),
  });
  const body = (await response.json().catch(() => null)) as Plan | null;
  if (!response.ok || body === null) {
    window.CRM?.notify?.(body?.message || t("The conversion failed. Nothing was changed."), {
      type: "error",
      delay: 5000,
    });
    await refresh();
    return;
  }

  ownMinistry.clear();
  window.CRM?.notify?.(t("Volunteer Opportunities converted"), { type: "success", delay: 3000 });
  const summary = byId("volunteerConversionSummary");
  await refresh();
  if (summary && body.totals) {
    summary.insertAdjacentHTML(
      "afterbegin",
      `<div class="alert alert-success" id="volunteerConversionDone">${escapeHtml(
        t(
          "Converted: {{positions}} positions and {{ministries}} ministries, {{qualifications}} volunteers qualified.",
          body.totals,
        ),
      )} <a href="${root()}/ministries/dashboard">${escapeHtml(t("Open Ministries"))}</a></div>`,
    );
  }
}

function confirmConvert(): void {
  const plan = latest;
  if (!plan) {
    return;
  }
  const message = escapeHtml(
    t("Convert {{count}} opportunities now? The V1 data is kept as it is until Volunteer Opportunities are retired.", {
      count: plan.totals.positions + plan.totals.ministries,
    }),
  );
  if (!window.bootbox) {
    void convert();
    return;
  }
  window.bootbox.confirm({
    title: t("Convert Volunteer Opportunities"),
    message,
    buttons: { confirm: { label: t("Convert"), className: "btn-primary" }, cancel: { label: t("Cancel") } },
    callback: (yes: boolean) => {
      if (yes) {
        void convert();
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
      void refresh();
    });
  }
  name?.addEventListener("input", () => {
    window.clearTimeout(typing);
    typing = window.setTimeout(() => void refresh(), 300);
  });
  select?.addEventListener("change", () => void refresh());

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
