---
title: "Testing Migration & E2E"
intent: "Testing strategy for migrating endpoints and UI flows (Cypress + integration tests)"
tags: ["testing","cypress","e2e","migration"]
prereqs: ["testing.md","cypress-testing.md","api-development.md"]
complexity: "intermediate"
---

# Testing Migration & E2E

Goals: Ensure feature parity and permission coverage when moving endpoints/UI to new MVCs.

Checklist:
- Add unit tests for any extracted service logic (`GroupService`).
- Add integration tests for API shims to ensure identical responses.
- Add Cypress scenarios covering:
  - Group creation, enrollment, and removal
  - Permission boundaries (admin vs manager vs regular user)
  - Public flows (register, calendar) if affected

Test data:
- Use fixtures for people, families, and groups; reset DB between runs.

CI:
- Run migration tests in a staging pipeline before merging migration PRs.
