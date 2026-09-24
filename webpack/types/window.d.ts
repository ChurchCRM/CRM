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

interface CRMEmailComposerOptions {
  emails: string[];
  byRole?: Record<string, string[]>;
  title: string;
  /** Church default "to" address (sToEmailAddress); offered as a removable default recipient. */
  defaultTo?: string;
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

interface CRMNamespace {
  root?: string;
  timeZone?: string;
  plugins?: CRMPlugins;
  bEnableGravatarPhotos?: boolean;
  showPhotoLightbox?: (type: string, id: number) => void;
  avatarLoader?: unknown;
  peopleImageLoader?: unknown;
  APIRequest?: (options: CRMAPIRequestOptions) => { done: (cb: () => void) => unknown };
  notify?: (message: string | object, options?: Record<string, unknown>) => void;
  notyf?: unknown;
  escapeHtml?: (s: string) => string;
  escapeAttribute?: (s: string) => string;
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
    smtpConfigured?: boolean;
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

interface Window {
  CRM?: CRMNamespace;
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
