import { defineConfig } from 'cypress'
import { verifyDownloadTasks } from 'cy-verify-downloads';

export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  screenshotOnRunFailure: false,
  pageLoadTimeout: 120000,
  defaultCommandTimeout: 60000,
  requestTimeout: 60000,
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    'admin.api.key': 'ajGwpy8Pdai22XDUpqjC5Ob04v0eG7EGgb4vz2bD2juT8YDmfM',
    'user.api.key': 'JZJApQ9XOnF7nvupWZlTWBRrqMtHE9eNcWBTUzEWGqL4Sdqp6C',
  },
  retries: 1,
  e2e: {
    setupNodeEvents(on, config) {
      // Install cypress-terminal-report logs collector
      const installLogsCollector = require('cypress-terminal-report/src/installLogsCollector');
      installLogsCollector(on, {
        outputRoot: 'cypress/logs'
      });
      
      // Register download verification tasks
      on('task', verifyDownloadTasks);
      
      // Modern Cypress 15.x event handling
      on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome') {
          launchOptions.args.push('--disable-dev-shm-usage');
        }
        return launchOptions;
      });

      // Return the config (required for Cypress 15.x)
      return config;
    },
    baseUrl: 'http://localhost/',
    specPattern: [
      'cypress/e2e/api/**/*.spec.js',
      'cypress/e2e/ui/**/*.spec.js',
      'cypress/e2e/xReset/**/*.spec.js'
    ]
  },
})
