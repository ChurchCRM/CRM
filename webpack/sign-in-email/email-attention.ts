/**
 * "Email needs attention" (member sign-in design §4, §9, §11.2), shared by
 * Admin → Member Portal → Members and the staff page /people/sign-in-email:
 * the list's columns, Confirm Email Address on one row, Confirm selected on
 * many, and the Fix shared emails preview. Every change posts to
 * /api/persons/sign-in-email/*, which checks each row again.
 */
import {
  escapeAttribute,
  escapeHtml,
  familyLink,
  getFrom,
  muted,
  personLink,
  root,
  type ServerColumn,
  t,
} from "../admin-member-portal/server-table";

export interface AttentionRow {
  personId: number;
  name: string;
  familyId: number | null;
  familyName: string;
  email: string;
  problem?: string;
  problemLabel?: string;
  sharedWith?: Array<{ personId: number; name: string }>;
  unconfirmedEmail: string | null;
  unconfirmedSource: string | null;
  unconfirmedSourceLabel: string | null;
  canConfirmEmail?: boolean;
  confirmEmailRefusal?: string | null;
}

export interface AttentionCounts {
  emailProblems?: Record<string, number>;
  sharedAddresses?: number;
  unconfirmedKinds?: Record<string, number>;
}

interface PlanPerson {
  personId: number;
  name: string;
  familyId: number | null;
  familyName: string | null;
  role: string;
  signedIn: boolean;
}

interface PlanRow {
  address: string;
  email: string;
  status: "fix" | "by_hand";
  reasonLabel: string | null;
  familyId: number | null;
  familyName: string | null;
  keeper: PlanPerson | null;
  losers: PlanPerson[];
  people: PlanPerson[];
  canApply: boolean;
  refusal: string | null;
}

interface RowOutcome {
  email?: string;
  name?: string | null;
  familyName?: string | null;
  reason?: string;
  losers?: PlanPerson[];
}

interface FixResult {
  changed: RowOutcome[];
  skipped: RowOutcome[];
  refused: RowOutcome[];
  peopleChanged: number;
}

interface ConfirmResult {
  confirmed: RowOutcome[];
  skipped: RowOutcome[];
  refused: RowOutcome[];
}

export interface AttentionToolsOptions {
  table: HTMLTableElement;
  csrfToken: () => string;
  /** Reload the list and the counts after a change. */
  onChanged: () => void;
  /** Reload the list from its first page after the kind filter changed. */
  onFilter: () => void;
}

export interface AttentionTools {
  /** The unconfirmed kind the list is filtered by ("" for both). */
  kind: () => string;
  setProblem: (problem: string) => void;
  applyCounts: (counts: AttentionCounts) => void;
  afterDraw: () => void;
  openSharedEmailFix: () => void;
}

const API = "/api/persons/sign-in-email";
const UNCONFIRMED = "email_unconfirmed";
const MODAL_ID = "sharedEmailFixModal";

async function send<T>(path: string, body: unknown, csrfToken: string): Promise<T> {
  const response = await fetch(`${root()}${path}`, {
    method: "POST",
    credentials: "same-origin",
    headers: { "content-type": "application/json", Accept: "application/json", "X-CSRF-Token": csrfToken },
    body: JSON.stringify(body),
  });
  const reply = (await response.json().catch(() => null)) as (T & { message?: string }) | null;
  if (!response.ok || reply === null) {
    throw new Error(reply?.message || t("The change could not be saved."));
  }
  return reply;
}

export function attentionCounts(): Promise<AttentionCounts> {
  return getFrom<AttentionCounts>(`${API}/attention/counts`);
}

function problemCell(row: AttentionRow): string {
  const label = `<span class="badge bg-warning-lt text-wrap text-start">${escapeHtml(row.problemLabel ?? "")}</span>`;
  if (row.problem === "shared_email" && row.sharedWith?.length) {
    const others = row.sharedWith.map((other) => personLink(other.personId, other.name)).join(", ");
    return `${label}<div class="small mt-1">${escapeHtml(t("Also used by"))}: ${others}</div>`;
  }
  if (row.problem === UNCONFIRMED) {
    return `${label}<div class="small text-secondary mt-1">${escapeHtml(row.unconfirmedSourceLabel ?? "")}</div>`;
  }
  return label;
}

/**
 * What staff can do about the row. An unconfirmed address is confirmed here,
 * after checking who the person is, when §9 lets the reader; the rest are
 * fixed on the person's record or with Fix shared emails.
 */
function attentionFixCell(row: AttentionRow): string {
  const attributes = `data-cy="attention-fix" data-person-id="${row.personId}" data-problem="${escapeAttribute(row.problem ?? "")}"`;
  if (row.problem === UNCONFIRMED) {
    if (row.canConfirmEmail) {
      return `<div ${attributes}><button type="button" class="btn btn-sm btn-outline-primary member-list-confirm-email"
        data-person-id="${row.personId}" data-email="${escapeAttribute(row.unconfirmedEmail ?? "")}">
        <i class="fa-solid fa-check me-1"></i>${escapeHtml(t("Confirm Email Address"))}</button></div>`;
    }
    return `<div ${attributes}>${muted(row.confirmEmailRefusal ?? "")}</div>`;
  }
  const label = row.problem === "shared_email" ? t("Give each person their own email") : t("Add an email");
  const href = `${root()}/PersonEditor.php?PersonID=${row.personId}`;
  return `<div ${attributes}><a class="btn btn-sm btn-outline-primary" href="${escapeAttribute(href)}">${escapeHtml(label)}</a></div>`;
}

function selectCell(row: AttentionRow): string {
  if (row.problem !== UNCONFIRMED) {
    return "";
  }
  const label = escapeAttribute(`${t("Select")} ${row.name}`);
  if (!row.canConfirmEmail) {
    const reason = escapeAttribute(row.confirmEmailRefusal ?? "");
    return `<span class="d-inline-block" title="${reason}" data-cy="attention-select-locked">
      <input type="checkbox" class="form-check-input m-0" disabled aria-label="${label}" title="${reason}"></span>`;
  }
  return `<input type="checkbox" class="form-check-input m-0 attention-select" data-cy="attention-select"
    data-person-id="${row.personId}" data-email="${escapeAttribute(row.unconfirmedEmail ?? "")}" aria-label="${label}">`;
}

export function attentionColumns(menu: (row: AttentionRow) => string): ServerColumn<AttentionRow>[] {
  return [
    { title: "", render: selectCell, className: "w-1", priority: 1 },
    { title: t("Name"), render: (row) => personLink(row.personId, row.name), priority: 1 },
    { title: t("Problem"), render: problemCell, priority: 3 },
    {
      title: t("Email"),
      render: (row) => (row.email ? `<span class="text-break">${escapeHtml(row.email)}</span>` : muted(t("None"))),
      priority: 5,
    },
    { title: t("What to do"), render: attentionFixCell, priority: 4 },
    { title: "", render: menu, className: "text-end w-1 no-export", priority: 2 },
  ];
}

function outcomeList(title: string, rows: RowOutcome[], tone: string): string {
  if (rows.length === 0) {
    return "";
  }
  const items = rows
    .map((row) => {
      const who = row.email ?? row.name ?? "";
      const what = row.reason ? ` <span class="text-secondary">${escapeHtml(row.reason)}</span>` : "";
      return `<li><span class="text-break">${escapeHtml(who)}</span>${row.name && row.email ? ` · ${escapeHtml(row.name)}` : ""}${what}</li>`;
    })
    .join("");
  return `<div class="mt-2"><div class="fw-medium text-${tone}">${escapeHtml(title)} (${rows.length})</div><ul class="mb-0 ps-3">${items}</ul></div>`;
}

export function wireEmailAttentionTools(options: AttentionToolsOptions): AttentionTools {
  const { table } = options;
  let currentKind = "";
  let currentProblem = "";
  let unconfirmedCount = 0;
  let plan: PlanRow[] = [];
  const picked = new Set<string>();

  const bulkBar = document.getElementById("bulkConfirmBar");
  const kindBar = document.getElementById("unconfirmedKindBar");
  const selectAll = document.getElementById("bulkConfirmSelectAll") as HTMLButtonElement | null;
  const confirmButton = document.getElementById("bulkConfirmButton") as HTMLButtonElement | null;
  const resultBox = document.getElementById("emailAttentionResult");
  resultBox?.addEventListener("closed.bs.alert", () => resultBox.classList.add("d-none"));

  const boxes = (): HTMLInputElement[] => [...table.querySelectorAll<HTMLInputElement>("input.attention-select")];
  const chosen = (): HTMLInputElement[] => boxes().filter((box) => box.checked);

  function updateSelection(): void {
    const all = boxes();
    const ticks = chosen().length;
    const count = document.getElementById("bulkConfirmCount");
    if (count) {
      count.textContent = String(ticks);
    }
    if (confirmButton) {
      confirmButton.disabled = ticks === 0;
    }
    if (selectAll) {
      selectAll.disabled = all.length === 0;
      selectAll.textContent = all.length > 0 && ticks === all.length ? t("Clear selection") : t("Select all");
    }
  }

  function showBars(): void {
    const unconfirmedShown = currentProblem === "" || currentProblem === UNCONFIRMED;
    bulkBar?.classList.toggle("d-none", !unconfirmedShown || unconfirmedCount === 0);
    kindBar?.classList.toggle("d-none", currentProblem !== UNCONFIRMED || unconfirmedCount === 0);
  }

  function showResult(html: string, tone: "success" | "warning"): void {
    if (!resultBox) {
      return;
    }
    resultBox.innerHTML = `<div class="alert alert-${tone} alert-dismissible mb-0" role="status"><div>${html}</div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="${escapeAttribute(t("Close"))}"></button></div>`;
    resultBox.classList.remove("d-none");
  }

  async function confirmOne(button: HTMLButtonElement): Promise<void> {
    button.disabled = true;
    try {
      await send(
        `/api/person/${button.dataset.personId}/sign-in/confirm-email`,
        { email: button.dataset.email ?? "" },
        options.csrfToken(),
      );
    } catch (error) {
      button.disabled = false;
      window.CRM?.notify?.((error as Error).message || t("The email could not be confirmed."), { type: "danger" });
      return;
    }
    window.CRM?.notify?.(t("The email is confirmed. It can now be used to sign in."), { type: "success" });
    options.onChanged();
  }

  async function confirmChosen(rows: Array<{ personId: number; email: string }>): Promise<void> {
    if (confirmButton) {
      confirmButton.disabled = true;
    }
    let result: ConfirmResult;
    try {
      result = await send<ConfirmResult>(`${API}/confirm`, { rows }, options.csrfToken());
    } catch (error) {
      updateSelection();
      window.CRM?.notify?.((error as Error).message, { type: "danger" });
      return;
    }
    const summary = `<div class="fw-medium">${escapeHtml(t("Emails confirmed: {{total}}", { total: result.confirmed.length }))}</div>`;
    showResult(
      summary +
        outcomeList(t("Skipped"), result.skipped, "secondary") +
        outcomeList(t("Refused"), result.refused, "danger"),
      result.skipped.length + result.refused.length === 0 ? "success" : "warning",
    );
    options.onChanged();
  }

  // On a narrow screen the first cell also opens the row's hidden columns; a tick should only tick.
  table.addEventListener(
    "click",
    (event) => {
      if ((event.target as HTMLElement).classList.contains("attention-select")) {
        event.stopPropagation();
      }
    },
    true,
  );

  table.addEventListener("change", (event) => {
    if ((event.target as HTMLElement).classList.contains("attention-select")) {
      updateSelection();
    }
  });

  table.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>(".member-list-confirm-email");
    if (!button) {
      return;
    }
    window.bootbox?.confirm({
      title: t("Confirm Email Address"),
      message: escapeHtml(
        t(
          "Confirm this email only after checking, in person or by phone, that it is this person's own address. Once confirmed, it can be used to sign in to the Member Portal.",
        ),
      ),
      buttons: { confirm: { label: t("Confirm Email Address"), className: "btn-primary" } },
      callback: (confirmed: boolean) => {
        if (confirmed) {
          void confirmOne(button);
        }
      },
    });
  });

  selectAll?.addEventListener("click", () => {
    const all = boxes();
    const check = chosen().length !== all.length;
    for (const box of all) {
      box.checked = check;
    }
    updateSelection();
  });

  confirmButton?.addEventListener("click", () => {
    const rows = chosen().map((box) => ({ personId: Number(box.dataset.personId), email: box.dataset.email ?? "" }));
    if (rows.length === 0) {
      return;
    }
    window.bootbox?.confirm({
      title: t("Confirm selected"),
      message:
        `<p>${escapeHtml(t("Email addresses to confirm: {{total}}", { total: rows.length }))}</p>` +
        `<p class="mb-0">${escapeHtml(t("Only confirm addresses you know belong to that person. Anyone who reads an address can sign in as its person."))}</p>`,
      buttons: { confirm: { label: t("Confirm selected"), className: "btn-primary" } },
      callback: (confirmed: boolean) => {
        if (confirmed) {
          void confirmChosen(rows);
        }
      },
    });
  });

  document.getElementById("unconfirmedKinds")?.addEventListener("click", (event) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>("[data-kind]");
    if (!button) {
      return;
    }
    currentKind = button.dataset.kind ?? "";
    for (const other of document.querySelectorAll<HTMLButtonElement>("#unconfirmedKinds [data-kind]")) {
      other.classList.toggle("active", other === button);
    }
    options.onFilter();
  });

  // ---- Fix shared emails -------------------------------------------------

  function modal(): HTMLElement {
    let element = document.getElementById(MODAL_ID);
    if (element) {
      return element;
    }
    element = document.createElement("div");
    element.className = "modal modal-blur fade";
    element.id = MODAL_ID;
    element.tabIndex = -1;
    element.setAttribute("aria-labelledby", `${MODAL_ID}Title`);
    element.setAttribute("aria-hidden", "true");
    element.innerHTML = `<div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="${MODAL_ID}Title">${escapeHtml(t("Fix shared emails"))}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${escapeAttribute(t("Close"))}"></button>
        </div>
        <div class="modal-body" data-cy="shared-fix-body"></div>
        <div class="modal-footer" data-cy="shared-fix-footer"></div>
      </div>
    </div>`;
    document.body.appendChild(element);
    element.addEventListener("click", onModalClick);
    element.addEventListener("change", onModalChange);
    element.addEventListener("hidden.bs.modal", () => {
      if (element?.dataset.changed === "1") {
        element.dataset.changed = "";
        options.onChanged();
      }
    });
    return element;
  }

  const body = (): HTMLElement => modal().querySelector<HTMLElement>(".modal-body") as HTMLElement;
  const footer = (): HTMLElement => modal().querySelector<HTMLElement>(".modal-footer") as HTMLElement;
  const ticked = (): PlanRow[] => plan.filter((row) => row.canApply && picked.has(row.address));

  const personLine = (person: PlanPerson): string =>
    `${personLink(person.personId, person.name)} <span class="text-secondary small">${escapeHtml(person.role)}</span>` +
    (person.signedIn
      ? ` <span class="badge bg-secondary-lt" title="${escapeAttribute(t("Has signed in: their sessions and devices end"))}">${escapeHtml(t("Has signed in"))}</span>`
      : "");

  function previewTable(rows: PlanRow[]): string {
    const head = `<thead><tr>
        <th class="w-1"><span class="visually-hidden">${escapeHtml(t("Apply"))}</span></th>
        <th>${escapeHtml(t("Address"))}</th><th>${escapeHtml(t("Family"))}</th>
        <th>${escapeHtml(t("Keeps it"))}</th><th>${escapeHtml(t("Loses it"))}</th></tr></thead>`;
    const body = rows
      .map((row) => {
        const box = row.canApply
          ? `<input type="checkbox" class="form-check-input m-0 shared-fix-row" value="${escapeAttribute(row.address)}" ${picked.has(row.address) ? "checked" : ""} aria-label="${escapeAttribute(`${t("Apply")} ${row.email}`)}">`
          : `<span class="d-inline-block" title="${escapeAttribute(row.refusal ?? "")}"><input type="checkbox" class="form-check-input m-0" disabled aria-label="${escapeAttribute(`${t("Apply")} ${row.email}`)}"></span>`;
        const refusal = row.canApply
          ? ""
          : `<div class="small text-secondary mt-1" data-cy="shared-fix-refusal">${escapeHtml(row.refusal ?? "")}</div>`;
        return `<tr data-address="${escapeAttribute(row.address)}" data-cy="shared-fix-row">
          <td data-label="${escapeAttribute(t("Apply"))}">${box}</td>
          <td data-label="${escapeAttribute(t("Address"))}"><span class="text-break">${escapeHtml(row.email)}</span>${refusal}</td>
          <td data-label="${escapeAttribute(t("Family"))}">${familyLink(row.familyId, row.familyName)}</td>
          <td data-label="${escapeAttribute(t("Keeps it"))}" data-cy="shared-fix-keeper">${row.keeper ? personLine(row.keeper) : ""}</td>
          <td data-label="${escapeAttribute(t("Loses it"))}" data-cy="shared-fix-losers">${row.losers.map(personLine).join("<br>")}</td>
        </tr>`;
      })
      .join("");
    return `<table class="table table-vcenter table-stack-md-down mb-0" data-cy="shared-fix-table">${head}<tbody>${body}</tbody></table>`;
  }

  function byHandTable(rows: PlanRow[]): string {
    const head = `<thead><tr><th>${escapeHtml(t("Address"))}</th><th>${escapeHtml(t("Shared by"))}</th><th>${escapeHtml(t("Why"))}</th></tr></thead>`;
    const body = rows
      .map(
        (row) => `<tr data-address="${escapeAttribute(row.address)}" data-cy="shared-by-hand-row">
          <td data-label="${escapeAttribute(t("Address"))}"><span class="text-break">${escapeHtml(row.email)}</span></td>
          <td data-label="${escapeAttribute(t("Shared by"))}">${row.people
            .map(
              (person) =>
                `${personLink(person.personId, person.name)}${person.familyName ? ` <span class="text-secondary small">${escapeHtml(person.familyName)}</span>` : ""}`,
            )
            .join("<br>")}</td>
          <td data-label="${escapeAttribute(t("Why"))}" class="text-secondary">${escapeHtml(row.reasonLabel ?? "")}</td>
        </tr>`,
      )
      .join("");
    return `<table class="table table-vcenter table-stack-md-down mb-0" data-cy="shared-by-hand-table">${head}<tbody>${body}</tbody></table>`;
  }

  function renderPreview(): void {
    const fixable = plan.filter((row) => row.status === "fix");
    const byHand = plan.filter((row) => row.status === "by_hand");
    let html = `<p class="text-secondary">${escapeHtml(
      t(
        "Nobody can sign in with a personal email that two people who may sign in share. For an address shared within one family, the head of household keeps it, else the spouse (the oldest first). Everyone else sharing it loses it. Untick any you want to fix another way.",
      ),
    )}</p>`;
    if (plan.length === 0) {
      html += `<div class="empty"><p class="empty-title">${escapeHtml(t("No shared emails stop anyone from signing in."))}</p></div>`;
    }
    if (fixable.length > 0) {
      html += `<h4 class="mb-2">${escapeHtml(t("To fix"))} <span class="badge bg-secondary-lt">${fixable.length}</span></h4>${previewTable(fixable)}`;
    }
    if (byHand.length > 0) {
      html += `<h4 class="mt-4 mb-1">${escapeHtml(t("Fix by hand"))} <span class="badge bg-secondary-lt">${byHand.length}</span></h4>
        <p class="text-secondary small">${escapeHtml(t("This tool never changes these. Give each person their own email on their record."))}</p>${byHandTable(byHand)}`;
    }
    body().innerHTML = html;
    renderPreviewFooter();
  }

  function renderPreviewFooter(): void {
    footer().innerHTML = `<button type="button" class="btn btn-link link-secondary me-auto" data-bs-dismiss="modal">${escapeHtml(t("Cancel"))}</button>
      <button type="button" class="btn btn-primary" data-action="review" data-cy="shared-fix-apply">
        ${escapeHtml(t("Apply"))} <span class="badge bg-white text-primary ms-1" data-cy="shared-fix-count"></span></button>`;
    updateApplyCount();
  }

  function updateApplyCount(): void {
    const count = ticked().length;
    const button = footer().querySelector<HTMLButtonElement>("[data-action=review]");
    if (button) {
      button.disabled = count === 0;
    }
    const badge = footer().querySelector<HTMLElement>("[data-cy=shared-fix-count]");
    if (badge) {
      badge.textContent = String(count);
    }
  }

  function renderConfirm(): void {
    const rows = ticked();
    const people = rows.reduce((sum, row) => sum + row.losers.length, 0);
    const signedIn = rows.reduce((sum, row) => sum + row.losers.filter((person) => person.signedIn).length, 0);
    body().innerHTML = `<div class="alert alert-warning mb-0" data-cy="shared-fix-confirm"><div>
      <h4 class="alert-title">${escapeHtml(t("People who lose an address: {{total}}", { total: people }))}</h4>
      <p>${escapeHtml(t("Shared addresses to fix: {{total}}", { total: rows.length }))}</p>
      <p>${escapeHtml(t("A person who loses it then has no email of their own: mail sent to them alone, such as a group email, is skipped, and the email composer says so."))}</p>
      ${signedIn > 0 ? `<p class="mb-0">${escapeHtml(t("Of them, people who have signed in: {{total}}. They are signed out everywhere, and the address gets a notice.", { total: signedIn }))}</p>` : ""}
    </div></div>`;
    footer().innerHTML = `<button type="button" class="btn btn-link link-secondary me-auto" data-action="back">${escapeHtml(t("Back"))}</button>
      <button type="button" class="btn btn-danger" data-action="apply" data-cy="shared-fix-confirm-apply">${escapeHtml(t("Remove Emails"))}
        <span class="badge bg-white text-danger ms-1">${people}</span></button>`;
  }

  async function apply(): Promise<void> {
    const rows = ticked().map((row) => ({
      email: row.address,
      keeperId: row.keeper?.personId ?? 0,
      loserIds: row.losers.map((person) => person.personId),
    }));
    footer()
      .querySelectorAll<HTMLButtonElement>("button")
      .forEach((button) => {
        button.disabled = true;
      });
    let result: FixResult;
    try {
      result = await send<FixResult>(`${API}/shared/fix`, { rows }, options.csrfToken());
    } catch (error) {
      window.CRM?.notify?.((error as Error).message, { type: "danger" });
      renderConfirm();
      return;
    }
    modal().dataset.changed = "1";
    const changed = result.changed
      .map(
        (row) =>
          `<li><span class="text-break">${escapeHtml(row.email ?? "")}</span> <span class="text-secondary">${escapeHtml(
            t("Removed from: {{names}}", {
              names: (row.losers ?? []).map((person) => person.name).join(", "),
              interpolation: { escapeValue: false },
            }),
          )}</span></li>`,
      )
      .join("");
    body().innerHTML = `<div data-cy="shared-fix-result">
      <div class="alert alert-${result.skipped.length + result.refused.length === 0 ? "success" : "warning"}"><div>
        <h4 class="alert-title">${escapeHtml(t("Shared addresses fixed: {{total}}", { total: result.changed.length }))}</h4>
        <div>${escapeHtml(t("People who lost an address: {{total}}", { total: result.peopleChanged }))}</div>
      </div></div>
      ${changed ? `<div class="fw-medium">${escapeHtml(t("Changed"))} (${result.changed.length})</div><ul class="ps-3">${changed}</ul>` : ""}
      ${outcomeList(t("Skipped"), result.skipped, "secondary")}
      ${outcomeList(t("Refused"), result.refused, "danger")}
    </div>`;
    footer().innerHTML = `<button type="button" class="btn btn-primary" data-bs-dismiss="modal">${escapeHtml(t("Close"))}</button>`;
  }

  function onModalClick(event: Event): void {
    const action = (event.target as HTMLElement).closest<HTMLButtonElement>("[data-action]")?.dataset.action;
    if (action === "review") {
      renderConfirm();
    } else if (action === "back") {
      renderPreview();
    } else if (action === "apply") {
      void apply();
    }
  }

  function onModalChange(event: Event): void {
    const box = event.target as HTMLInputElement;
    if (box.classList.contains("shared-fix-row")) {
      if (box.checked) {
        picked.add(box.value);
      } else {
        picked.delete(box.value);
      }
      updateApplyCount();
    }
  }

  async function openSharedEmailFix(): Promise<void> {
    const element = modal();
    body().innerHTML = `<div class="text-secondary"><span class="spinner-border spinner-border-sm me-2" role="status"></span>${escapeHtml(t("Loading..."))}</div>`;
    footer().innerHTML = "";
    window.bootstrap.Modal.getOrCreateInstance(element).show();
    try {
      plan = (await getFrom<{ rows: PlanRow[] }>(`${API}/shared`)).rows;
      picked.clear();
      for (const row of plan) {
        if (row.canApply) {
          picked.add(row.address);
        }
      }
    } catch (error) {
      body().innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml((error as Error).message)}</div>`;
      return;
    }
    renderPreview();
  }

  document.getElementById("sharedEmailFixButton")?.addEventListener("click", () => {
    void openSharedEmailFix();
  });

  return {
    kind: () => (currentProblem === UNCONFIRMED ? currentKind : ""),
    setProblem: (problem: string) => {
      currentProblem = problem;
      showBars();
    },
    applyCounts: (counts: AttentionCounts) => {
      for (const badge of document.querySelectorAll<HTMLElement>("[data-problem-count]")) {
        badge.textContent = String(counts.emailProblems?.[badge.dataset.problemCount ?? ""] ?? 0);
      }
      for (const badge of document.querySelectorAll<HTMLElement>("[data-kind-count]")) {
        badge.textContent = String(counts.unconfirmedKinds?.[badge.dataset.kindCount ?? ""] ?? 0);
      }
      const shared = counts.sharedAddresses ?? 0;
      const fixCount = document.getElementById("sharedEmailFixCount");
      if (fixCount) {
        fixCount.textContent = String(shared);
      }
      document.getElementById("sharedEmailFixButton")?.classList.toggle("d-none", shared === 0);
      for (const count of document.querySelectorAll<HTMLElement>("[data-shared-address-count]")) {
        count.textContent = String(shared);
      }
      document.getElementById("memberSignInSharedEmails")?.classList.toggle("d-none", shared === 0);
      unconfirmedCount = counts.emailProblems?.[UNCONFIRMED] ?? 0;
      showBars();
    },
    afterDraw: updateSelection,
    openSharedEmailFix: () => {
      void openSharedEmailFix();
    },
  };
}
