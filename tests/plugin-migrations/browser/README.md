# Plugin migration browser coverage

This dedicated Cypress suite uses the real CRM login, page shell, management
JavaScript and API routes. It supplements the ordinary repository Cypress suite;
it does not replace root/subdirectory, API, admin or fresh-install coverage.

`fixture.php` is a CLI-only fixture loader outside the public document root. It
requires explicit `PLUGIN_MIGRATION_BROWSER_TEST=1`, a loopback database host,
the exact database `churchcrm_plugin_migrations_browser_test`, and an existing
real Admin login session for state changes. It refuses non-fixture Config.php
files and unowned plugin directories. Initialization drops/reimports only that
fixed disposable database using the repository's synthetic Cypress seed.

The fixture installs a synthetic reviewed catalog and matching extraction
provenance in that test login session. The production registry, permission,
fingerprint, migration, CSRF and lifecycle checks run normally. There is no test
mode, fixture endpoint or approval exception in production PHP. Do not expose
the development server or configure these fixtures on a real church installation.

The five tests cover ordinary core enable/disable, approved first enable with
CSRF, pending upgrades, actionable DDL failure with blocked replay, and confirmed
uninstall preserving rows/history. The tests query durable state through the
guarded CLI helper after real browser actions. The development-server router is
only a loopback adapter for the actual CRM module entry points.

The workflow `plugin-migration-browser.yml` records the exact commit and PHP
version, builds real assets, starts a disposable MariaDB service and runs:

```sh
npx cypress run --config-file cypress/configs/plugin-migrations.config.ts --headless --browser electron
```

For local use, build locked Composer/npm dependencies and CRM assets, create the
exact empty database above, and provide `PLUGIN_BROWSER_DB_HOST` (loopback),
`PLUGIN_BROWSER_DB_PORT`, `PLUGIN_BROWSER_DB_USER`, `PLUGIN_BROWSER_DB_PASSWORD`,
`PLUGIN_BROWSER_SESSION_DIR` (a dedicated existing directory), and
`CYPRESS_BASE_URL=http://127.0.0.1:8097/`. Set the explicit fixture flag, run
`php tests/plugin-migrations/browser/fixture.php initialize`, then start
`php -S 127.0.0.1:8097 -t src tests/plugin-migrations/browser/router.php` before
Cypress. `PLUGIN_BROWSER_PHP` optionally selects a local PHP executable for CLI
fixture tasks. Initialization refuses to overwrite an ordinary configuration.

The existing Build, Test and Package workflow also accepts this proposal's
feature-branch push so the fork can run the unchanged full Cypress matrix.
Permissions, required checks, draft status and release-upload conditions are not
broadened; release upload still requires a push to master. A configured or queued
job is not a test result; use the actual run/job evidence in PR #9672.
