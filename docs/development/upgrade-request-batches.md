# Database upgrades in separate requests

The web upgrader executes one SQL or PHP file per request. A successful file is
checkpointed before the next request starts. The version advances only when all
files in that version step finish. `upgrade.json` keeps its existing string format.

Bootstrap sends incomplete upgrades to `/external/system/db-upgrade`. The page
returns HTTP 200 with a one-second Refresh header and a Continue link. Each refresh
starts a new request, avoiding both cumulative PHP execution time and browser
limits on consecutive HTTP redirects. URLs preserve the installation prefix.
Application initialization and encryption-key creation wait until the schema is
current. Completion sends the browser through logout.

Code deployment returns `databaseUpgradePending` to the wizard, which navigates to
the continuation page. An older release manager still loaded during deployment
may call `upgradeDatabaseVersion()` without arguments. In HTTP, that call defers
all database work. Bootstrap explicitly passes `true` to execute one file. CLI
callers without arguments retain synchronous execution; explicit `false` also
requests synchronous execution.

`database_upgrade_progress` records the target version, script path, started time,
and `started` or `complete` status. The upgrader creates this internal journal
before migrations, independently of application ORM tables: older `config_cfg`
schemas have mandatory columns and primary keys that the current model cannot
insert safely. A database-scoped advisory lock protects each batch. Progress
survives session expiration and requests from different clients.

An interrupted file remains `started` and stops further execution with its name
in the server log. An administrator must repair the migration and its checkpoint,
or restore a database backup. A concurrent request waits on the continuation page
instead of recording a permanent failure. Failure pages do not automatically retry.

This limits cumulative request time. A single long-running file can still time
out, and a failure between applying SQL and recording completion still needs
administrator recovery. Automatic idempotent retries, retry metadata, and splitting
historical scripts remain deferred under #10943 / #10945. The two column/index
ALTERs in `7.8.1.sql` are guarded to heal the reported partial email ALTER; this does
not make that entire historical file idempotent.

## Verification

On a disposable MySQL or MariaDB server with generated ORM models available:

```sh
git show master:src/mysql/upgrade/7.8.1.sql > /tmp/original-7.8.1.sql
git show 7.8.0:src/mysql/install/Install.sql > /tmp/Install-7.8.0.sql
UPGRADE_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
UPGRADE_TEST_USER=root UPGRADE_TEST_PASSWORD=test-password \
UPGRADE_TEST_BASELINE=/tmp/Install-7.8.0.sql \
UPGRADE_TEST_ORIGINAL=/tmp/original-7.8.1.sql \
php scripts/test-upgrade-request-batches.php
```

`scripts/test-upgrade-request-http.php` uses the same connection variables and
requires a disposable checkout without `src/Include/Config.php`. It temporarily
writes that configuration and replaces `upgrade.json`, then restores both. It
starts PHP's development server on port 8097 and verifies root and subdirectory
continuation through the real Bootstrapper and Slim route. Use a nonempty database
password because Config.php validation requires one.

Before considering the approach tested for release, exercise the seeded Cypress
upgrade wizard, a real release archive upgrade, the ChurchInfo and 6.0.0 restore
paths, and a large dataset on supported PHP 8.4+. Keep #10943 open until that review.
