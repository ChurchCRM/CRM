import { defineConfig } from 'cypress'
import base from './base.config'

export default defineConfig({
  ...base,
  env: {
    ...base.env,
    'db.host': 'database-new-system',
    'db.port': '3306',
    'db.name': 'churchcrm',
    'db.user': 'churchcrm',
    'db.password': 'changeme',
    'admin.username': 'admin',
    'admin.password': 'changeme',
    // Password set during forced-change on fresh install (Step 1 of the upgrade spec).
    // Must match the value used in 01-setup-and-restore.spec.js.
    'admin.new.password': 'AdminP@ss1234!',
  },
  pageLoadTimeout: 120000,
  defaultCommandTimeout: 60000,
  requestTimeout: 120000,
  responseTimeout: 120000,
  e2e: {
    ...base.e2e,
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:8081/',
    specPattern: ['cypress/e2e/upgrade/**/*.spec.js']
  }
})
