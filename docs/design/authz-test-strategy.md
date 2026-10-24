---
title: "Authorization Test Strategy"
intent: "How ChurchCRM tests who may do what, so the RBAC redesign (ADR 0002) changes data, not specs"
tags: ["authorization","testing","cypress","rbac"]
prereqs: ["[[authorization-security]]","[[cypress-testing]]"]
complexity: "intermediate"
---

# Authorization Test Strategy

**Status:** Active. Slices 1 to 5 of §7 are shipped; slices 6 and 7 and the `pending` entries in `cypress/support/gates/coverage.js` are open. Implements the test side of [ADR 0002](../decisions/0002-rbac-redesign.md). Changes no runtime behavior. How to add a row or persona: `.agents/skills/churchcrm/cypress-testing.md`.

## 1. Purpose

A permission test proves one thing: **a persona can or cannot reach a surface**. It does not name the flag, the middleware or the helper that happens to enforce it today. ADR 0002 replaces the `usr_*` flags with roles and moves each feature through `LEGACY` → `DUAL_ENFORCE` → `RBAC` (R1). Specs that assert outcomes keep passing through every step, and specs that assert mechanisms must be rewritten at each one.

## 2. Rules

1. **One test per enforcement boundary.** A role wrapper added once to an app or route group (`/admin`, `/finance`, `/fundraiser`, `/groups`, `/event`, a `->add(...)` on a `group()`) gets one denial probe. A wrapper added route by route gets one probe per route (`cypress-testing.md`). The boundary is where the gate attaches, not the URL count.
2. **Every surface has an allowed persona.** A fail-closed evaluator that denies everyone passes every denial test. Each surface names at least one persona that must succeed.
3. **Deny with the nearest neighbor.** A surface is probed with a persona that holds the adjacent grants but not this one (finance without fundraisers, groups without finance), plus the zero-permission persona. A route gated by the wrong flag fails only the first.
4. **Personas, not flags.** Specs ask for `office-staff`, never for `makePrivateNoFinanceAPICall`. Renaming a seed user or retiring a flag is a one-place change (`cypress/support/personas-data.js`).
5. **Assert the outcome.** A denial is a 403 JSON body with `success: false`, or a redirect to `/v2/access-denied`. Only the access-denied page's own spec asserts the `role=` text, which becomes a capability under RBAC.
6. **One audience per spec** (`cypress-testing.md`). Admin-only surfaces are probed from the admin folders.
7. **Principals are not all staff.** The matrix names the principal type: staff, portal, device (kiosk) or service (API token). A portal member has no `user_usr` row after the member sign-in cutover, so a portal persona signs in by session, not by API key.

## 3. Personas

A persona is a seed user plus a stated intent. Names follow the candidate templates in ADR 0002 §3, which are not approved grants. Flags are today's `user_usr` values from `cypress/data/seed.sql`.

| Persona | Seed user | Flags today | Nearest ADR template |
|---|---|---|---|
| `church-admin` | `Admin` (1) | Admin | Church Administrator |
| `staff-all-but-admin` | tony.wade (3) | every record, group, finance, fundraiser and notes flag; not Admin | none (strongest non-admin probe) |
| `office-staff` | judith.matthews (95) | Add Records, Edit Records, Manage My Ministries | Office Staff / People Editor |
| `finance-only` | grace.financeonly (904) | Finance | Finance Manager |
| `finance-no-fundraisers` | finance.nofundraiser (96) | Delete Records, Finance | Finance Manager |
| `groups-only` | kyle.kioskonly (905) | Manage Groups | Group / Team Leader |
| `notes-only-staff` | john.plainauth (900) | Notes | none |
| `zero-permission-staff` | noperm.user (901) | none | Read-Only Staff |
| `portal-only` | Darren Campbell (4), a member session with no user account | none | Member / Household |

A persona is added when a surface needs it. The kiosk device and API token personas arrive with the kiosk and token slices.

## 4. Access matrix

One row per boundary, one file per surface (`cypress/support/gates/<surface>.js`), so slices never share a file.

```js
{
  id: "finance-app",
  principal: "staff",
  probe: { method: "GET", url: "/finance/api/funds" },   // a safe read
  classification: "finance",                              // data class, for the exposure graph (S1)
  capability: "finance.read",                             // candidate RFC key, informational
  allow: ["finance-only", "church-admin"],
  deny: ["office-staff", "zero-permission-staff"],        // includes the nearest neighbor
}
```

A runner turns each row into one `it()` per audience and loops its personas. `classification` groups the alternate channels of one data class (list, search, count, CSV, PDF, labels), so hardening a data class adds rows and rewrites none.

A structural spec lists the gated apps and groups from `src/*/index.php` and `src/api/routes/**` and fails when one has no row file.

## 5. Expected changes

These are the only places a row should change when ADR 0002 lands. Each is a cell edit, not a rewrite.

| ADR item | Cell that changes |
|---|---|
| P6: an administrator never reads highly confidential notes | `church-admin` moves from `allow` to `deny` on the confidential-notes rows. Today `private.notes.visibility.spec.js` pins the admin reads of another user's private note (single note, person and family timelines, notes list) and the admin edit and delete of any note; those assertions are the ones P6 flips |
| #10516 bulk export | Letters, labels and CSV rows: `zero-permission-staff` and `office-staff` move to `deny` once scoped export grants exist. Today letters and labels follow ordinary staff access (#10778) |
| #10512 `membership.date.manage` | New row: `office-staff` denied on the membership date write |
| Member sign-in cutover | `portal-only` signs in by session; its API-key rows are removed |
| #10684, #10688 | Delete rows stop depending on Delete Records; personas are unchanged |
| Retired flag (any) | The persona's `flags` entry changes; an upgrade fixture asserts who loses access |

Rows for restricted people, trusted pastoral groups, guardians, voting eligibility and token scopes are written with the slice that builds them. ADR 0002 §10 says not to mark them passing without evidence.

## 6. Layers

- **Cypress API and UI** sample one probe per boundary per persona, plus the allowed control. The repository takes no PHP test scripts (`cypress-testing.md`).
- **Policy unit tests with fake principals** (ADR 0002 §10) need `AuthorizationService::decide()` and a unit runner. That is an open decision for the maintainers, because the current rule is Cypress only.
- **Fresh versus upgraded database.** The same matrix runs from the `upgrade` config, which proves "same effective authorization" (ADR 0002 §10).

## 7. Delivery

Each slice is its own PR from `master`, as `git-workflow.md` requires; a slice that needs another slice's code bases on it.

| # | Slice | Needs |
|---|---|---|
| 1 | This document | |
| 2 | Persona registry and fixture-integrity specs | |
| 3a–d | Gate rows and runner specs: admin, finance, fundraiser/groups/event, kiosk | 2 |
| 4 | Structural coverage spec | 3a–d |
| 5a–c | Delete the tests the rows replace, one PR per surface | 3x |
| 6 | One shared persona-key module for the Cypress configs | after the open Menu Options PRs |
| 7 | Upgrade-fixture run of the matrix | 3a–d |

## 8. Readiness gates

F0 and F1 do not change who can do what, so the gate rows are their safety net: a changed outcome fails a row. The first slice that replaces a V1 check with a V2 one (F3) starts when:

1. `gates/coverage.js` has no `pending` entry. Groups, events and calendar, and plugin routes each name the fixture they need.
2. Every route that returns person, family, finance or note data is listed with its gate or marked public or self-service. `coverage.js` lists only files that attach a role middleware, so a route with no gate is invisible to it; an inventory of all routes closes that gap (ADR 0002 R0.1).
3. Each data class has rows for its alternate channels: list, search, count, CSV, PDF and labels (§4, `classification`).
4. The matrix runs from the `upgrade` config (§6).
5. The maintainers decide on policy unit tests with fake principals (§6).
6. The cleanup guard counts `pledge_plg` and `deposit_dep` (`GUARDED_TABLES` in `cypress/configs/row-count-guard.ts`), so a spec that leaks payments fails its own file.
