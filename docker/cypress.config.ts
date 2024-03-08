import { defineConfig } from 'cypress'
import { verifyDownloadTasks } from 'cy-verify-downloads';

export default defineConfig({
  chromeWebSecurity: false,
  video: false,
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
  retries: 4,
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      on('task', verifyDownloadTasks);
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost/',
    specPattern: [
      'cypress/e2e/api/**/*.spec.js',
      'cypress/e2e/ui/**/*.spec.js',
      'cypress/e2e/xReset/**/*.spec.js'
    ]
  },
})
