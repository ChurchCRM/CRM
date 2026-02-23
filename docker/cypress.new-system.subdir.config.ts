import { defineConfig } from 'cypress'

// Configuration for new system setup wizard testing (subdirectory install)
export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  videosFolder: 'cypress/videos',
  screenshotOnRunFailure: true,
  screenshotsFolder: 'cypress/screenshots',
  pageLoadTimeout: 120000,
  defaultCommandTimeout: 60000,
  requestTimeout: 120000,
  responseTimeout: 120000,
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    'db.host': 'database-new-system',
    'db.port': '3306',
    'db.name': 'churchcrm',
    'db.user': 'churchcrm',
    'db.password': 'changeme',
    'admin.username': 'admin',
    'admin.password': 'changeme',
  },
  retries: 0,
  numTestsKeptInMemory: 0,
  e2e: {
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

      on('before:browser:launch', (browser, launchOptions) => {
        if (browser.name === 'chrome') {
          launchOptions.args.push('--disable-dev-shm-usage');
        }
        return launchOptions;
      });

      return config;
    },
    baseUrl: 'http://localhost:8081/churchcrm/',
    specPattern: [
      'cypress/e2e/new-system/**/*.spec.js'
    ]
  },
})
