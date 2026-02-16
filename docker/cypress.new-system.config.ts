import { defineConfig } from 'cypress'

// Configuration for new system setup wizard testing
// Tests the fresh install workflow without Config.php or existing database
export default defineConfig({
  chromeWebSecurity: false,
  video: false,
  videosFolder: 'cypress/videos',
  screenshotOnRunFailure: true,
  screenshotsFolder: 'cypress/screenshots',
  pageLoadTimeout: 120000,  // Longer timeout for install/restore operations
  defaultCommandTimeout: 60000,  // Longer for backup/restore commands
  requestTimeout: 120000,  // Longer for backup/restore API calls
  responseTimeout: 120000,  // Longer for backup/restore responses
  viewportHeight: 1080,
  viewportWidth: 1920,
  projectId: 'n4qnyb',
  env: {
    // Database connection settings for new system test
    'db.host': 'database-new-system',
    'db.port': '3306',
    'db.name': 'churchcrm',
    'db.user': 'churchcrm',
    'db.password': 'changeme',
    // Default admin credentials to use after install
    'admin.username': 'admin',
    'admin.password': 'changeme',
  },
  retries: 0,
  numTestsKeptInMemory: 0,
  e2e: {
    setupNodeEvents(on, config) {
      // cypress-terminal-report logs printer for CI debugging
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
    baseUrl: 'http://localhost:8081/',
    specPattern: [
      'cypress/e2e/new-system/**/*.spec.js'
    ]
  },
})
