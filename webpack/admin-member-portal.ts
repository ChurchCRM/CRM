/**
 * Admin → Member Portal (#9864) — the page's behaviour.
 *
 * The tabs are rendered server-side; this bundle adds what needs the browser:
 *
 *   1. Settings Panels for the portal ConfigItems, each in the tab it
 *      belongs to, saving through POST /admin/api/system/config/{name}:
 *      Developer mode (Themes), the birthday and volunteering switches
 *      (Permissions) and the directory switch (Directory);
 *   2. the Themes tab's row actions: Check re-runs the validator and Activate
 *      goes through POST /admin/api/member-portal/theme so the validator can
 *      refuse. Either one shows what it found in the row under that theme;
 *   3. a refresh of the statistics numbers when that tab is opened;
 *   4. the Calendars tab's switches, each saved as it changes through
 *      POST /admin/api/member-portal/calendars. Members see the Calendar
 *      page while at least one calendar is switched on;
 *   5. the Sign-in tab's controls, each saved as it changes by posting that one
 *      setting to POST /admin/api/member-portal/sign-in. A refused value is put
 *      back to the saved one, so the page only ever shows what the server kept.
 *
 * The Members, Permissions and Audit tabs (member sign-in design §11.2, §8.6)
 * live in webpack/admin-member-portal/ and read their rows a page at a time.
 *
 * Every user-visible string goes through i18next — the *global* instance the
 * locale loader initialises, never a bundled copy, which would be empty — and
 * is resolved inside the handlers, because i18next is only populated once the
 * locale loader has run.
 */
import { wireAuditTab } from "./admin-member-portal/audit-tab";
import { refreshMemberLists, wireMembersTab } from "./admin-member-portal/members-tab";
import { loadPermissionsCatalog, wirePermissionsTab } from "./admin-member-portal/permissions-tab";

interface ThemeFinding {
  file: string;
  line: number;
  message: string;
  level: "error" | "warning";
}

interface ThemeResponse {
  name: string;
  activated?: boolean;
  status: "ok" | "warning" | "error";
  message?: string;
  findings: ThemeFinding[];
}

interface PortalStats {
  maySignIn: number;
  activeNow: number;
  signedIn24Hours: number;
  signedIn7Days: number;
  signedIn30Days: number;
  neverSignedIn: number;
}

const CALENDARS_TABLE_ID = "portalCalendarsTable";

const SIGN_IN_FORM_ID = "memberSignInForm";
const SIGN_IN_SWITCHES = ["bMemberSignIn", "bPortalPasskeys", "bAllowLoginAsPerson", "bLoginAsPersonCanWrite"];
const SIGN_IN_NUMBERS = [
  "iMemberSignInMinAge",
  "iSignInLinkMinutes",
  "iMemberSignInRequestsPerIP",
  "iPortalIdleMinutes",
  "iRememberDeviceDays",
];
const SIGN_IN_CHECKBOX_LISTS = ["sMemberSignInFamilyRoles", "sMemberSignInClassifications"];
const SIGN_IN_OFFICE_LIST = "sMemberSignInNotificationRecipientIDs";

interface SignInSaveResponse {
  success?: boolean;
  message?: string;
  values?: Record<string, string>;
  maySignInCount?: number;
  sharedAddresses?: number;
}

interface CalendarChoice {
  type: string;
  id: number;
  visible: boolean;
}

const root = (): string => window.CRM?.root ?? "";

const csrfToken = (): string => {
  const config = window.CRM?.memberPortalAdmin as { csrfToken?: string } | undefined;
  return config?.csrfToken ?? "";
};

/**
 * Translate through the global i18next the locale loader set up. A bundled
 * `import i18next` would be a second, never-initialised instance whose `t()`
 * returns an empty string, so every label on the page would render blank.
 */
function t(key: string, options?: Record<string, unknown>): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key, options);
  return translated === undefined || translated === "" ? key : String(translated);
}

function escapeHtml(value: string): string {
  const div = document.createElement("div");
  div.textContent = value;
  return div.innerHTML;
}

/** Class, icon and label for a validation summary. */
function statusPresentation(status: string): { cssClass: string; icon: string; label: string } {
  if (status === "error") {
    return { cssClass: "bg-danger text-white", icon: "fa-circle-xmark", label: t("Errors") };
  }
  if (status === "warning") {
    return { cssClass: "bg-warning text-dark", icon: "fa-triangle-exclamation", label: t("Warnings") };
  }
  return { cssClass: "bg-success text-white", icon: "fa-circle-check", label: t("Valid") };
}

function themeRow(name: string): HTMLTableRowElement | null {
  return document.querySelector<HTMLTableRowElement>(`#portalThemesTable tr[data-theme="${CSS.escape(name)}"]`);
}

/**
 * The findings list for one theme. An empty list is a statement in its own
 * right — the designer asked a question and deserves an answer.
 */
function findingsHtml(response: ThemeResponse): string {
  if (response.findings.length === 0) {
    return `<div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-2"></i>${escapeHtml(
      t("This theme has no problems."),
    )}</div>`;
  }

  const rows = response.findings
    .map((finding) => {
      const badgeClass = finding.level === "error" ? "bg-danger text-white" : "bg-warning text-dark";
      const badgeLabel = finding.level === "error" ? t("Error") : t("Warning");
      // A file:line pair is the same in every language — no translation needed.
      const where = finding.file
        ? `<code>${escapeHtml(finding.file)}${finding.line > 0 ? `:${finding.line}` : ""}</code> `
        : "";
      return `<li class="py-1"><span class="badge ${badgeClass} me-2">${escapeHtml(badgeLabel)}</span>${where}<span>${escapeHtml(finding.message)}</span></li>`;
    })
    .join("");

  const heading = response.activated === false ? t("This theme cannot be activated") : t("What the check found");
  return `<div class="fw-medium mb-1">${escapeHtml(heading)}</div><ul class="list-unstyled mb-0">${rows}</ul>`;
}

/** Repaint one theme's row with a fresh result and open the findings under it. */
function paintThemeResult(name: string, response: ThemeResponse): void {
  const row = themeRow(name);
  const findingsRow = document.getElementById(`portalFindings-${name}`);
  if (!row || !findingsRow) {
    return;
  }

  const badge = row.querySelector<HTMLElement>(".portal-theme-status");
  if (badge) {
    const presentation = statusPresentation(response.status);
    badge.className = `badge ${presentation.cssClass} portal-theme-status`;
    badge.innerHTML = `<i class="fa-solid ${presentation.icon} me-1"></i>${escapeHtml(presentation.label)}`;
  }
  row.querySelector(".portal-theme-findings-toggle")?.classList.toggle("d-none", response.findings.length === 0);
  const count = row.querySelector(".portal-theme-findings-count");
  if (count) {
    count.textContent = String(response.findings.length);
  }

  const cell = findingsRow.querySelector(".portal-theme-findings");
  if (cell) {
    cell.innerHTML = findingsHtml(response);
  }
  window.bootstrap.Collapse?.getOrCreateInstance(findingsRow, { toggle: false }).show();
}

/** Run the validator against one theme and show what it found. */
async function checkTheme(name: string): Promise<void> {
  if (!name) {
    return;
  }
  const response = await fetch(`${root()}/admin/api/member-portal/theme/${encodeURIComponent(name)}/validation`);
  if (!response.ok) {
    window.CRM?.notify?.(t("That theme could not be checked."), { type: "danger" });
    return;
  }
  paintThemeResult(name, await response.json());
}

/**
 * Activate a theme. A 409 means the validator refused it: the findings are
 * listed and nothing was written, so the portal keeps the theme it had.
 */
async function activateTheme(name: string): Promise<void> {
  if (!name) {
    return;
  }
  const response = await fetch(`${root()}/admin/api/member-portal/theme`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ name }),
  });

  let body: ThemeResponse | null = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  if (response.status === 409 && body) {
    paintThemeResult(name, { ...body, status: "error", activated: false });
    window.CRM?.notify?.(t("That theme has errors, so it was not activated."), { type: "danger" });
    return;
  }

  if (!response.ok || !body) {
    window.CRM?.notify?.(t("That theme could not be activated."), { type: "danger" });
    return;
  }

  paintThemeResult(name, body);
  window.CRM?.notify?.(t("The Member Portal theme was changed."), { type: "success" });
  window.setTimeout(() => window.location.reload(), 1500);
}

/** Re-read the statistics so an admin who leaves the page open sees fresh numbers. */
async function refreshStatistics(): Promise<void> {
  let stats: PortalStats;
  try {
    const response = await fetch(`${root()}/admin/api/member-portal/stats`);
    if (!response.ok) {
      return;
    }
    stats = await response.json();
  } catch {
    return;
  }
  for (const [key, value] of Object.entries(stats)) {
    const cell = document.getElementById(`portalStat-${key}`);
    if (cell && typeof value === "number") {
      cell.textContent = String(value);
    }
  }
}

function wireThemeControls(): void {
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-activate")) {
    button.addEventListener("click", () => activateTheme(button.dataset.theme ?? ""));
  }
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-check")) {
    button.addEventListener("click", () => checkTheme(button.dataset.theme ?? ""));
  }
}

/**
 * The portal ConfigItems carry no System Settings category, so each lives in
 * the tab it affects, and a link to that page would be a dead end.
 */
function wireSettingsPanels(): void {
  const SettingsPanel = window.CRM?.SettingsPanel;
  if (!SettingsPanel) {
    return;
  }
  const panel = (
    container: string,
    options: { title: string; icon: string; settings: CRMSettingsPanelSetting[]; onSave?: () => void },
  ): void => {
    if (document.querySelector(container)) {
      new SettingsPanel().init({ container, showAllSettingsLink: false, autoSave: true, ...options });
    }
  };

  panel("#portalDeveloperModePanel", {
    title: t("Theme development"),
    icon: "fa-solid fa-code",
    settings: [
      {
        name: "bPortalDeveloperMode",
        type: "boolean",
        label: t("Developer mode"),
        tooltip: t(
          "Turns off the portal template cache and prints the name of each template in an HTML comment. For theme designers; leave this off in normal use.",
        ),
      },
    ],
  });

  panel("#portalDirectorySectionPanel", {
    title: t("Directory section"),
    icon: "fa-solid fa-address-book",
    settings: [
      {
        name: "bPortalShowDirectory",
        type: "boolean",
        label: t("Show the member directory"),
        tooltip: t("Show the Directory page in the Member Portal to the classifications chosen below."),
      },
    ],
    onSave: () => void loadPermissionsCatalog(),
  });

  // Both switches feed the catalog's On/Off column, so it is redrawn after a save.
  panel("#portalMemberFeaturesPanel", {
    title: t("What members can use"),
    icon: "fa-solid fa-toggle-on",
    settings: [
      {
        name: "bPortalAllowBirthdayEdit",
        type: "boolean",
        label: t("Let members edit birthdays"),
        tooltip: t("Allow members to change their own and their family members' birthdays."),
      },
      {
        name: "bPortalShowVolunteer",
        type: "boolean",
        label: t("Show volunteering"),
        tooltip: t("Show the volunteering and team sections in the Member Portal."),
      },
    ],
    onSave: () => void loadPermissionsCatalog(),
  });
}

function notifySaved(): void {
  window.CRM?.notify?.(t("Settings saved successfully"), { type: "success", delay: 2000 });
}

function notifyRefused(message: string | undefined, fallback: string): void {
  window.CRM?.notify?.(message || fallback, { type: "error", delay: 5000 });
}

/** POST a JSON body; a network failure reads as a refusal with no message. */
async function postJSON<T>(
  path: string,
  body: unknown,
  headers: Record<string, string> = {},
): Promise<{ ok: boolean; body: T | null }> {
  try {
    const response = await fetch(`${root()}${path}`, {
      method: "POST",
      headers: { "content-type": "application/json", ...headers },
      body: JSON.stringify(body),
    });
    return { ok: response.ok, body: (await response.json().catch(() => null)) as T | null };
  } catch {
    return { ok: false, body: null };
  }
}

const calendarKey = (type: string, id: number): string => `${type}:${id}`;

/** The line above the list: whether members see the Calendar page at all. */
function paintCalendarRule(shared: boolean): void {
  const rule = document.getElementById("portalCalendarsRule");
  if (!rule) {
    return;
  }
  rule.querySelector(".portal-calendars-rule-shown")?.classList.toggle("d-none", !shared);
  rule.querySelector(".portal-calendars-rule-hidden")?.classList.toggle("d-none", shared);
}

function calendarRows(): HTMLTableRowElement[] {
  return Array.from(document.querySelectorAll<HTMLTableRowElement>(`#${CALENDARS_TABLE_ID} tr[data-calendar-type]`));
}

function rowCalendar(row: HTMLTableRowElement): { type: string; id: number } {
  return { type: row.dataset.calendarType ?? "", id: Number.parseInt(row.dataset.calendarId ?? "0", 10) };
}

/**
 * The Calendars tab saves one switch at a time. Each request sends the set the
 * server last confirmed with only that switch changed, so a refused switch
 * never carries another row's change with it.
 */
function wireCalendarControls(): void {
  const table = document.getElementById(CALENDARS_TABLE_ID);
  if (!table) {
    return;
  }

  const saved = new Set<string>();
  for (const row of calendarRows()) {
    if (row.querySelector<HTMLInputElement>(".portal-calendar-switch")?.checked) {
      const calendar = rowCalendar(row);
      saved.add(calendarKey(calendar.type, calendar.id));
    }
  }

  let queue: Promise<void> = Promise.resolve();

  const save = async (input: HTMLInputElement, row: HTMLTableRowElement): Promise<void> => {
    const wanted = input.checked;
    const calendar = rowCalendar(row);
    const key = calendarKey(calendar.type, calendar.id);
    const visible = new Set(saved);
    if (wanted) {
      visible.add(key);
    } else {
      visible.delete(key);
    }

    const { ok, body } = await postJSON<{ message?: string; calendars?: CalendarChoice[] }>(
      "/admin/api/member-portal/calendars",
      {
        visible: Array.from(visible, (entry) => {
          const [type, id] = entry.split(":");
          return { type, id: Number.parseInt(id, 10) };
        }),
      },
    );

    if (!ok || !body?.calendars) {
      if (input.checked === wanted) {
        input.checked = saved.has(key);
      }
      notifyRefused(body?.message, t("The calendars could not be saved."));
      return;
    }

    saved.clear();
    for (const choice of body.calendars) {
      if (choice.visible) {
        saved.add(calendarKey(choice.type, choice.id));
      }
    }
    paintCalendarRule(saved.size > 0);
    notifySaved();
    void loadPermissionsCatalog();
  };

  table.addEventListener("change", (event) => {
    const input = (event.target as HTMLElement).closest<HTMLInputElement>(".portal-calendar-switch");
    const row = input?.closest<HTMLTableRowElement>("tr[data-calendar-type]");
    if (input && row) {
      queue = queue.then(() => save(input, row)).catch(() => undefined);
    }
  });
}

type SignInValue = boolean | string | number[];

/** One Sign-in control: its value as the API takes it, and how to show a stored value again. */
interface SignInControl {
  read(): SignInValue;
  write(stored: string): void;
}

const storedIds = (stored: string): string[] =>
  stored
    .split(",")
    .map((id) => id.trim())
    .filter((id) => id !== "");

function signInControls(form: HTMLFormElement): Map<string, SignInControl> {
  const controls = new Map<string, SignInControl>();

  for (const name of SIGN_IN_SWITCHES) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      controls.set(name, {
        read: () => input.checked,
        write: (stored) => {
          input.checked = stored === "1";
        },
      });
    }
  }
  for (const name of SIGN_IN_NUMBERS) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      controls.set(name, {
        read: () => input.value.trim(),
        write: (stored) => {
          input.value = stored;
        },
      });
    }
  }
  for (const name of SIGN_IN_CHECKBOX_LISTS) {
    const boxes = Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${name}"]`));
    controls.set(name, {
      read: () => boxes.filter((box) => box.checked).map((box) => Number.parseInt(box.value, 10)),
      write: (stored) => {
        const ids = storedIds(stored);
        for (const box of boxes) {
          box.checked = ids.includes(box.value);
        }
      },
    });
  }
  const office = form.querySelector<HTMLSelectElement>(`select[name="${SIGN_IN_OFFICE_LIST}"]`);
  if (office) {
    controls.set(SIGN_IN_OFFICE_LIST, {
      read: () => {
        const picked = office.tomselect
          ? office.tomselect.getValue()
          : Array.from(office.selectedOptions, (o) => o.value);
        return (Array.isArray(picked) ? picked : picked.split(","))
          .filter((value) => value !== "")
          .map((value) => Number.parseInt(value, 10));
      },
      write: (stored) => {
        const ids = storedIds(stored);
        if (office.tomselect) {
          office.tomselect.setValue(ids, true);
        } else {
          for (const option of office.options) {
            option.selected = ids.includes(option.value);
          }
        }
      },
    });
  }
  return controls;
}

const sameValue = (a: SignInValue, b: SignInValue): boolean => JSON.stringify(a) === JSON.stringify(b);

function paintSignInCounts(body: SignInSaveResponse): void {
  const count = document.getElementById("memberSignInMayCount");
  if (count && typeof body.maySignInCount === "number") {
    count.textContent = String(body.maySignInCount);
  }
  if (typeof body.sharedAddresses === "number") {
    for (const shared of document.querySelectorAll<HTMLElement>("[data-shared-address-count]")) {
      shared.textContent = String(body.sharedAddresses);
    }
    document.getElementById("memberSignInSharedEmails")?.classList.toggle("d-none", body.sharedAddresses === 0);
  }
}

/** Why the saved rule lets nobody in, or "" when it lets someone in. */
function signInRuleWarning(values: Record<string, string>, maySignInCount: number): string {
  const noFamilyRole = storedIds(values.sMemberSignInFamilyRoles ?? "").length === 0;
  const noClassification = storedIds(values.sMemberSignInClassifications ?? "").length === 0;
  if (noFamilyRole && noClassification) {
    return t(
      "Nobody can sign in yet: tick at least one family role and at least one classification under Who may sign in.",
    );
  }
  if (noFamilyRole) {
    return t("Nobody can sign in yet: tick at least one family role under Who may sign in.");
  }
  if (noClassification) {
    return t("Nobody can sign in yet: tick at least one classification under Who may sign in.");
  }
  if (maySignInCount === 0) {
    return t(
      "No one matches the sign-in rule yet. Tick more family roles or classifications, or lower the minimum age.",
    );
  }
  return "";
}

/** The warnings beside the Member sign-in switch and the classifications, shown while member sign-in is on. */
function paintSignInRuleWarning(values: Record<string, string>, maySignInCount: number): void {
  const warning = values.bMemberSignIn === "1" ? signInRuleWarning(values, maySignInCount) : "";
  for (const alert of document.querySelectorAll<HTMLElement>("[data-sign-in-rule-warning]")) {
    const text = alert.querySelector("[data-sign-in-rule-warning-text]");
    if (text) {
      text.textContent = warning;
    }
    alert.classList.toggle("d-none", warning === "");
  }
}

/** The counts, lists and permissions on the other tabs that follow the sign-in rule. */
function refreshAfterSignInSave(): void {
  void refreshStatistics();
  refreshMemberLists();
  void loadPermissionsCatalog();
}

function confirmMemberSignIn(turningOn: boolean, ruleWarning: string): Promise<boolean> {
  const maySignIn = document.getElementById("portalStat-maySignIn")?.textContent?.trim() ?? "";
  const warning =
    maySignIn === "0" && ruleWarning !== ""
      ? `<div class="alert alert-warning mt-3 mb-0" id="memberSignInConfirmWarning">
           <i class="fa-solid fa-triangle-exclamation me-2"></i>${escapeHtml(ruleWarning)}
         </div>`
      : "";
  const message = turningOn
    ? `<p>${escapeHtml(t("Everyone the sign-in rules let in can then sign in to the Member Portal with their own email."))}</p>
       <p class="mb-0 fw-medium">${escapeHtml(t("People who may sign in: {{total}}", { total: maySignIn }))}</p>${warning}`
    : `<p class="mb-0">${escapeHtml(
        t(
          "Members who are signed in now are signed out, and nobody can sign in with their email until member sign-in is turned on again.",
        ),
      )}</p>`;

  return new Promise((resolve) => {
    if (!window.bootbox) {
      resolve(false);
      return;
    }
    window.bootbox.confirm({
      title: turningOn ? t("Turn on member sign-in?") : t("Turn off member sign-in?"),
      message,
      buttons: {
        cancel: { label: t("Cancel") },
        confirm: turningOn
          ? { label: t("Turn on"), className: "btn-primary" }
          : { label: t("Turn off"), className: "btn-danger" },
      },
      callback: (result: boolean) => {
        resolve(result === true);
      },
    });
  });
}

/**
 * The Sign-in tab saves each control on its own change, one request at a
 * time, by sending only that setting. Member sign-in itself asks first: on, it
 * lets every eligible member in; off, it signs out everyone signed in.
 */
function wireSignInTab(): void {
  const form = document.getElementById(SIGN_IN_FORM_ID) as HTMLFormElement | null;
  if (!form) {
    return;
  }

  const office = form.querySelector<HTMLSelectElement>(`select[name="${SIGN_IN_OFFICE_LIST}"]`);
  if (office && typeof window.CRM?.initPersonSelect === "function") {
    window.CRM.initPersonSelect(office, {
      endpoint: "/admin/api/member-portal/sign-in/office-candidates/",
      plugins: ["remove_button"],
    });
  }

  const controls = signInControls(form);
  const config = window.CRM?.memberPortalAdmin as
    | { signInValues?: Record<string, string>; maySignInCount?: number }
    | undefined;
  const saved: Record<string, string> = { ...(config?.signInValues ?? {}) };
  let maySignInCount = config?.maySignInCount ?? 0;
  let queue: Promise<void> = Promise.resolve();
  paintSignInRuleWarning(saved, maySignInCount);

  const save = async (name: string, control: SignInControl): Promise<void> => {
    const sent = control.read();
    const { ok, body } = await postJSON<SignInSaveResponse>(
      "/admin/api/member-portal/sign-in",
      { [name]: sent },
      { "X-CSRF-Token": csrfToken() },
    );

    if (!ok || !body?.values) {
      if (sameValue(control.read(), sent) && saved[name] !== undefined) {
        control.write(saved[name]);
      }
      notifyRefused(body?.message, t("The sign-in settings could not be saved."));
      return;
    }

    Object.assign(saved, body.values);
    if (sameValue(control.read(), sent)) {
      control.write(saved[name]);
    }
    paintSignInCounts(body);
    if (typeof body.maySignInCount === "number") {
      maySignInCount = body.maySignInCount;
    }
    paintSignInRuleWarning(saved, maySignInCount);
    const memberSignIn = form.querySelector<HTMLInputElement>('input[name="bMemberSignIn"]');
    if (memberSignIn && !memberSignIn.checked && document.getElementById("memberSignInEmailProblem")) {
      memberSignIn.disabled = true;
    }
    notifySaved();
    refreshAfterSignInSave();
  };

  const enqueue = (work: () => Promise<void>): void => {
    queue = queue.then(work).catch(() => undefined);
  };

  form.addEventListener("change", (event) => {
    const name = (event.target as HTMLInputElement | HTMLSelectElement).name;
    const control = controls.get(name);
    if (!control) {
      return;
    }
    if (name !== "bMemberSignIn") {
      enqueue(() => save(name, control));
      return;
    }
    const turningOn = control.read() === true;
    enqueue(async () => {
      if (turningOn) {
        await refreshStatistics();
      }
      if (await confirmMemberSignIn(turningOn, signInRuleWarning(saved, maySignInCount))) {
        await save(name, control);
      } else {
        control.write(saved[name] ?? (turningOn ? "0" : "1"));
      }
    });
  });
  form.addEventListener("submit", (event) => event.preventDefault());
}

/** Open the tab a link points at, such as /admin/member-portal#portal-signin. */
function collectDirectorySettings(form: HTMLFormElement): Record<string, unknown> {
  const ticked = (name: string): number[] =>
    Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${name}"]:checked`), (input) =>
      Number(input.value),
    );
  const fields: Record<string, boolean> = {};
  for (const input of form.querySelectorAll<HTMLInputElement>('input[name="fields"]')) {
    fields[input.value] = input.checked;
  }
  const choice = (name: string): string => form.querySelector<HTMLSelectElement>(`select[name="${name}"]`)?.value ?? "";

  return {
    listed: ticked("listed"),
    viewers: ticked("viewers"),
    fields,
    birthday: choice("birthday"),
    anniversary: choice("anniversary"),
    browse: form.querySelector<HTMLInputElement>('input[name="browse"]:checked')?.value !== "0",
  };
}

async function saveDirectorySettings(form: HTMLFormElement): Promise<void> {
  const response = await fetch(`${root()}/admin/api/member-portal/directory`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": csrfToken() },
    body: JSON.stringify(collectDirectorySettings(form)),
  });

  if (!response.ok) {
    const body: { message?: string } | null = await response.json().catch(() => null);
    window.CRM?.notify?.(body?.message || t("Failed to save settings"), { type: "error", delay: 5000 });
    return;
  }

  window.CRM?.notify?.(t("Settings saved successfully"), { type: "success", delay: 2000 });
  void loadPermissionsCatalog();
}

function wireDirectoryTab(): void {
  const form = document.getElementById("portalDirectoryForm");
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  form.addEventListener("change", () => void saveDirectorySettings(form));
  form.addEventListener("submit", (event) => event.preventDefault());
}

function openTabFromHash(): void {
  const hash = window.location.hash;
  if (!/^#portal-[a-z-]+$/.test(hash)) {
    return;
  }
  const link = document.getElementById(`${hash.slice(1)}-tab`);
  const tabs = (window.bootstrap as unknown as { Tab?: { getOrCreateInstance(el: Element): { show(): void } } }).Tab;
  if (link && tabs) {
    tabs.getOrCreateInstance(link).show();
  }
}

function start(): void {
  wireThemeControls();
  wireSettingsPanels();
  wireCalendarControls();
  wireSignInTab();
  wireMembersTab();
  wirePermissionsTab();
  wireAuditTab();
  wireDirectoryTab();
  openTabFromHash();
  window.addEventListener("hashchange", openTabFromHash);
  document.getElementById("portal-statistics-tab")?.addEventListener("click", () => {
    void refreshStatistics();
  });
}

if (typeof window.CRM?.onLocalesReady === "function") {
  window.CRM.onLocalesReady(start);
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
