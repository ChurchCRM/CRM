# Plugin migration integration tests

These tests execute the real core migration runner, ledger SQL, lifecycle,
package extractor, generated fixture models and Slim enable endpoint against a
disposable MySQL/MariaDB database. They need PHP 8.4+, PDO MySQL, ZipArchive and
the normal ChurchCRM Composer dependencies. No browser or production CRM config
is required. No test plugin is added to the remote registry.

Create a disposable database named **churchcrm_plugin_migrations_test**. The
runner checks the actual selected database before modifying anything. It creates
and resets its own ledger/config/fixture tables on each run, and removes its
temporary package directory automatically. Never point it at real application data.

The suite also imports the **full core Install.sql**, backs up every table in
the disposable database, and drops/reimports the entire schema during recovery.
The fixed database-name guard is mandatory, including for subprocess workers.

From the repository root (POSIX shell):

```sh
cd src
composer install --no-interaction --prefer-dist
cd ..
export PLUGIN_MIGRATION_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=churchcrm_plugin_migrations_test;charset=utf8mb4'
export PLUGIN_MIGRATION_TEST_USER='root'
export PLUGIN_MIGRATION_TEST_PASSWORD='your-disposable-db-password'
php tests/plugin-migrations/run.php
php scripts/plugin-scan.php tests/fixtures/plugins/community/migration-example
```

In PowerShell use `$env:PLUGIN_MIGRATION_TEST_DSN = '...'` and analogous user and
password assignments, then the same PHP commands. The tests use subprocesses for
a real PHP restart and concurrent connection locks. A Windows host without
symlink privileges reports that case as unavailable; Linux CI must exercise it.
Existing PHP 8.4 User hook deprecations are suppressed by the isolated bootstrap.

Fixture models are already distributed in `src/Model`; the test does not generate
them. Core model generation is the normal application development build step.
To intentionally regenerate the fixture after a fixture schema change:

```sh
php src/vendor/bin/propel --config-dir=tests/fixtures/plugins/community/migration-example/orm model:build
```

The two example SQL files model versions 1.0.0 and 1.1.0. Tests append the second
declaration to simulate a new approved release. The fixture uninstall hook is
deliberately destructive solely to test retention; it is not authoring guidance.

The GitHub Actions workflow `plugin-migrations.yml` is configured for MySQL 8.0 and
MariaDB 10.11 on Linux/PHP 8.4. It triggers on relevant pull requests, master and
feature/community-plugin-migrations pushes, and workflow_dispatch. It uses only
read permissions and disposable service credentials, with no upstream secrets.
It also runs the read-only security scanner and PHPStan for the new
core migration classes. The scanner currently reports two direct-SQL warnings
from generated Base model/query prepared statements. Review these against the
reproducible Perpl output; do not suppress all findings in generated directories.
The fixture's application code uses only ORM models/queries.

Both dependency modes run the same suite: first the development build, then
Composer `--no-dev --no-scripts --no-plugins`, with the Perpl Generator/Command directory
moved out of the runtime. `PLUGIN_MIGRATION_TEST_PRODUCTION=1` verifies that the
generator command, PHPStan and Rector directories are absent. Core models are built once
as part of preparing the application; shipped plugin models are never generated
by the tests or during plugin installation.

Perpl 2.6 runtime imports `Generator/Model/PropelTypes.php`. Removing the whole
Generator directory is invalid: it breaks core queries as well as plugin ones.
The production test removes only Generator/Command, retaining required runtime
types. This is test isolation, not advice to modify installed vendor packages.

The test-only `FaultConnection.php` pauses real DDL at pipe barriers. The parent
kills the worker (SIGKILL on Linux; TerminateProcess on Windows), reconnects and
verifies the attempt state. Tests cover before DDL, after DDL before completion,
after completion before activation, and between ordered resources. They also
test lock exclusion across DDL implicit commits, lock release on process death,
per-database/per-plugin isolation, route withholding, hook cleanup and full
backup recovery. No production fault-injection API or automatic repair exists.

Use the [recovery guide](../../docs/plugins/migration-recovery.md) for operational
assumptions and the [handoff](../../docs/plugins/migration-implementation-handoff.md)
for executed results. Workflow configuration alone is not evidence of a CI pass.
