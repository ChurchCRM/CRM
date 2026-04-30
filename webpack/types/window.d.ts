/**
 * Ambient type declarations for the global window.CRM namespace injected by PHP templates.
 * No imports/exports — this is a plain ambient script so all interfaces are globally accessible.
 */

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

interface CRMNamespace {
  root?: string;
  plugins?: CRMPlugins;
  bEnableGravatarPhotos?: boolean;
  showPhotoLightbox?: (type: string, id: number) => void;
  avatarLoader?: unknown;
  peopleImageLoader?: unknown;
  APIRequest?: (options: CRMAPIRequestOptions) => { done: (cb: () => void) => unknown };
  notify?: (message: string | object, options?: Record<string, unknown>) => void;
  notyf?: unknown;
  escapeHtml?: (s: string) => string;
  [key: string]: unknown;
}

interface Window {
  CRM?: CRMNamespace;
}
