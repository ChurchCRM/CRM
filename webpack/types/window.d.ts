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

interface Window {
  CRM?: CRMNamespace;
  bootstrap: {
    Modal: {
      getOrCreateInstance(el: Element): BootstrapModalInstance;
      getInstance(el: Element): BootstrapModalInstance | null;
      new (el: Element, options?: Record<string, unknown>): BootstrapModalInstance;
    };
  };
}
