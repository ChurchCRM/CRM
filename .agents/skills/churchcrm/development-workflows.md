---
title: Development Workflows
intent: Pointer to live setup commands. Do not copy package.json.
---

# Development Workflows

Canonical setup: `DEVELOPING.md`.
Commands: `package.json`.
Docker profiles: `docker/README.md`.
Tests: `cypress/README.md` and `cypress-testing.md`.

```bash
npm install
npm run build
npm run docker:test:start
```

Local test login is `admin` / `changeme`. It exists only on the test stack.

After JS/TS/CSS: `npm run build:frontend`.
Before a PR: `npm run lint`, the matching build, and the smallest Cypress spec that covers the change.

Wrap UI strings (`i18n-localization.md`).
