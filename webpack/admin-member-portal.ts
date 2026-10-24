/**
 * Admin → Member Portal (#9864) — the page's behaviour.
 *
 * Five tabs are rendered server-side (Statistics, Settings, Sign-in, Themes,
 * Calendars); this bundle adds the six things that need the browser:
 *
 *   1. the Settings Panel component for the four portal ConfigItems, which
 *      saves through POST /admin/api/system/config/{name};
 *   2. the "Check" button, which re-runs the theme validator and lists what it
 *      found without changing anything;
 *   3. theme activation, which goes through POST /admin/api/member-portal/theme
 *      so the validator can refuse — a refusal lists the findings and leaves
 *      the active theme alone;
 *   4. a refresh of the statistics numbers when that tab is opened;
 *   5. the Calendars tab's save, which posts the switched-on calendars to
 *      POST /admin/api/member-portal/calendars;
 *   6. the Sign-in tab's save, which posts every sign-in setting to
 *      POST /admin/api/member-portal/sign-in. The server checks them all and
 *      saves none when one is wrong, naming each problem.
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
import { wireMembersTab } from "./admin-member-portal/members-tab";
import { wirePermissionsTab } from "./admin-member-portal/permissions-tab";

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

const SETTINGS_CONTAINER = "#portalSettingsPanel";
const THEME_SELECT_ID = "portalThemeSelect";
const FINDINGS_ID = "portalThemeFindings";
const STATUS_BADGE_ID = "portalThemeStatusBadge";
const CALENDARS_TABLE_ID = "portalCalendarsTable";
const CALENDARS_STATUS_ID = "portalCalendarsStatus";

const SIGN_IN_FORM_ID = "memberSignInForm";
const SIGN_IN_SWITCHES = ["bMemberSignIn", "bPortalPasskeys", "bAllowLoginAsUser", "bLoginAsUserCanWrite"];
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
  errors?: Record<string, string>;
  maySignInCount?: number;
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
function t(key: string): string {
  if (typeof i18next === "undefined" || typeof i18next.t !== "function") {
    return key;
  }
  const translated = i18next.t(key);
  return translated === undefined || translated === "" ? key : translated;
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

function paintStatusBadge(status: string): void {
  const badge = document.getElementById(STATUS_BADGE_ID);
  if (!badge) {
    return;
  }
  const presentation = statusPresentation(status);
  badge.className = `badge ${presentation.cssClass}`;
  badge.innerHTML = `<i class="fa-solid ${presentation.icon} me-1"></i><span class="badge-text">${escapeHtml(presentation.label)}</span>`;
}

/**
 * Draw the findings list under the theme picker. An empty list is a statement
 * in its own right — the designer asked a question and deserves an answer.
 */
function paintFindings(response: ThemeResponse): void {
  const container = document.getElementById(FINDINGS_ID);
  if (!container) {
    return;
  }

  if (response.findings.length === 0) {
    container.innerHTML = `<div class="alert alert-success mb-0"><i class="fa-solid fa-circle-check me-2"></i>${escapeHtml(
      t("This theme has no problems."),
    )}</div>`;
    return;
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

  container.innerHTML = `
    <div class="card">
      <div class="card-header py-2"><h4 class="card-title mb-0">${escapeHtml(heading)}</h4></div>
      <div class="card-body py-2"><ul class="list-unstyled mb-0">${rows}</ul></div>
    </div>`;
}

function selectedTheme(): string {
  const select = document.getElementById(THEME_SELECT_ID) as HTMLSelectElement | null;
  return select?.value ?? "";
}

function setBusy(button: HTMLButtonElement | null, busy: boolean): void {
  if (!button) {
    return;
  }
  button.disabled = busy;
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
  const body: ThemeResponse = await response.json();
  paintStatusBadge(body.status);
  paintFindings(body);
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
    paintStatusBadge("error");
    paintFindings({ ...body, activated: false });
    window.CRM?.notify?.(t("That theme has errors, so it was not activated."), { type: "danger" });
    return;
  }

  if (!response.ok || !body) {
    window.CRM?.notify?.(t("That theme could not be activated."), { type: "danger" });
    return;
  }

  paintStatusBadge(body.status);
  paintFindings(body);
  window.CRM?.notify?.(t("The Member Portal theme was changed."), { type: "success" });
  window.setTimeout(() => window.location.reload(), 1500);
}

/** Re-read the statistics so an admin who leaves the page open sees fresh numbers. */
async function refreshStatistics(): Promise<void> {
  const response = await fetch(`${root()}/admin/api/member-portal/stats`);
  if (!response.ok) {
    return;
  }
  const stats: PortalStats = await response.json();
  for (const [key, value] of Object.entries(stats)) {
    const cell = document.getElementById(`portalStat-${key}`);
    if (cell && typeof value === "number") {
      cell.textContent = String(value);
    }
  }
}

function wireThemeControls(): void {
  const checkButton = document.getElementById("portalThemeCheckButton") as HTMLButtonElement | null;
  const activateButton = document.getElementById("portalThemeActivateButton") as HTMLButtonElement | null;
  const select = document.getElementById(THEME_SELECT_ID) as HTMLSelectElement | null;

  checkButton?.addEventListener("click", async () => {
    setBusy(checkButton, true);
    await checkTheme(selectedTheme());
    setBusy(checkButton, false);
  });

  activateButton?.addEventListener("click", async () => {
    setBusy(activateButton, true);
    await activateTheme(selectedTheme());
    setBusy(activateButton, false);
  });

  // Picking a different entry shows that entry's badge from the server-side
  // validation; the Check button is what re-runs the validator.
  select?.addEventListener("change", () => {
    const option = select.options[select.selectedIndex];
    paintStatusBadge(option?.dataset.status ?? "ok");
    const findings = document.getElementById(FINDINGS_ID);
    if (findings) {
      findings.innerHTML = "";
    }
  });

  // Row actions on the Themes tab drive the same two operations.
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-activate")) {
    button.addEventListener("click", () => activateTheme(button.dataset.theme ?? ""));
  }
  for (const button of document.querySelectorAll<HTMLButtonElement>(".portal-theme-check")) {
    button.addEventListener("click", async () => {
      const name = button.dataset.theme ?? "";
      if (select) {
        select.value = name;
      }
      await checkTheme(name);
      document.getElementById("portal-settings-tab")?.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    });
  }
}

function wireSettingsPanel(): void {
  const panel = window.CRM?.settingsPanel;
  if (!panel || !document.querySelector(SETTINGS_CONTAINER)) {
    return;
  }

  panel.init({
    container: SETTINGS_CONTAINER,
    title: t("Portal settings"),
    icon: "fa-solid fa-sliders",
    headerClass: "bg-info-lt",
    // These four items deliberately carry no System Settings category, so a
    // link to that page would be a dead end.
    showAllSettingsLink: false,
    settings: [
      {
        name: "bPortalShowCalendar",
        type: "boolean",
        label: t("Show the church calendar"),
        tooltip: t("Show the church calendar section in the Member Portal."),
      },
      {
        name: "bPortalShowVolunteer",
        type: "boolean",
        label: t("Show volunteering"),
        tooltip: t("Show the volunteering and team sections in the Member Portal."),
      },
      {
        name: "bPortalAllowBirthdayEdit",
        type: "boolean",
        label: t("Let members edit birthdays"),
        tooltip: t("Allow members to change their own and their family members' birthdays."),
      },
      {
        name: "bPortalDeveloperMode",
        type: "boolean",
        label: t("Developer mode"),
        tooltip: t(
          "Turns off the portal template cache and prints the name of each template in an HTML comment. For theme designers; leave this off in normal use.",
        ),
      },
    ],
    onSave: () => {
      window.setTimeout(() => window.location.reload(), 1500);
    },
  });
}

/**
 * Save the Calendars tab: the set of switched-on calendars, as the whole list
 * rather than a diff, so what the server stores is exactly what the page shows.
 */
async function saveCalendars(button: HTMLButtonElement | null): Promise<void> {
  const table = document.getElementById(CALENDARS_TABLE_ID);
  const status = document.getElementById(CALENDARS_STATUS_ID);
  if (!table) {
    return;
  }

  const visible: Array<{ type: string; id: number }> = [];
  for (const row of table.querySelectorAll<HTMLTableRowElement>("tr[data-calendar-type]")) {
    const input = row.querySelector<HTMLInputElement>(".portal-calendar-switch");
    if (!input?.checked) {
      continue;
    }
    visible.push({
      type: row.dataset.calendarType ?? "",
      id: Number.parseInt(row.dataset.calendarId ?? "0", 10),
    });
  }

  setBusy(button, true);
  if (status) {
    status.textContent = "";
  }

  const response = await fetch(`${root()}/admin/api/member-portal/calendars`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ visible }),
  });
  setBusy(button, false);

  if (!response.ok) {
    window.CRM?.notify?.(t("The calendars could not be saved."), { type: "danger" });
    return;
  }

  if (status) {
    status.textContent =
      visible.length === 0
        ? t("No calendar is shared with members.")
        : t("Saved. Members see the calendars switched on above.");
  }
  window.CRM?.notify?.(t("The Member Portal calendars were saved."), { type: "success" });
}

function wireCalendarControls(): void {
  const button = document.getElementById("portalCalendarsSaveButton") as HTMLButtonElement | null;
  button?.addEventListener("click", () => {
    void saveCalendars(button);
  });
}

function collectSignInSettings(form: HTMLFormElement): Record<string, unknown> {
  const settings: Record<string, unknown> = {};
  for (const name of SIGN_IN_SWITCHES) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      settings[name] = input.checked;
    }
  }
  for (const name of SIGN_IN_NUMBERS) {
    const input = form.querySelector<HTMLInputElement>(`input[name="${name}"]`);
    if (input) {
      settings[name] = input.value.trim();
    }
  }
  for (const name of SIGN_IN_CHECKBOX_LISTS) {
    settings[name] = Array.from(form.querySelectorAll<HTMLInputElement>(`input[name="${name}"]:checked`), (input) =>
      Number.parseInt(input.value, 10),
    );
  }
  const office = form.querySelector<HTMLSelectElement>(`select[name="${SIGN_IN_OFFICE_LIST}"]`);
  if (office) {
    const picked = office.tomselect ? office.tomselect.getValue() : Array.from(office.selectedOptions, (o) => o.value);
    settings[SIGN_IN_OFFICE_LIST] = (Array.isArray(picked) ? picked : picked.split(","))
      .filter((value) => value !== "")
      .map((value) => Number.parseInt(value, 10));
  }
  return settings;
}

function paintSignInErrors(form: HTMLFormElement, errors: Record<string, string>): void {
  for (const slot of form.querySelectorAll<HTMLElement>("[data-error-for]")) {
    slot.textContent = errors[slot.dataset.errorFor ?? ""] ?? "";
  }
  for (const input of form.querySelectorAll<HTMLElement>(".is-invalid")) {
    input.classList.remove("is-invalid");
  }
  for (const name of Object.keys(errors)) {
    for (const input of form.querySelectorAll<HTMLElement>(`[name="${name}"]`)) {
      input.classList.add("is-invalid");
    }
  }
}

async function saveSignInSettings(form: HTMLFormElement, button: HTMLButtonElement | null): Promise<void> {
  const status = document.getElementById("memberSignInStatus");
  setBusy(button, true);
  if (status) {
    status.textContent = "";
  }

  const response = await fetch(`${root()}/admin/api/member-portal/sign-in`, {
    method: "POST",
    headers: { "content-type": "application/json", "X-CSRF-Token": csrfToken() },
    body: JSON.stringify(collectSignInSettings(form)),
  });
  setBusy(button, false);

  let body: SignInSaveResponse | null = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  if (response.status === 400 && body?.errors) {
    paintSignInErrors(form, body.errors);
    window.CRM?.notify?.(t("Some sign-in settings need fixing. Nothing was saved."), { type: "danger" });
    return;
  }
  if (!response.ok) {
    window.CRM?.notify?.(t("The sign-in settings could not be saved."), { type: "danger" });
    return;
  }

  paintSignInErrors(form, {});
  const count = document.getElementById("memberSignInMayCount");
  if (count && typeof body?.maySignInCount === "number") {
    count.textContent = String(body.maySignInCount);
  }
  const memberSignIn = form.querySelector<HTMLInputElement>('input[name="bMemberSignIn"]');
  if (memberSignIn && !memberSignIn.checked && document.getElementById("memberSignInEmailProblem")) {
    memberSignIn.disabled = true;
  }
  if (status) {
    status.textContent = t("Saved.");
  }
  window.CRM?.notify?.(t("The sign-in settings were saved."), { type: "success" });
}

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

  const button = document.getElementById("memberSignInSaveButton") as HTMLButtonElement | null;
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    void saveSignInSettings(form, button);
  });
}

/** Open the tab a link points at, such as /admin/member-portal#portal-signin. */
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
  wireSettingsPanel();
  wireCalendarControls();
  wireSignInTab();
  wireMembersTab();
  wirePermissionsTab();
  wireAuditTab();
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
