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
