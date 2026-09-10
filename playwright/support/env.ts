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

export const CHURCH_NAME = 'Grace Community Church';

export const SEED_VERSION = 'marketing-basic-v1';

export const LOCALE = 'en';
