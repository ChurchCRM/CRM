import { defineConfig } from 'cypress'
import { setupCommonNodeEvents } from './_shared'

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
  env: {},
  retries: 0,
  numTestsKeptInMemory: 0,
  e2e: {
    setupNodeEvents(on, config) {
      return setupCommonNodeEvents(on, config);
    },
    // default baseUrl can be overridden by CYPRESS_BASE_URL env var
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost/',
    specPattern: [
      'cypress/e2e/api/**/*.spec.js',
      'cypress/e2e/ui/**/*.spec.js'
    ]
  }
})
