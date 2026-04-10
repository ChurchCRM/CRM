---
title: GSD Epic Planning
intent: "Decompose a large feature request into an epic + ordered sub-tickets using the Get Shit Done methodology"
applies_to: "Any issue tagged as a large effort (>2 days) that should be broken down before work starts"
---

# GSD Epic Planning

Adapted from the [GSD methodology](https://github.com/gsd-build/get-shit-done) for ChurchCRM's GitHub-issue-driven workflow. GSD's 6 phases map cleanly onto the "epic + sub-issues + milestone" flow that issues #8538 and #8493 already follow.

**When to use this**: a ticket comes in that's clearly >2 days of work AND touches more than one layer (schema, API, UI, tests). Run the phases in order. Skip phases only with explicit user approval.

---

## Phase 1 — Frame & Scope <!-- equivalent: /gsd-new-project -->

**Output**: a scoping comment on the epic ticket with v1/v2/out-of-scope split.

Before proposing any code or sub-tickets, establish what the work *actually is*. GSD's core insight: bad scope at Phase 1 compounds through every later phase. Err on the side of too many clarifying questions.

### Frame checklist

- [ ] **Is the data model already in place?** Check `orm/schema.xml` for the relevant tables/columns. If yes, this is a "wire up UI to existing data" effort; if no, factor in a schema migration phase.
- [ ] **What related issues exist?** `gh issue list --search "<keyword> in:title"` and walk the closed ones. Old tickets often have closed siblings that already did pieces of the work.
- [ ] **What's the user story?** Write it out in plain English for each audience (admin, staff, member, visitor). If you can't explain the user flow in one paragraph per audience, the scope isn't clear yet.
- [ ] **What's the minimum viable shape?** The v1 cut should be usable end-to-end but intentionally incomplete. Don't ship a half-wired version.
- [ ] **What are the edges?** For each feature, ask: what happens when the prerequisite is missing? What happens when the thing is deleted? What happens on upgrade of existing data?

### Scoping comment template

```markdown
## Status Audit (post-<last relevant merge>)

### What already exists today
| Layer | State |
|---|---|
| Database | ✅/❌ |
| Propel ORM models | ✅/❌ |
| API — read | ✅/❌ |
| API — write | ✅/❌ |
| UI — list page | ✅/❌ |
| UI — CRUD form | ✅/❌ |
| UI — integration on related pages | ✅/❌ |

### User journeys (one paragraph per audience)
**Admin** — …
**Staff** — …
**Member** — …

### v1 scope (this epic)
1. …
2. …

### v2 / follow-up (separate tickets)
- …

### Out of scope (explicit — will be declined)
- …

### Open questions for the ticket owner
- …
```

**Output file**: post as a comment on the epic ticket. Do not start making tickets until the owner acknowledges the scope.

---

## Phase 2 — Discuss (Gray Areas) <!-- equivalent: /gsd-discuss-phase -->

**Output**: answered gray-area checklist (comment on the epic or in the conversation).

Before building any plan, surface implementation preferences that will make or break the UX. GSD calls these "gray areas" — decisions that aren't obvious from the ticket text and that you'd otherwise make by default.

### Gray areas checklist for ChurchCRM

Work through each category; skip any that don't apply.

**Data shape**
- [ ] Soft delete vs hard delete? (Usually soft — check for existing `Active` / `DateDeactivated` columns on sibling tables.)
- [ ] What's the "empty" default value? (For FKs: `0` is the ChurchCRM convention, not `NULL`.)
- [ ] Can the entity be referenced by existing records? If yes, delete semantics must preserve history.
- [ ] Any columns on the schema that are currently unused (`*_typeID`, etc)? Decide: wire now, defer, or leave orphan.

**UI shape**
- [ ] Where does this live in the menu? (`Menu.php` entry under which parent?)
- [ ] Does it need a new `/admin/` page, a new `src/<module>/` page, or does it extend an existing module?
- [ ] Which Tabler pattern? (DataTable list + edit page, or card grid, or wizard?)
- [ ] Quick-add from the form that references it? (e.g. "+ Add new …" inside a dropdown → bootbox modal)
- [ ] Active/inactive toggle vs hard delete?

**Permissions**
- [ ] Which `User::is*()` method gates this? (Audit existing role methods before adding new ones.)
- [ ] Module-level middleware: lowest tier read routes need, with `->add()` per write route. See `authorization-security.md` → "MVC Module Middleware: View vs Add Split"
- [ ] Does this need a new permission column? (High bar — prefer reusing existing permissions.)

**Integration points**
- [ ] Which existing pages display / reference this entity? Each one is a separate integration sub-ticket.
- [ ] Which API endpoints will consumers of the API need? (List ALL: GET list, GET detail, POST, PUT, DELETE.)
- [ ] Does the API need to be backwards-compatible with the existing shape? If yes, that's a constraint on v1.

**Failure modes**
- [ ] What happens when the referenced entity is missing? (Fall back to "None", hide the badge, etc.)
- [ ] What happens on migration of existing data? (Default value, one-time backfill script, no-op?)
- [ ] What happens for users without the gating permission?

**i18n / a11y**
- [ ] All new strings wrapped in `gettext()` / `i18next.t()`?
- [ ] Keyboard-navigable? Screen-reader labels on icon-only buttons?

Answer each item in prose (single sentence per bullet is fine). Save the answers in the epic ticket as a comment — these are the acceptance criteria for every sub-ticket.

---

## Phase 3 — Plan (Atomic Sub-Tickets) <!-- equivalent: /gsd-plan-phase -->

**Output**: an ordered list of sub-tickets, each "small enough to ship in one PR".

### Rules for sub-ticket decomposition

1. **One layer per ticket.** Don't mix "add schema" with "add UI" in the same ticket. Layers are independently reviewable and have different reviewers.
2. **Atomic = shippable in isolation** — each ticket's PR passes CI on its own. If ticket B's PR would break ticket A's tests, B depends on A.
3. **Target one PR per ticket.** If a ticket needs two PRs, it's too big; split it.
4. **Wave ordering** — group tickets into waves by dependencies. Within a wave, tickets can ship in any order (or in parallel by different contributors). Waves ship in sequence.
5. **Each ticket has the same canonical shape** (template below) — consistency makes review and tracking easy.

### Standard layer decomposition for an MVC feature

Use this as the default skeleton for any "new entity + UI + API" feature:

| Wave | Layer | Ticket pattern |
|---|---|---|
| **1** | Data model | Schema additions, Propel regen, upgrade SQL (only if needed) |
| **2a** | Read API | `GET /api/<resource>` (list), `GET /api/<resource>/{id}` (detail), OpenAPI specs |
| **2b** | Write API | `POST`, `PUT`, `DELETE` endpoints with auth middleware, OpenAPI specs, Cypress API spec |
| **3a** | UI list page | `src/<module>/routes/<resource>.php` + `views/<resource>-list.php` + webpack entry, DataTable, Add button, row actions |
| **3b** | UI CRUD forms | `views/<resource>-new.php` + `views/<resource>-edit.php` + form handlers |
| **4** | Integration | Picker on related page(s), display on related page(s), filter on dashboard |
| **5** | Polish | i18n build, a11y sweep, docs, release notes entry |

Wave 2a and 2b can be one ticket if the API surface is small. Wave 3a and 3b can be one ticket if the CRUD fits in one form.

### Sub-ticket body template

```markdown
**Part of epic #<N>**

## Context
<one paragraph — what's being built, why, which epic section>

## Acceptance criteria
- [ ] <concrete, testable bullet>
- [ ] <concrete, testable bullet>

## Depends on
- #<earlier wave ticket> (must be merged first)

## Files affected
- `path/to/route.php` (new)
- `path/to/view.php` (new)
- `webpack/<entry>.js` (new)
- `cypress/e2e/<spec>.js` (new)

## Out of scope for this ticket
- <thing that was considered and deferred — reference the ticket that will do it>

## Verify
- [ ] CI green
- [ ] Specific manual test: <one sentence>
- [ ] Related skill doc updated (if pattern changed): `<skill-file>`
```

### Ordering constraints

- **Schema before API** — you can't write ORM queries against a non-existent column.
- **Read API before UI** — the UI will fetch data via the API (or directly via Propel in the route — either way the API is verification).
- **Write API before write UI** — Cypress UI tests will hit the API.
- **Core before integration** — list page works end-to-end before you add pickers to other pages.
- **i18n last** — strings stabilize during UI work, so running `locale:build` before UI is done wastes translator time.

---

## Phase 4 — Execute (One PR per Ticket) <!-- equivalent: /gsd-execute-phase -->

Out of scope for this skill — covered by `admin-mvc-migration.md`, `api-development.md`, and `pr-review.md`. The GSD contribution here is just the **wave discipline**: don't jump ahead. Finish a wave before starting the next one (or have a good reason documented in the epic why you're parallelizing).

---

## Phase 5 — Verify (Per-Ticket UAT) <!-- equivalent: /gsd-verify-work -->

Each sub-ticket's PR needs:
- CI green (lint + build + affected Cypress specs)
- Manual verification of the acceptance criteria checklist from the ticket body
- Epic checklist box ticked when merged

---

## Phase 6 — Ship & Close <!-- equivalent: /gsd-ship -->

When the last wave merges:
1. Verify the epic checklist is fully ticked
2. Close the epic with a comment listing all sub-issues closed by the effort (see the PR #8550 closing comment on epic #8538 for the template)
3. Update the relevant skill file(s) with any patterns learned — `post-PR skill updates are mandatory`, see `pr-review.md` Phase 7
4. Release notes: add a single bullet referencing the epic

---

## Quick mode for small efforts

If the user says *"just break this up, don't overthink it"*, skip Phases 1–2 and go straight to Phase 3 with a short ticket list. Only appropriate when:
- The data model is already in place
- The user journeys are obvious from the ticket
- The decomposition is mechanical (CRUD + integration, no open design questions)

Otherwise run the full flow — cheap Phase 1–2 questions prevent expensive Phase 4 rework.

---

## Milestone hygiene

- **Epic goes on the target milestone** (`vNext` if deferred, `7.2.x` if in-cycle)
- **Sub-tickets go on the same milestone** as the epic — never a mix
- Move the epic *and all sub-tickets* in one batch when shifting between milestones
- **Remove `Stale` labels** on revived tickets

---

## Related skills

- `admin-mvc-migration.md` — layer-by-layer patterns used during execution
- `api-development.md` — API decomposition patterns
- `pr-review.md` — the review + close + skill-update loop
- `routing-architecture.md` — where MVC modules live
- `authorization-security.md` — module middleware split patterns
