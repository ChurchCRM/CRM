/**
 * Ambient type declarations for the global window.CRM namespace injected by PHP templates.
 * No imports/exports — this is a plain ambient script so all interfaces are globally accessible.
 */

// i18next — loaded globally via skin-core.js
declare const i18next: { t(key: string, options?: Record<string, unknown>): string };

interface CRMGravatarPlugin {
  enabled?: boolean;
  defaultImage?: string;
}

interface CRMPlugins {
  gravatar?: CRMGravatarPlugin;
  [key: string]: unknown;
}

interface CRMAPIRequestOptions {
  method: string;
  path: string;
  [key: string]: unknown;
}

/** One recipient with the record behind the address, as returned by the /emails list endpoints. */
interface CRMEmailRecipient {
  personId?: number | null;
  familyId?: number | null;
  name: string;
  email: string;
}

interface CRMEmailComposerOptions {
  emails: string[];
  byRole?: Record<string, string[]>;
  title: string;
  /** Church default "to" address (sToEmailAddress); offered as a removable default recipient. */
  defaultTo?: string;
  /** Records behind the addresses; required for the server-side Send (ids are posted, not addresses). */
  recipients?: CRMEmailRecipient[];
}

interface CRMEmailComposer {
  open(options: CRMEmailComposerOptions): void;
}

/**
 * Minimal structural type for a TomSelect instance. The `tom-select` package
 * (v2.6.2) ships no type declarations, so the members actually used in this
 * codebase are declared here rather than pulled from the library.
 */
interface TomSelectInstance {
  /** Loaded options keyed by `valueField` (here: the person id). */
  options: Record<string, Record<string, unknown>>;
  /** The original `<select>`/`<input>` TomSelect wrapped. */
  input: HTMLElement;
  addOption(data: Record<string, unknown>, userCreated?: boolean): string | undefined;
  setValue(value: string | number | string[], silent?: boolean): void;
  getValue(): string | string[];
  clear(silent?: boolean): void;
  clearOptions(): void;
  /** Moves keyboard focus into the control (#9709 uses it on `shown.bs.modal`). */
  focus(): void;
  destroy(): void;
}

type TomSelectConstructor = new (el: HTMLElement | string, settings?: Record<string, unknown>) => TomSelectInstance;

/** One row of `GET /api/persons/search/{query}` (src/api/routes/people/people-persons.php). */
interface PersonSearchResult {
  objid: string | number;
  text: string;
  uri?: string;
  [key: string]: unknown;
}

/**
 * Options for the shared person-search TomSelect helper
 * (`webpack/common/person-select.ts`, issue #9819).
 */
interface PersonSelectOptions {
  /** Search route the `load` callback appends the URL-encoded query to. Default `/api/persons/search/`. */
  endpoint?: string;
  /** Maps one raw API row onto the option object TomSelect stores. Default: identity. */
  mapResult?: (item: PersonSearchResult) => PersonSearchResult;
  /** Overrides the element's `data-placeholder`. */
  placeholder?: string;
  /** Passed straight to TomSelect's `render` setting (e.g. `option` / `item`). */
  render?: Record<string, unknown>;
  /** Called with `this` bound to the TomSelect instance, plus the original element. */
  onChange?: (this: TomSelectInstance, value: string, el: HTMLSelectElement) => void;
}

/** `attachToModal` additionally reports the instance it built. */
interface PersonSelectModalOptions extends PersonSelectOptions {
  onInit?: (ts: TomSelectInstance, el: HTMLSelectElement) => void;
}

/** Handle returned by `attachToModal`. */
interface PersonSelectModalHandle {
  /** The live instance, or null before `shown.bs.modal` / after `hidden.bs.modal`. */
  getInstance(): TomSelectInstance | null;
  /** Unregisters the modal listeners and destroys any live instance. */
  detach(): void;
}

/**
 * One entry in the item list `window.CRM.buildActionMenu()` accepts.
 * Every value here is raw — the builder does all escaping.
 */
interface CRMActionMenuItem {
  type: "link" | "button" | "divider";
  /** `link` only. */
  href?: string;
  /** Font Awesome classes, e.g. "fa-solid fa-eye"; `me-2` is appended. */
  icon?: string;
  label?: string;
  /** Extra classes appended to `dropdown-item`. */
  className?: string;
  /** Prefixes `text-danger`; use for destructive items. */
  danger?: boolean;
  /** Wraps the label in a `<span>` carrying this class (e.g. "cart-label"). */
  labelClass?: string;
  /** `data-*` attributes, keyed without the `data-` prefix. */
  data?: Record<string, string | number | null | undefined>;
  /** `button` only; emit `class=` before `type=` (cart-button markup compatibility). */
  classBeforeType?: boolean;
}

interface CRMActionMenuOptions {
  /** Default: "dropdown". */
  wrapperClass?: string;
  /** Default: "dropdown-menu dropdown-menu-end". */
  menuClass?: string;
}

interface CRMPersonActionMenuOptions {
  inCart?: boolean;
  /** When set, adds a "View Family" item after Edit. */
  familyId?: number | null;
}

interface CRMFamilyActionMenuOptions {
  inCart?: boolean;
}

interface CRMEventActionMenuOptions {
  /** Controls Activate vs Deactivate. */
  inactive?: boolean;
}

/**
 * Per-page config the volunteer views hand their bundle through an inline
 * `<script>` (the `window.CRM.eventTypesList` idiom).
 */
interface CRMVolunteerMinistryConfig {
  ministryId: number;
  /** Advisory: a global volunteer manager, who alone may grant scopes (§3.2). */
  isManager: boolean;
  /**
   * Advisory: the server believes the viewer coordinates this ministry or better
   * — true for an administrator, a global manager and a ministry-scope holder,
   * false for a team leader. It decides whether "Remove Volunteer" is offered and
   * nothing else; the API authorizes independently (design §4.5, D5).
   */
  isMinistryCoordinator: boolean;
}

/**
 * Per-page config for S1, handed to the bundle by
 * `src/volunteer/views/dashboard.php` (issue #9711).
 *
 * `isAdmin` is advisory: it says whether the settings strip was rendered at all, and
 * the server decided that. It is never used to authorize anything — every read is
 * scoped server-side in `GET /api/volunteer/dashboard` (design §4.4).
 */
interface CRMVolunteerDashboardConfig {
  /** Initial window length in days; the select on the page can widen it. */
  days: number;
  isAdmin: boolean;
  isManager: boolean;
}

/**
 * Per-page config for S4, handed to the bundle by
 * `src/volunteer/views/occurrence-view.php` (issue #9709).
 *
 * `eventId` is 0 for a standalone occurrence. It is advisory only — the page asks
 * the API whether attendance is available (`attendanceAvailable`) rather than
 * inferring it, because only the server knows whether the linked event still exists.
 */
interface CRMVolunteerOccurrenceConfig {
  occurrenceId: number;
  ministryId: number;
  eventId: number;
}

/**
 * Per-page config for the Member Portal's My Teams page, handed to
 * `webpack/portal/teams.ts` by `teams/team.html.twig` (issue #9868).
 *
 * Both values are advisory in the usual sense: they say which team the page was
 * rendered for, and the route already decided the reader may run it. Every
 * request the bundle makes is authorized again per record server-side (§4.5).
 */
interface CRMPortalTeamConfig {
  teamId: number;
  /** The team's ministry — the create-position and create-schedule routes are keyed on it. */
  ministryId: number;
}

/**
 * The slice of `window.CRM.groups` (src/skin/js/CRMJSOM.js) that V2 calls.
 *
 * Declared so the group picker is type-checked rather than resolving through
 * the `[key: string]: unknown` catch-all below (U10). `promptSelection()` hands
 * the callback string ids — they come out of a TomSelect, which stores its
 * values as strings — so callers must `Number()` them.
 */
interface CRMGroupsNamespace {
  /** Bit flags: Group = 1, Role = 2; `Group | Role` asks for both. */
  selectTypes: { Group: number; Role: number };
  promptSelection?: (
    options: { Type: number; GroupID?: number | string },
    callback: (selection: { GroupID?: string; RoleID?: string | null }) => void,
  ) => void;
  [key: string]: unknown;
}

/**
 * The shared DataTables defaults every table merges over its own options
 * (`$.extend(config, window.CRM.plugin.dataTable)`), set in
 * `src/skin/js/CRMJSOM.js`.
 */
interface CRMPluginDefaults {
  dataTable?: Record<string, unknown>;
  [key: string]: unknown;
}

/** One row of `window.CRM.settingsPanel.init({ settings: [...] })`. */
interface CRMSettingsPanelSetting {
  name: string;
  type: "boolean" | "number" | "text" | "choice" | "password" | "date" | "textarea" | "json" | "ajax";
  label?: string;
  tooltip?: string;
  choices?: Array<string | { value: string; label: string }>;
  [key: string]: unknown;
}

/** The reusable SystemConfig editor from webpack/system-settings-panel.js. */
interface CRMSettingsPanel {
  init(options: {
    container: string;
    title?: string;
    icon?: string;
    headerClass?: string;
    showAllSettingsLink?: boolean;
    settings: Array<string | CRMSettingsPanelSetting>;
    onSave?: (savedValues?: Record<string, string>) => void;
    [key: string]: unknown;
  }): void;
}

interface CRMNamespace {
  root?: string;
  settingsPanel?: CRMSettingsPanel;
  timeZone?: string;
  plugins?: CRMPlugins;
  plugin?: CRMPluginDefaults;
  /** Set by src/volunteer/views/ministry-view.php (issue #9715). */
  volunteerMinistry?: CRMVolunteerMinistryConfig;
  /** Set by src/volunteer/views/dashboard.php (issue #9711). */
  volunteerDashboard?: CRMVolunteerDashboardConfig;
  /** Set by src/volunteer/views/occurrence-view.php, and by the Member Portal's
   * teams/occurrence.html.twig, which reuses the same bundle (issues #9709, #9868). */
  volunteerOccurrence?: CRMVolunteerOccurrenceConfig;
  /** Set by the Member Portal's teams/team.html.twig (issue #9868). */
  portalTeam?: CRMPortalTeamConfig;
  /** The Groups helpers, including the shared group picker (G4). */
  groups?: CRMGroupsNamespace;
  bEnableGravatarPhotos?: boolean;
  showPhotoLightbox?: (type: string, id: number) => void;
  avatarLoader?: unknown;
  peopleImageLoader?: unknown;
  APIRequest?: (options: CRMAPIRequestOptions) => { done: (cb: () => void) => unknown };
  notify?: (message: string | object, options?: Record<string, unknown>) => void;
  notyf?: unknown;
  /** Member Portal only: show a toast in the portal's fixed top-right stack.
   * Published by the `portal` bundle, which every portal page loads, so a page
   * bundle, a plugin or a theme's `theme.js` can raise a notice without knowing
   * how the portal draws one. */
  portalToast?: (message: string, type?: "success" | "danger" | "warning" | "info") => void;
  /** Escapes `&`, `<` and `>` — safe for text nodes, NOT for attribute values. */
  escapeHtml?: (s: string) => string;
  /** Escapes `&`, `<`, `>` and both quote characters — use for attribute values. */
  escapeAttribute?: (s: string) => string;
  /**
   * Builds the canonical Tabler row-action dropdown, and is the single place
   * menu labels and `data-*` values are escaped. Falsy items are skipped.
   */
  buildActionMenu?: (items: Array<CRMActionMenuItem | null | false | undefined>, opts?: CRMActionMenuOptions) => string;
  renderPersonActionMenu?: (personId: number, personName: string, options?: CRMPersonActionMenuOptions) => string;
  renderFamilyActionMenu?: (familyId: number, familyName?: string, options?: CRMFamilyActionMenuOptions) => string;
  renderEventActionMenu?: (eventId: number, eventTitle: string, options?: CRMEventActionMenuOptions) => string;
  emailComposer?: CRMEmailComposer;
  /** Shared AJAX person-search TomSelect (webpack/common/person-select.ts), re-exported
   * by skin-core.js for scripts that are not part of a webpack bundle — currently
   * src/skin/js/GroupView.js, which is loaded as a plain `<script src>`. */
  initPersonSelect?: (el: HTMLSelectElement, opts?: PersonSelectOptions) => TomSelectInstance;
  /** Initialises every not-yet-initialised `.personSearch` / `.person-search` in `root`. */
  initAllPersonSelects?: (opts?: PersonSelectOptions, root?: ParentNode) => TomSelectInstance[];
  /** Set by locale-loader.js once i18next has finished initializing. Calls back
   * immediately if locales are already loaded, otherwise waits for the
   * "CRM.localesReady" event — the ordering hook other modules should use before
   * calling i18next.t() on page load. */
  onLocalesReady?: (callback: () => void) => void;
  comm?: {
    emailSendingEnabled?: boolean;
    /** Closing pre-filled at the end of a new composer message ("Sincerely,\nSigner"). */
    emailSignature?: string;
    vonageEnabled?: boolean;
    /** Church default "to" address (sToEmailAddress); "" when unset or user lacks email permission. */
    defaultEmailToAddress?: string;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

interface BootstrapModalInstance {
  show(): void;
  hide(): void;
}

/** TomSelect stores its instance back onto the element it wrapped. */
interface HTMLElement {
  tomselect?: TomSelectInstance;
}

/**
 * The members of the global `bootbox` actually used in this codebase. The
 * package ships no type declarations and is loaded as a global by the skin, so
 * the surface is declared here rather than pulled from the library — the same
 * approach `TomSelectInstance` above takes.
 */
interface BootboxStatic {
  alert(options: string | Record<string, unknown>): void;
  confirm(options: {
    title?: string;
    message: string;
    buttons?: Record<string, { label?: string; className?: string }>;
    callback: (result: boolean) => void;
  }): void;
  prompt(options: Record<string, unknown>): void;
}

interface Window {
  CRM?: CRMNamespace;
  /** Loaded globally by the skin; see BootboxStatic. */
  bootbox?: BootboxStatic;
  /** Exposed globally by skin-core.js so non-bundled scripts share one TomSelect class. */
  TomSelect: TomSelectConstructor;
  bootstrap: {
    Modal: {
      getOrCreateInstance(el: Element): BootstrapModalInstance;
      getInstance(el: Element): BootstrapModalInstance | null;
      new (el: Element, options?: Record<string, unknown>): BootstrapModalInstance;
    };
  };
}
