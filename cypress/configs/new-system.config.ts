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
  },
  pageLoadTimeout: 120000,
  defaultCommandTimeout: 60000,
  requestTimeout: 120000,
  responseTimeout: 120000,
  e2e: {
    ...base.e2e,
    baseUrl: process.env.CYPRESS_BASE_URL || 'http://localhost:8081/',
    specPattern: ['cypress/e2e/new-system/**/*.spec.js']
  }
})
