# PHP tests

Nette Tester runs focused PHP regressions against the application classes. Each
`*Test.php` file runs in its own process. Assertions, discovery, failures and JUnit
reports come from the framework.

## Setup and commands

Use the shared setup in [DEVELOPING.md](../../DEVELOPING.md). To prepare PHP tests
without the frontend or browsers:

```sh
npm run build:php
npm run test:php:install
npm run env:check -- php
npm run test:php
```

Both Composer projects use committed lockfiles. The application dependencies live
in `src/vendor`; the development-only test tooling lives in `tests/php/vendor`.
Install both while network access is available. Test commands never install
dependencies. Release packaging reads `src` and excludes the test project.

```sh
npm run test:php -- Service/Geocoding
npm run test:php -- View/Reports/ReportEscapingTest.php
npm run test:php -- --junit
npm run lint
```

Targets are relative to the selected suite. JUnit files are written to
`temp/php-tests`. Local lint includes the fast suite; the pre-push checkpoint and
PR CI run that same lint command. Nightly runs both suites on the supported
database engines and PHP runtimes.

## Packages and boundaries

Organize by suite, then layer and feature. Mirror the owning application package
where possible; use View for rendered templates and Controller for HTTP flows.

| Package | Responsibility |
|---------|----------------|
| `unit/Service/<feature>` | Business rules with controlled collaborators |
| `unit/Model` | Model behavior independent of persistence |
| `unit/View/<feature>` | Rendered HTML, escaping and template data |
| `unit/Data`, `unit/Dto`, `unit/Slim` | Existing application utility packages |
| `integration/Service/<feature>` | Database behavior and upgrades |
| `integration/Controller/<feature>` | Requests through bootstrap and routes |

Create a package when it has a test. Place a new `*Test.php` beside the behavior it
tests and require `tests/php/bootstrap.php` using `__DIR__`; discovery is automatic.
Use `Tester\Assert::same()` for exact values, specific exception assertions for
failure paths, and small local stand-ins for dependencies. Helpers shared by
several tests belong under `Support`.

Unit tests use the production autoloader without loading `Include/Config.php`.
They need no database, HTTP server or network. Control time explicitly where
possible. Files run serially to reduce contention during password timing checks.
Each file must perform assertions; let unexpected warnings and exceptions fail.

HTML escaping tests verify template output. PDF layout, fonts, page breaks and
browser workflows still require their own visual or end-to-end checks.

## Integration tests

Use a disposable MySQL or MariaDB server whose account can create and drop
databases. The runner makes a temporary application copy, omitting local
configuration and person/family uploads. Tests create randomly named databases,
start HTTP servers on available ports, and clean up resources in `finally`.
The runner removes its application copy even when a test fails.

```sh
PHP_TEST_DISPOSABLE_DB=1 \
UPGRADE_TEST_DSN='mysql:host=127.0.0.1;port=3306' \
UPGRADE_TEST_USER=root UPGRADE_TEST_PASSWORD=test-password \
npm run test:php:integration
```

Use `-- Service` or `-- Controller/System/UpgradeContinuationTest.php` to focus
the integration suite. Tests require `integration/bootstrap.php`, which checks
isolation and database settings before connecting. Missing prerequisites fail
the requested suite. Upgrade tests create small schemas and SQL/PHP scripts to
exercise checkpointing, interruption, concurrency and request continuation.
