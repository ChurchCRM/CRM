import { defineConfig } from 'cypress'
import { verifyDownloadTasks } from 'cy-verify-downloads';

import base from './base.config'

/**
 * Cypress config for the admin UI test suite (cypress/e2e/ui-admin/).
 *
 * Admin specs are the heaviest in the project (system-upgrade, church-info,
 * user-editor, etc.) and are split from the main UI suite so CI can run them
 * in a dedicated parallel matrix leg, shortening the wall-clock time of the
 * main UI leg.
 *
 * npm run test:ui-admin
 * CI job: test-type.name == "admin-ui" in test-root / test-subdir matrices
 */
export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  videosFolder: 'cypress/videos',
  screenshotOnRunFailure: true,
  screenshotsFolder: 'cypress/screenshots',
  pageLoadTimeout: 30000,
  defaultCommandTimeout: 5000,
  requestTimeout: 15000,
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    'admin.api.key': 'ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM',
    'user.api.key': 'JZJApQ9XOnF7nvupWZlTWBRrqMtHE9eNcWBTUzEWGqL4Sdqp6C',
    'nofinance.api.key': 'M_5K4ZWTdBTmMOTGTfLWCmXFbETgHNG6_6FNZXJJulicn_WweBjm',
    'nofundraiser.api.key': 'financeNoFundraiserApiKeyForTesting12345',
    'nofundraiser.username': 'finance.nofundraiser',
    'nofundraiser.password': 'changeme',
    'selfedit.api.key': 'amandaBlackEditSelfOnlyApiKey12345678901',
    'selfedit.plus.notes.api.key': 'editSelfPlusNotesApiKeyForTesting12345678901',
    'plainauth.api.key': 'plainAuthReadOnlyApiKeyForTesting12345678901',
    'limited.api.key': 'limitedUserApiKeyForTesting123456789012345678',
    'editrecords.api.key': 'judithMatthewsEditRecordsNoNotesApiKey1234',
    'menuoptions.api.key': 'menuOptionsOnlyApiKeyForTesting12345678901',
    'admin.username': 'admin',
    'admin.password': 'changeme',
    'standard.username': 'tony.wade@example.com',
    'standard.password': 'basicjoe',
    'nofinance.username': 'judith.matthews@example.com',
    'nofinance.password': 'noMoney$',
  },
  retries: 0,
  numTestsKeptInMemory: 0,
  e2e: {
    ...base.e2e,
    // Admin UI specs live here; the main UI suite (docker.config.ts) excludes
    // this directory so both suites can run as parallel CI matrix legs.
    specPattern: ['cypress/e2e/ui-admin/**/*.spec.js'],
    setupNodeEvents(on, config) {
      const installLogsPrinter = require('cypress-terminal-report/src/installLogsPrinter');
      installLogsPrinter(on, {
        outputRoot: 'cypress/logs',
        outputTarget: {
          'cypress-terminal-report.txt': 'txt',
          'cypress-terminal-report.json': 'json'
        },
        printLogsToConsole: 'onFail',
        printLogsToFile: 'always'
      });
      on('task', verifyDownloadTasks);
      on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome') {
          launchOptions.args.push('--disable-dev-shm-usage');
        }
        return launchOptions;
      });
      return config;
    },
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost/',
  },
})
