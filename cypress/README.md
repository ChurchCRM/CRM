Cypress tests for ChurchCRM

Overview
- `cypress/e2e/` — test specs; group by feature (e.g., `auth/`, `family/`, `donations/`).
- `cypress/fixtures/` — static test data (move from `cypress/data/`).
- `cypress/support/` — global support files and custom commands.
- `cypress/configs/` — environment-specific config files (e.g., `new-system.config.ts`, `docker.config.ts`).
- `cypress/videos/`, `cypress/screenshots/`, `cypress/downloads/` — runtime artifacts (gitignored).

Running tests (recommended)
- Open interactive runner (local):
  ```bash
  npm run test:open
  ```
- Run headless (local):
  ```bash
  npm run test
  ```
- Run specific tests:
  ```bash
  npm run test:api     # API tests only
  npm run test:ui      # UI tests only
  npm run test:new-system  # Setup wizard tests
  ```

Quick migration notes
- Move `cypress/data/` → `cypress/fixtures/` and update usages of `cy.fixture()` accordingly.
- Consolidate Docker/CI variants under `cypress/configs/` (use `--config-file` to point to them).
- Remove the legacy `cypress.json` file in favor of `cypress.config.ts` (Cypress v10+).

Best practices
- Group specs by feature and add tags (e.g., `@smoke`) to allow fast subset runs.
- Keep secrets out of repo; use `cypress.env.json` or CI environment variables.
- Ensure `cypress/videos/`, `cypress/screenshots/`, and `cypress/downloads/` are in `.gitignore`.

Local setup (recommended)
- Create a `cypress.env.json` in your local checkout (gitignored) to store non-secret test values:

```json
{
  "admin.api.key": "REPLACE_ME",
  "user.api.key": "REPLACE_ME",
  "nofinance.api.key": "REPLACE_ME"
}
```

- Override `baseUrl` for local runs by setting the `CYPRESS_BASE_URL` environment variable or by passing `--config` on the CLI. Examples:

```bash
# temporary override via env var (e.g., for subdirectory installation)
CYPRESS_BASE_URL=http://localhost:8080/churchcrm/ npm run test

# or pass via CLI (explicit config-file)
npx cypress run --config baseUrl=http://localhost:8080/churchcrm/ --config-file cypress/configs/docker.config.ts
```

- Common developer commands:
  - `npm run test` — run full e2e suite headless (api + ui)
  - `npm run test:open` — open interactive test runner
  - `npm run test:api` — run API tests only
  - `npm run test:ui` — run UI tests only
  - `npm run test:new-system` — run setup wizard tests

- Security: ensure `cypress.env.json` is added to your `.gitignore` and never committed.

Contact
If you need help migrating or reorganizing tests, open an issue or contact the QA maintainer.
