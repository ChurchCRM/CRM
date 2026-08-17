import { defineConfig } from 'cypress'
import { verifyDownloadTasks } from 'cy-verify-downloads';

import base from './base.config'

/**
 * Cypress config for the sharded UI test suite (cypress/e2e/ui/).
 *
 * This config intentionally includes ONLY the ui/ spec pattern so that
 * cypress-split sees only ui specs when splitting. If docker.config.ts
 * (api + ui combined) were used instead, cypress-split would build its
 * shard from all 157 specs alphabetically — shard-0 would be mostly api
 * specs — and the --spec cypress/e2e/ui/**\/\*.spec.js CLI filter would then
 * find zero matching ui specs in that shard, causing exit-code 1.
 *
 * CI jobs: test-type.name == "ui-shard-{0,1,2}" in test-root / test-subdir
 * matrices (build-test-package.yml, nightly.yml).
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
    // UI specs only — cypress-split must see only ui/ here so every shard
    // receives a non-empty subset of ui specs. The api/ suite runs in a
    // separate non-sharded CI job using docker.config.ts.
    specPattern: ['cypress/e2e/ui/**/*.spec.js'],
    setupNodeEvents(on, config) {
      // Register cypress-split for UI spec sharding (SPLIT / SPLIT_INDEX env vars).
      // Guard: when SPLIT is unset the plugin is a no-op, so it's safe to
      // require unconditionally, but we skip registration to avoid debug noise.
      if (process.env.SPLIT) {
        const cypressSplit = require('cypress-split');
        cypressSplit(on, config);
      }
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
