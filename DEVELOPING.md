# Developing ChurchCRM

This guide is the starting point for contributors changing the ChurchCRM core application. User and administrator instructions belong on [docs.churchcrm.io](https://docs.churchcrm.io).

## Prerequisites

Before running any ChurchCRM build, test, or Docker command, install:

- PHP and Composer (version in `src/composer.json`)
- Node.js with npm (version in `package.json` `engines`, `.nvmrc`)
- Docker Desktop or Docker Engine with Docker Compose v2
- Git and Git LFS

Verify the tools before continuing:

```bash
php --version
composer --version
node --version
npm --version
docker --version
docker compose version
```

After cloning the repository, install the Node.js dependencies before using the npm scripts:

```bash
npm install
```

## Primary local setup

ChurchCRM maintainers develop with PHP, Composer, Node.js, and npm installed locally. Docker runs the application stack used for testing.

From the repository root:

```bash
npm install
npm run build
npm run docker:test:start
```

Open `http://localhost` and sign in with `admin` / `changeme`.

## Building Without a Local PHP/Composer/Node.js Toolchain

The development Docker image includes PHP, Composer, and Node/npm, so the host needs only `docker`, `docker compose`, and `npm` (to invoke the wrapper scripts below). Without `npm` on the host, use the raw `docker compose exec` command at the end of this section.

```bash
npm run docker:dev:start   # bring up the development stack (builds the image on first run)
npm run docker:dev:build   # composer install + the full `npm run build`, inside the container
npm run docker:dev:watch   # webpack --watch, inside the container, for an active edit session
```

These run `npm install && npm run build` from the primary setup inside the `webserver` container, against the repository bind-mounted at `/home/ChurchCRM`. Without `npm` on the host, run the equivalent directly:

```bash
docker compose -f docker/docker-compose.dev.yaml exec webserver bash -c \
  "source /root/.nvm/nvm.sh && cd /home/ChurchCRM && npm ci && npm run build"
```

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
| `npm run docker:test:reset:db` | Restore the test database seed |
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

4. Update test seed data when the schema changes.
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
