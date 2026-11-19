# Verification evidence

Checked on 2026-11-19 from CRM source commit `36b7aea` (7.8.6 development).
Every platform candidate still requires a live deployment. No catalog entry,
deploy button, partner application or documentation publication is complete.

## Local results

| Check | Result |
| --- | --- |
| Persistence, release-context and Compose security tests | 18 passed, including 6 persistence tests |
| TypeScript | Passed; Easypanel's generator belongs to its upstream template project |
| Coolify and Dokploy `docker compose config --quiet` | Passed with disposable validation values |
| Dokploy upstream `validate-template.ts` | Passed |
| Render public blueprint JSON schema | Passed |
| Easypanel upstream `templateSchema` applied to generator output | Passed |
| Shell and Node syntax; `git diff --check` | Passed |
| Repository lint | PHP regression, Composer lock and Composer-dependent tooling checks blocked by missing host PHP/Composer |
| Full application build | Blocked by missing Composer |

The frontend portion of `npm run build` compiled successfully with webpack's
three performance warnings. PHP installation and final build signing did not run.

## Local browser and container checks

The workspace uses Docker's VFS storage driver. Pulling the full application
image exhausted disk while expanding its layers. Local browser checks used a
flattened copy of the published 7.8.5 filesystem with the candidate entrypoint
copied into the container. This checks the entrypoint against real application
code, but does **not** verify a build of the modified production Dockerfile.

- Application source image: `churchcrm/crm:latest-php8-apache`, manifest
  `sha256:3164d4498ee9307b058ed76a18fd8a49124591660390078c84011eb8def0ae02`.
- Database: `mariadb:11.4.5`, digest
  `sha256:49117dcc565cf51aa57ac5fca59ab31213402ff0eae6ffc13c46a37b938f7e4b`.
- Existing setup-wizard Cypress suite: **17/18 passed**. Full installation,
  forced password change and login passed. The church-info success-message
  assertion failed (`Church information saved successfully` was not observed).
  This failure remains unresolved; the suite is not green.
- Replaced the application container, retaining only the named application disk.
  Configuration retained its SHA-256 checksum and mode `0600`; person and family
  photo-directory probe files retained their contents. These probes verify file
  persistence, not authenticated photo uploads.
- A separate Cypress check after replacement passed: setup redirects, staff
  login works, the authenticated people endpoint responds, and direct person
  and family probe-file requests return `403`.

The production image smoke test now covers two fresh containers sharing one disk,
configuration permissions, photo persistence and the current image's bootstrap.
Run it against a built candidate image before releasing the image change.

## Remaining handoff

1. Build and release the Apache image with persistent storage support; resolve
   the official-image prerequisite in #11753.
2. Complete each platform's live verification checklist in [README.md](README.md).
   Include actual photo uploads, restore and upgrade checks.
3. Prepare catalog metadata and authentic assets, submit verified candidates,
   publish Railway's template, and record accepted catalog/deploy URLs.
4. Add real deploy buttons and the Railway standalone guide to the owning docs
   and website repositories, then apply to the Railway partner program.

GitHub fork creation returned `403 Resource not accessible by integration`.
The maintainer requested a draft PR while deployment verification remains open;
the candidates remain pending the live checks above.
