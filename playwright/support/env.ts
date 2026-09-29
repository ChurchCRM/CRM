/**
 * Shared environment configuration for the marketing visual-media pipeline.
 *
 * All values are overridable via environment variables so the pipeline can
 * run against any reachable ChurchCRM instance (see playwright/README.md).
 */

export const BASE_URL = process.env.CHURCHCRM_BASE_URL || 'http://127.0.0.1:8081/';

export const DB = {
  host: process.env.CHURCHCRM_DB_HOST || 'database-new-system',
  port: process.env.CHURCHCRM_DB_PORT || '3306',
  name: process.env.CHURCHCRM_DB_NAME || 'churchcrm',
  user: process.env.CHURCHCRM_DB_USER || 'churchcrm',
  password: process.env.CHURCHCRM_DB_PASSWORD || 'changeme',
};

export const ADMIN_USERNAME = process.env.CHURCHCRM_TEST_USERNAME || 'admin';

// Password on a fresh install, before the forced first-login change.
export const ADMIN_INITIAL_PASSWORD = process.env.CHURCHCRM_TEST_PASSWORD || 'changeme';

// Password global-setup changes the admin account to. The instance is a
// throwaway fresh install every run, so this does not need to be a secret —
// it only needs to satisfy the app's password policy.
export const ADMIN_WORKING_PASSWORD = 'MarketingVisuals!2026';

// The very first admin account, created by the setup wizard itself before
// the demo importer ever runs — see bootstrap.setup.ts for the full note.
// Locale-session.ts needs this id to call the ui.locale setting API for the
// same account the storageState session belongs to.
export const ADMIN_PERSON_ID = 1;

export const CHURCH_NAME = 'Grace Community Church';

export const SEED_VERSION = 'marketing-basic-v1';

// ---------------------------------------------------------------------------
// Locale (CRM #10048 — multi-language marketing screenshots)
// ---------------------------------------------------------------------------

/**
 * Marketing-facing locale code for this run — the value written into
 * manifest entries and used to select the artifact output directory
 * (screenshots/{locale}/{device}/{name}.png). Defaults to 'en' for a plain
 * `npm run marketing:screenshots` invocation so nothing changes for anyone
 * not opted into the multi-locale pipeline.
 *
 * Set via CHURCHCRM_LOCALE — see scripts/capture-all-locales.sh, which
 * loops this across all 8 codes in LOCALE_MAP for a full #10048 run.
 */
export const LOCALE = process.env.CHURCHCRM_LOCALE || 'en';

/**
 * Maps each #10048 marketing locale code to the actual `ui.locale` value
 * ChurchCRM's user-settings API expects (see src/locale/locales.json).
 * ChurchCRM has no bare 'es'/'pt'/'zh' entries — only regional variants —
 * so these are explicit product decisions, not derived automatically:
 *
 *   es -> es_ES  (Spain, not es_MX/es_AR/es_CO/es_SV)
 *   pt -> pt_PT  (Portugal, not pt_BR)
 *   zh -> zh_CN  (Simplified — matches #10048's "中文 (Simplified Chinese)")
 *
 * Confirm 'en_US' is the exact key in locales.json before first run — if
 * ChurchCRM's default/untouched locale differs, adjust here.
 */
export const LOCALE_MAP: Record<string, string> = {
  en: 'en_US',
  es: 'es_ES',
  pt: 'pt_PT',
  zh: 'zh_CN',
  fr: 'fr_FR',
  ru: 'ru_RU',
  de: 'de_DE',
  ar: 'ar_EG',
};

/** The ui.locale value to request for the current LOCALE, or undefined for
 *  an unrecognized/unmapped code (locale-session.ts treats that as "leave
 *  the app on its default locale — don't call the setting API"). */
export const LOCALE_UI_VALUE: string | undefined = LOCALE_MAP[LOCALE];
