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
