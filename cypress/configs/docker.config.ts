import { defineConfig } from 'cypress'
import { verifyDownloadTasks } from 'cy-verify-downloads';

import base from './base.config'
export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  videosFolder: 'cypress/videos',
  screenshotOnRunFailure: true,
  screenshotsFolder: 'cypress/screenshots',
  pageLoadTimeout: 30000,
  defaultCommandTimeout: 30000,
  requestTimeout: 15000,
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    'admin.api.key': 'ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM',
    'user.api.key': 'JZJApQ9XOnF7nvupWZlTWBRrqMtHE9eNcWBTUzEWGqL4Sdqp6C',
    'nofinance.api.key': 'M_5K4ZWTdBTmMOTGTfLWCmXFbETgHNG6_6FNZXJJulicn_WweBjm',
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
