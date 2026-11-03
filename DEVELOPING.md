# Developing ChurchCRM

This guide is the starting point for contributors changing the ChurchCRM core application. User and administrator instructions belong on [docs.churchcrm.io](https://docs.churchcrm.io).

## Shared environment setup

Humans, CI, and coding agents use the same commands. Run setup while network
access is available, then retain dependencies and images for offline work.
Setup does not change application behavior or install real church data.

### Choose a setup path

| Path | Host prerequisites | Command |
| --- | --- | --- |
| Native build and PHP tests | Git, Bash, Node 24/npm, PHP 8.4+, Composer | `bash scripts/setup-dev.sh` |
| Container build and PHP tests | Git, Bash, Node 24+, running Docker Engine and Compose v2 | `bash scripts/setup-dev.sh --docker` |
| Native build and integration stack | Native prerequisites plus Docker Engine/Compose v2, curl, Cypress OS libraries | `bash scripts/setup-dev.sh --integration` |

The container path provisions PHP, Composer, and Node using the existing
[docker/Dockerfile.churchcrm-apache-php8](docker/Dockerfile.churchcrm-apache-php8)
and [dev Compose stack](docker/docker-compose.dev.yaml). It leaves the dev stack
running. Run its tools inside the container (see below). Native setup uses the
host's installed runtimes; it never changes OS repositories or uses sudo to
replace them. Missing prerequisites stop setup with a diagnostic.

Native setup runs locked `npm ci`, the full build (Composer dependencies, ORM,
frontend, signatures), locked PHP test tooling, PHP readiness checks, and lint. It explicitly defers the
Cypress download to integration setup. Integration setup installs Cypress,
retries a failed download once with `--force`, verifies the executable/OS
libraries, builds/pulls the existing test images, starts services, and checks
readiness. It fails if any step fails. Install browser OS libraries through your
OS package manager before integration setup; the script does not elevate privileges.

Use a disposable checkout. Setup's Docker paths write `src/Include/Config.php`
using [docker/Config.php](docker/Config.php). The local placeholders in
[docker/.env](docker/.env) are database `churchcrm`, user `churchcrm`, password
`changeme`, host `database:3306` inside Docker. They are development fixtures.

PHP requirements come from [src/composer.json](src/composer.json): PDO, bcmath,
curl, fileinfo, filter, gd, gettext, iconv, mbstring, mysqli, session, zip, zlib.
The [installer](src/ChurchCRM/Service/AppIntegrityService.php) also checks UTF-8
PCRE, Phar, SimpleXML, GD functions, rewriting, and writable Include/Images
(including Person/Family) directories. The image supplies pdo_mysql for the ORM,
XML, exif, intl, and OPcache too. Check the web user's permissions separately
from CLI PHP. Versions are defined by `.nvmrc`, `package.json`, and
`src/composer.json`; CI uses PHP 8.4.

### Check readiness without installing anything

These checks run before application tests and work without internet access:

```bash
npm run env:check -- static
npm run env:check -- php
npm run env:check -- integration
npm run env:check -- integration --services
```

| Mode | Checks |
| --- | --- |
| `static` (default) | Node version, Git, PHP version, Composer, Biome and TypeScript executables |
| `php` | Static checks plus required extensions, application autoloader, PHP test runner, both Composer projects' platform requirements |
| `integration` | PHP checks plus Docker daemon, Compose, Cypress verification, built frontend and signatures |
| `integration --services` | Also running test services, database health, HTTP login page |

Every failed check prints a remedy and exits nonzero. Passing means these
prerequisites are available, not that tests pass or dependency caches match a
changed lockfile. After changing lockfiles, rerun setup online. Use `npm run lint`,
`npm run build:php:validate`, `npm run test:php`, and focused Cypress specs for
verification. Fast PHP tests need both vendor trees and no running stack.
PHP/Composer are needed for full lint, which validates both Composer locks and runs
the fast PHP suite. Package layout, focused commands and isolated database/HTTP
tests are documented in [tests/php/README.md](tests/php/README.md).

For the container path, run the same checks and tests inside the dev image:

```bash
docker compose -f docker/docker-compose.dev.yaml exec -T webserver bash -c \
  'source /root/.nvm/nvm.sh && cd /home/ChurchCRM && npm run env:check -- php && npm run test:php'
```

Use the native integration path for host-side Cypress. The dev and test stacks
share default ports; stop one before starting the other. `--services` checks the
test Compose project, not the dev stack. Docker's CLI alone is insufficient;
the executing user must have access to a running daemon.

### Services and offline sessions

The existing test stack runs Apache with rewriting, MariaDB 10.11, and Mailpit v1.
On a fresh database it loads `src/mysql/install/Install.sql`, then
`cypress/data/seed.sql`, and waits for DB health before starting the webserver.
Open `http://localhost` with local test login `admin` / `changeme`.
Mailpit captures SMTP at `mailserver:1025` and exposes its inbox on port 8025.
Host-side Cypress uses database `127.0.0.1:3306` by default. See
[docker/README.md](docker/README.md) for profiles and port overrides;
`CYPRESS_BASE_URL` selects the HTTP target when using a non-default port.

Keep `node_modules/` (including dev/optional packages), `src/vendor/`, built
assets/signatures, Cypress's binary cache, and Docker images between setup and
offline work. Repeat readiness checks after resuming; services may need restarting
with `npm run docker:test:start`. Lockfile/image/runtime changes can require a
new online setup. No readiness check fetches missing packages.

`npm run docker:test:reset:db` destroys and recreates only the disposable fixture
database. Stop with `npm run docker:test:stop`; `docker:test:down` removes volumes.
For Playwright captures, use the separate worktree/project in
[marketing-visuals-pipeline.md](.agents/skills/churchcrm/marketing-visuals-pipeline.md)
and prepare Chromium plus OS libraries online with `npm run marketing:install`.

## Services

After `npm run docker:test:start`, the test environment provides:

| Service | URL | Purpose |
|---|---|---|
| ChurchCRM | `http://localhost` | Application under development |
| Mailpit | `http://localhost:8025` | Captured test email |

The default ChurchCRM login is `admin` / `changeme`. Change it in any real deployment.

## Typical development loop

```bash
# Start the test environment
npm run docker:test:start

# Rebuild PHP and frontend assets
npm run build

# Run static checks
npm run lint

# Run the complete Cypress suite
npm run test

# Stop the test environment while keeping its volumes
npm run docker:test:stop
```

PHP changes are bind-mounted and normally require only a browser refresh. After changing JavaScript, TypeScript, or CSS, run `npm run build:frontend`.

### Focused tests

```bash
npm run test:api
npm run test:ui
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec "cypress/e2e/path/to/example.spec.js"
```

Use the smallest relevant test while developing, then run the broader required checks before opening a pull request. See [`cypress/README.md`](cypress/README.md) for test organization and [`docker/README.md`](docker/README.md) for the test and CI container profiles.

## Useful commands

| Command | Purpose |
|---|---|
| `npm run build` | Build PHP dependencies and frontend assets |
| `npm run build:php` | Validate PHP and install Composer dependencies |
| `npm run build:frontend` | Build JavaScript and CSS assets |
| `npm run docker:test:start` | Start the local test services |
| `npm run docker:test:logs` | Follow test-service logs |
| `npm run docker:test:login:web` | Open a shell in the web container |
| `npm run docker:test:reset:db` | Rebuild the test database: `Install.sql`, then `cypress/data/seed.sql` |
| `npm run docker:dev:start` | Start the development stack (PHP/Composer/Node all available in-container) |
| `npm run docker:dev:build` | Run `composer install` + the full `npm run build` inside the development container |
| `npm run docker:dev:watch` | Run `webpack --watch` inside the development container |
| `npm run lint` | Run the repository's static checks |
| `npm run test` | Run all Cypress tests |

`package.json` is the source of truth for available commands.

## Repository map

| Path | Contents |
|---|---|
| `src/` | PHP application and runtime assets |
| `src/ChurchCRM/` | Domain models, services, utilities, and generated Propel classes |
| `src/api/` | API application and routes |
| `src/v2/` | MVC routes and templates |
| `orm/schema.xml` | Propel database schema source |
| `cypress/e2e/` | End-to-end API and UI tests |
| `docker/` | Development, test, and CI container definitions |
| `.agents/skills/` | Task-specific instructions for coding agents |

## Development rules

- Use Propel ORM for application database access. Schema changes start in `orm/schema.xml` and require a versioned migration.
- Put reusable business logic in service classes rather than route handlers or templates.
- Use Slim patterns for new routes and pages.
- Use Tabler and Bootstrap 5 components for UI work.
- Wrap user-visible text with the project localization helpers.
- Add or update tests with every behavior change.
- Link pull requests to an open issue.
- Keep secrets, production data, and real personal information out of commits.

Detailed, task-specific guidance is indexed in [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md). Load only the guidance relevant to the work being performed.

## Database changes

1. Update `orm/schema.xml`.
2. Add the corresponding versioned migration under `src/mysql/upgrade/` and register it in `src/mysql/upgrade.json`.
3. Regenerate Propel models when required:

   ```bash
   npm run build:orm
   ```

4. Update `src/mysql/install/Install.sql`. `cypress/data/seed.sql` holds test data only and changes only when a spec needs a row (`.agents/skills/churchcrm/ci-test-infrastructure.md`).
5. Test both an existing installation upgrade and a fresh installation.

Make schema changes in `orm/schema.xml`; generated Propel model files are build output.

## Before opening a pull request

1. Confirm the pull request is linked to an issue.
2. Run `npm run lint`.
3. Run the build appropriate to the change.
4. Run the affected Cypress tests.
5. Review application and PHP logs for unexpected errors.
6. Update user documentation when behavior visible to users changes.
7. Complete the repository pull-request template.

Read [`CONTRIBUTING.md`](CONTRIBUTING.md) for contribution policy and community expectations.

## Getting help

- Use [GitHub Issues](https://github.com/ChurchCRM/CRM/issues) for confirmed bugs and planned work.
- Use [Discord](https://discord.gg/tuWyFzj3Nj) for contributor questions and collaboration.
- Use [docs.churchcrm.io](https://docs.churchcrm.io) for installation, administration, and product usage.
