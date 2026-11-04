# Member sign-in integration testing

Use this workflow for `feature/member-sign-in` and [PR #11006](https://github.com/ChurchCRM/CRM/pull/11006).
The integration PR targets `master` so each pushed change runs the application CI.
Keep it draft while the member workflow is incomplete. Merge `master` and stacked
PRs into this branch with merge commits to preserve the stack's ancestry.

## 1. Prepare the branch and environment

Start with a clean checkout of `feature/member-sign-in`. Use disposable seeded
data; database reset replaces the test database. Follow [DEVELOPING.md](../../DEVELOPING.md)
for prerequisites and initial setup. Docker commands run from the main checkout;
agents obtain permission before switching that checkout for Docker testing.

```bash
git fetch origin
git switch feature/member-sign-in
git merge --ff-only origin/feature/member-sign-in
bash scripts/dev/setup-dev.sh --integration
npm run env:check -- integration --services
```

After merging `master`, run setup again if lockfiles changed. For an already
prepared checkout, rebuild and check prerequisites:

```bash
npm run build
npm run env:check -- php
npm run lint
npm run build:php:validate
npm run docker:test:start
npm run env:check -- integration --services
```

`npm run lint` includes the fast PHP regression suite. A readiness check verifies
prerequisites; test results establish whether behavior works.

## 2. Run focused regressions

Reset before each group below. Run groups sequentially against the same database.
Admin UI uses its own config; `npm run test` excludes that directory.

```bash
# Sign-in rules, cutover, portal permissions and session boundaries.
npm run docker:test:reset:db
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec 'cypress/e2e/api/private/admin/private.admin.member-sign-in*.spec.js,cypress/e2e/api/private/admin/private.admin.portal-permissions.spec.js,cypress/e2e/api/private/standard/private.portal*.spec.js,cypress/e2e/api/personas/portal-only/*.spec.js,cypress/e2e/ui/personas/portal-only/*.spec.js'

# Member email sign-in, passkeys, devices and staff sign-in regressions.
npm run docker:test:reset:db
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec 'cypress/e2e/ui/portal/*.spec.js,cypress/e2e/ui/session/*.spec.js'

# Administrator rollout, sign-in email corrections and impersonation.
npm run docker:test:reset:db
npx cypress run --config-file cypress/configs/docker-admin.config.ts \
  --spec 'cypress/e2e/ui-admin/admin.member*.spec.js,cypress/e2e/ui-admin/admin.login-as-person*.spec.js,cypress/e2e/ui-admin/admin.sign-in-email-fixes.spec.js'
```

For a failed test, run that spec alone, find the first failure, fix it, and rerun
the group. Inspect `src/logs/` PHP/application logs and `cypress/logs/` even after a
passing run. Report test counts, failures and any skipped checks with the commit
SHA. Never retry an assertion failure without investigating it.

## 3. Run the complete regression suites before integration sign-off

The branch changes shared authentication, people/email handling, Volunteer and
schema behavior. The focused groups above are a development checkpoint; run all
three application suites for the integration result:

```bash
npm run docker:test:reset:db
npm run test:api
npm run docker:test:reset:db
npm run test:ui
npm run docker:test:reset:db
npm run test:ui-admin
```

The PR's **Build, Test and Package** workflow runs PHP 8.4 lint/build and root-path
Cypress shards. Inspect each job: `test-summary` can pass when a test job failed.
The authoritative definitions are [the PR workflow](../../.github/workflows/build-test-package.yml)
and [the nightly workflow](../../.github/workflows/build-test-nightly.yml).

Before declaring rollout ready, manually run **Build, Test and Package (Nightly)**
on `feature/member-sign-in` from GitHub Actions. Scheduled nightly runs cover the
default branch. The manual branch run covers root/subdirectory installs, PHP 8.5,
fresh setup, locales and the upgrade matrix, with PHP 8.4/8.5 upgrade coverage.
Record that run's URL and outcome on the integration PR. Test the member cutover
on a disposable copy of a pre-cutover database as well; a freshly seeded database
does not demonstrate that existing self-service accounts and family emails migrate.

## 4. Check the login and registration routes

With `bMemberSignIn` enabled, signed-out visitors reach `/portal/login`. With member sign-in off but self-registration enabled, `/portal/login` offers registration; with both settings off, signed-out visitors go to `/session/begin`. Staff sign in at `/session/begin`. `/admin/` always requires an authenticated admin and opens the admin dashboard; signed-out visitors are redirected to staff sign-in, and authenticated non-admin staff land at `/v2/dashboard`. When `bEnableSelfRegistration` is enabled, `/portal/login` links to `/portal/register` and the legacy `/external/register/` URL redirects there. When disabled, no portal registration link appears and `/portal/register` is denied. The family submission still uses the existing `/api/public/register/family` endpoint.

Run the focused route and registration specs after a test database reset:

```bash
npm run docker:test:reset:db
npx cypress run --config-file cypress/configs/docker.config.ts \
  --spec 'cypress/e2e/ui/session/session.sign-in-pages.spec.js,cypress/e2e/ui/session/auth-page-css.spec.js,cypress/e2e/ui/guest/guest.family.reg.spec.js,cypress/e2e/ui/mobile/mobile-ux.spec.js'
```

## 5. Check the workflow in a browser

Use `admin` / `changeme` at `http://localhost` and Mailpit at
`http://localhost:8025`. Use seeded people and test addresses. Check light/dark
appearance at desktop, tablet (about 820px) and phone widths, and attach screenshots
of the changed screens to the PR.

| Role / case | Expected result |
| --- | --- |
| Administrator enables member sign-in | Settings persist; eligible people and email problems are visible. |
| Eligible member requests a link/code | Mailpit receives the message; the link/code signs in to that person's portal. |
| Expired, reused, invalid or throttled request | Sign-in is refused with the expected recovery path and no account disclosure. |
| Member remembers a device or registers a passkey | A later sign-in works; revoking it prevents reuse. |
| Member edits their email | Confirmation completes before sign-in eligibility changes. |
| Member opens staff pages/APIs or another family's data | Access is refused; permitted portal pages still work. |
| Administrator uses Login as Person | The member view and banner appear; leaving returns to staff access and the action is audited. |
| Staff signs in with password / 2FA | Staff authentication and recovery still work. |
| Existing installation upgrades | Member access, family email handling and portal permissions follow the cutover rules; a subsequent upgrade is safe. |

Use [the design](../design/member-sign-in.md) and each spec's assertions for the
exact eligibility, permission and migration rules. Keep incomplete scenarios
listed on the draft PR until they are implemented and verified.
