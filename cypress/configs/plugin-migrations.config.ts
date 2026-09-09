import { defineConfig } from 'cypress';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import standard from './docker.config';

export default defineConfig({
  ...standard,
  e2e: {
    ...standard.e2e,
    specPattern: ['tests/plugin-migrations/browser/management.spec.js'],
    setupNodeEvents(on, config) {
      standard.e2e?.setupNodeEvents?.(on, config);
      if (process.env.PLUGIN_MIGRATION_BROWSER_TEST !== '1'
          || !['127.0.0.1', 'localhost'].includes(new URL(config.baseUrl!).hostname)) {
        throw new Error('Plugin browser fixtures require explicit disposable mode and a loopback URL.');
      }
      on('task', {
        pluginMigrationFixture({ action, sessionId }) {
          const result = execFileSync(
            process.env.PLUGIN_BROWSER_PHP || 'php',
            ['-d', 'display_errors=stderr', path.join(config.projectRoot, 'tests/plugin-migrations/browser/fixture.php'), action, sessionId],
            { cwd: config.projectRoot, encoding: 'utf8', env: process.env, timeout: 30000, windowsHide: true },
          );
          return JSON.parse(result.trim());
        },
      });
      return config;
    },
  },
});
