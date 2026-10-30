# ChurchCRM Development Guide

Loaded by Codex every session. Other agents should follow the same files.

## Community feedback

Feature Proposals and RFC Reviews Discussions: [`docs/decisions/0003-rfc-community-prioritization.md`](docs/decisions/0003-rfc-community-prioritization.md).

## Skills

Index: [`.agents/skills/churchcrm/SKILL.md`](.agents/skills/churchcrm/SKILL.md).
How to edit a skill: [`.agents/skills/churchcrm/skill-architecture.md`](.agents/skills/churchcrm/skill-architecture.md).
Load the card for the task. Do not load every skill.

| Task | Start |
|------|-------|
| Review a PR | `maintainer-review-gates.md` → `pr-review.md` |
| Address review comments | `.agents/skills/pr-review-fix.md` → `git-workflow.md` |
| Commit / PR | `git-workflow.md` |
| UI | `frontend-development.md` |
| i18n | `i18n-localization.md` |
| Security | `security-best-practices.md` → `authorization-security.md` |
| Plugin | `plugin-system.md` → `plugin-registry.md` |
| Tests | `cypress-testing.md` |
| API | `api-development.md` → `service-layer.md` |

Always-on:

@.agents/skills/churchcrm/code-standards.md

@.agents/skills/churchcrm/git-workflow.md

Keep these heading names (this file links them):
[Commit freely, push only on approval](.agents/skills/churchcrm/git-workflow.md),
[Mandatory Pre-Push Biome Check](.agents/skills/churchcrm/git-workflow.md),
[Strict vs Loose Comparisons](.agents/skills/churchcrm/code-standards.md).

## Environment

For a cold checkout, start with [DEVELOPING.md](DEVELOPING.md). Provision the
following during the Codex cloud **setup phase**, while internet access is
available; the agent phase must use installed dependencies and cached images.

### Runtimes and prerequisites

- Use PHP **8.4** for parity with CI; the supported minimum is **8.4+**
  ([src/composer.json](src/composer.json),
  [.github/workflows/build-test-package.yml](.github/workflows/build-test-package.yml)).
  Install Composer and Node.js **24** with npm ([.nvmrc](.nvmrc),
  [package.json](package.json)). PHP/Composer must be on the host PATH for the
  primary build; the dev-container alternative below supplies them in Docker.
- Composer requires `PDO`, `bcmath`, `curl`, `fileinfo`, `filter`, `gd`, `gettext`,
  `iconv`, `mbstring`, `mysqli`, `session`, `zip`, and `zlib`. The
  [installer checks](src/ChurchCRM/Service/AppIntegrityService.php) additionally
  require UTF-8 PCRE, Phar (`PharData`), SimpleXML (`SimpleXMLElement`), GD image
  functions, and URL rewriting. Enable `pdo_mysql` for the ORM. The existing
  [Apache Dockerfile](docker/Dockerfile.churchcrm-apache-php8) supplies these,
  plus `exif`, `intl`, OPcache, and XML support; reuse it for runtime parity.
  Run `composer --working-dir=src check-platform-reqs --no-dev` after installing
  dependencies to check the actual PHP runtime, not Composer's platform override.
- A running Docker Engine and **Compose v2** are needed for integration tests.
  The [test stack](docker/docker-compose.yaml) supplies Apache with `mod_rewrite`,
  **MariaDB 10.11**, and **Mailpit v1** (SMTP sink). No external database or SMTP
  account is needed. Git is required; see [DEVELOPING.md](DEVELOPING.md) for tools.
- The web process needs writable `src/Include/`, `src/Images/`,
  `src/Images/Family/`, and `src/Images/Person/` (installer filesystem checks).
  The Compose stacks mount `src/` as the document root and fixture photo folders
  as the Person/Family directories. Check permissions as the web user.

### Online setup and running an instance

Run from the repo root on a disposable development checkout with the tools above:

```bash
php --version
composer --version
node --version
docker info
docker compose version
npm ci
npm run build
composer --working-dir=src check-platform-reqs --no-dev
npx --no-install cypress install
npx --no-install cypress verify
npm run docker:test:start
docker compose -f docker/docker-compose.yaml --profile test ps
curl --fail --silent --show-error --retry 20 --retry-connrefused --retry-delay 2 \
  --output /dev/null http://localhost/session/begin
```

`npm ci` installs the locked npm dependencies, including dev/optional packages
needed by lint (Biome's platform binary) and Cypress. `npm run build` installs
locked production Composer packages into `src/vendor/` and generates ORM classes
in parallel with the frontend build, then generates signatures ([scripts/build.js](scripts/build.js),
[scripts/composer-install.js](scripts/composer-install.js)). Keep `node_modules/`,
`src/vendor/`, generated assets, and the Cypress binary cache for the agent phase.
Provision the browser's OS libraries during setup too; `cypress verify` must pass
there. Run `npm run lint` during setup to prove the offline tools are installed.

`docker:test:start` writes the local `src/Include/Config.php` from
[docker/Config.php](docker/Config.php), builds the test web image, and starts all
three services. Do this online so the web image and MariaDB/Mailpit images are
cached before the agent phase. A resumed agent must check `docker info`, restart
services if needed, and repeat the HTTP check; setup-time processes may not survive.
If Docker is unavailable, provision a Docker-capable task environment before
claiming integration verification; PHP/Node alone only cover static/build work.

The database initializes with `src/mysql/install/Install.sql` **then**
`cypress/data/seed.sql` on a fresh data directory. Compose waits for database
health before starting Apache. The local-only database placeholders are
`churchcrm` / `changeme`, database `churchcrm`, host `database:3306` inside Docker
([docker/.env](docker/.env)); host-side Cypress connects to `127.0.0.1:3306`.
Open `http://localhost` with test login `admin` / `changeme`; Mailpit is at
`http://localhost:8025` (SMTP `mailserver:1025` inside Docker).
Use only disposable fixtures. Reset them with `npm run docker:test:reset:db`.
Stop with `npm run docker:test:stop`; `npm run docker:test:down` removes volumes.

For an environment without host PHP/Composer, use the existing dev image:
`npm run docker:dev:start`, then `npm run docker:dev:build` (online). See
[DEVELOPING.md](DEVELOPING.md#building-without-a-local-phpcomposernodejs-toolchain).
It shares the default ports with the test stack, so run one stack at a time.

For Playwright work, load
[marketing-visuals-pipeline.md](.agents/skills/churchcrm/marketing-visuals-pipeline.md)
first: use its separate worktree and Compose project, and run
`npm run marketing:install` online to cache Chromium and its OS dependencies.
The pipeline removes `src/Include/Config.php`; keep it off the dev checkout.

### Verify before a PR

- Run **`npm run lint`** before pushing (the mandatory Biome checkpoint also
  runs typechecking and repository checks; [.githooks/pre-push](.githooks/pre-push)).
  Use `npx --no-install` for focused tool runs so an absent tool fails locally.
- Run the matching build: `npm run build:frontend` for JS/TS/CSS,
  `npm run build:php` for Composer dependencies, or `npm run build` for the full
  application. For PHP, run `npm run build:php:validate`; focused standalone
  checks are available through `npm run test:php` ([package.json](package.json)).
- For UI/behavior changes, reset the seeded test DB, then run the affected specs:
  `npx --no-install cypress run --config-file cypress/configs/docker.config.ts --spec "<actual-spec-path>"`.
  Follow the browser/device checks in [Tests](#tests) and the linked skill cards.
- Docs-only work needs no application build or Cypress run
  ([issue-readiness.md](docs/contributing/issue-readiness.md#write-for-the-human-first)).
  Report checks actually run and any environment blockers in the PR description.

## Updating a skill

If a durable rule is missing or wrong, edit the existing card. Replace the sentence.
Do not append `<!-- learned: -->` essays. Do not paste catalogs from `src/`.

Plugin registry URL: `CentralServices::PLUGIN_REGISTRY_URL` (External branch today).

## Issue and PR readiness

Before triaging or implementing an issue, read [`docs/contributing/issue-readiness.md`](docs/contributing/issue-readiness.md). Check existing issues/PRs first, size work by review burden and risk, use epics for independently meaningful outcomes or cross-subsystem work, and treat `good first issue` as a maintainer certification. Keep issue intake friendly and PRs focused. Human maintainers remain accountable for decisions and approvals.

## Session hygiene

- Confirm `git branch --show-current` before committing docs
- Do not land cross-cutting skill edits on an unrelated feature branch
- After a push that fixes review comments, resolve every thread it fixed before you report done: check each against the pushed code, reply with the SHA when unclear, leave unfixed threads open (`git-workflow.md`)
- Do not merge or close issues unless the maintainer answers yes to a direct question
- User-visible behavior: open a docs tracking issue on ChurchCRM/CRM. It does not block feature merge. Docs PRs wait for the release. See `docs/contributing/pr-review.md`
- UI that belongs on a marketing or docs page: one PR carries the code, the Cypress specs, and the Playwright capture (the `playwright/workflows/` spec, the `en` PNGs, and the `manifest.json` entries; `marketing-visuals-pipeline.md`). Run the pipeline in a git worktree on its own compose project, never on the dev checkout: it deletes `src/Include/Config.php`. Open a separate Playwright issue only when the capture cannot be taken yet
- Follow the human-first writing rules in [`docs/contributing/issue-readiness.md`](docs/contributing/issue-readiness.md): BLUF first, concise goals and outcomes, details only when they help a decision.
- Write the current state: positive instructions, no history, no negating comments, migrate a legacy page when you touch it (`skill-architecture.md`)
- Write little. Names and tests carry intent. Comment only to explain intent or a non-obvious constraint; do not narrate the code.
- If the PR author is the same GitHub user the agent is acting as, do not post a review comment. Fix the branch.

## Tests

Run the failing spec in isolation before committing test fixes. Before a push, run the specs the change touches locally and check UI changes in a browser at desktop, tablet, and phone widths; UI PRs carry screenshots (`git-workflow.md`, `cypress-testing.md`). Do not assume Cypress / Yasumi fixture shapes — read the source. `cy.visit()` paths start with `/`.
