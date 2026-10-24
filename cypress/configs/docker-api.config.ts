import { defineConfig } from 'cypress'

import docker from './docker.config'

// The api CI jobs shard cypress/e2e/api by recorded duration. docker.config.ts also matches
// ui/**, so splitting it would hand ui specs to the api shards.
export default defineConfig({
  ...docker,
  e2e: {
    ...docker.e2e,
    specPattern: ['cypress/e2e/api/**/*.spec.js'],
  },
})
