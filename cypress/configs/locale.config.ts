import { defineConfig } from 'cypress'
import { setupCommonNodeEvents } from './_shared'

export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  videosFolder: 'cypress/videos',
  screenshotOnRunFailure: true,
  screenshotsFolder: 'cypress/screenshots',
  pageLoadTimeout: 30000,
  defaultCommandTimeout: 15000,
  requestTimeout: 30000,
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    // Shared API keys (used by makePrivateAPICall in setupLocaleAdminSession)
    'admin.api.key': 'ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM',
    // Locale-admin dedicated test user (per_ID 906, seeded in cypress/data/seed.sql)
    'locale.admin.id': 906,
    'locale.admin.username': 'locale-admin@churchcrm.test',
    'locale.admin.password': 'changeme',
    'locale.admin.api.key': 'localeAdminApiKeyForTesting1234567890',
    // Default tier: smoke (15 high-risk locales). Override with --env LOCALE_TIER=full
    'LOCALE_TIER': 'smoke',
  },
  retries: 0,
  numTestsKeptInMemory: 0,
  e2e: {
    setupNodeEvents(on, config) {
      return setupCommonNodeEvents(on, config);
    },
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost/',
    specPattern: ['cypress/e2e/locale/**/*.spec.ts'],
  },
})
