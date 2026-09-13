# Developing ChurchCRM

This guide is the starting point for contributors changing the ChurchCRM core application. User and administrator instructions belong on [docs.churchcrm.io](https://docs.churchcrm.io).

## Recommended setup

GitHub Codespaces and VS Code Dev Containers provide the supported development toolchain: PHP 8.4, Node.js 24, Composer, Docker, and the required extensions.

### GitHub Codespaces

1. Open the repository's **Code** menu.
2. Select **Codespaces** and create a codespace from your working branch.
3. Wait for the setup process to finish.
4. Start ChurchCRM:

   ```bash
   npm run docker:dev:start
   ```

5. Open `http://localhost` and sign in with `admin` / `changeme`.

### VS Code Dev Containers

1. Install Docker and the VS Code Dev Containers extension.
2. Clone this repository and open it in VS Code.
3. Select **Reopen in Container** when prompted.
4. After setup finishes, run `npm run docker:dev:start`.

The container setup installs dependencies and builds the application automatically. See [`.devcontainer/README.md`](.devcontainer/README.md) for configuration and troubleshooting.

## Services

After `npm run docker:dev:start`, the development environment provides:

| Service | URL | Purpose |
|---|---|---|
| ChurchCRM | `http://localhost` | Application under development |
| Mailpit | `http://localhost:8025` | Captured test email |
| Adminer | `http://localhost:8088` | Database inspection |

The default ChurchCRM login is `admin` / `changeme`. Never use these credentials in a real deployment.

## Typical development loop

```bash
# Start the application
npm run docker:dev:start

# Rebuild PHP and frontend assets
npm run build

# Run static checks
npm run lint

# Run the complete Cypress suite
npm run test

# Stop the environment while keeping its volumes
npm run docker:dev:stop
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
| `npm run docker:dev:start` | Start the development services |
| `npm run docker:dev:logs` | Follow development-service logs |
| `npm run docker:dev:login:web` | Open a shell in the web container |
| `npm run docker:dev:reset:db` | Restore the development database seed |
| `npm run lint` | Run the repository's static checks |
| `npm run test` | Run all Cypress tests |

`package.json` is the source of truth for available commands. Do not copy the complete script list into documentation.

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
- Use Slim 4 patterns for new routes and pages.
- Use Tabler and Bootstrap 5 components for UI work.
- Wrap user-visible text with the project localization helpers.
- Add or update tests with every behavior change.
- Link pull requests to an open issue.
- Never commit secrets, production data, or real personal information.

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

Never edit generated Propel model files as the source of a schema change.

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
