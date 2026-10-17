# RFC: Issue Quality, Epic Sizing, PR Scope, and Good First Issue Readiness

**Status:** Accepted by George Dawoud on 2026-10-17 — implementation rollout tracked separately  
**Scope:** Pilot in `ChurchCRM/CRM`, then evaluate other repositories  
**Decision owner:** ChurchCRM maintainers; Q1–Q8 approved by George Dawoud on 2026-10-17  
**Review input:** [cwhorton's review of PR #10539](https://github.com/ChurchCRM/CRM/pull/10539#issuecomment-6127612100)  
**Related but distinct:** [RFC portfolio/community feedback #10550](https://github.com/ChurchCRM/CRM/issues/10550) and [governance PR #10551](https://github.com/ChurchCRM/CRM/pull/10551). This RFC governs **implementation issue/PR quality**, not public feature voting, RFC stages, or release priorities.

## Purpose and current authorities

Keep reporting easy for churches, while requiring maintainers to make implementation tasks clear, safe and reviewable before asking volunteers or agents to build them. Design approval does not mean an implementation issue is scheduled or release-bound.

**Decision record:** George approved Q1–Q8 on 2026-10-17. This RFC is the final record of those policy decisions. It is not the implementation itself: existing operating rules remain authoritative until the follow-up work is reviewed and merged. The implementation tracker is [#10634](https://github.com/ChurchCRM/CRM/issues/10634).

**Extend existing rules; do not create parallel policies.** Current source-of-truth surfaces (until follow-up PRs change them):
- [`docs/contributing-pr-review.md`](../../docs/contributing-pr-review.md) — existing canonical PR merge/review requirements, linked from the **root** [`CONTRIBUTING.md`](../../CONTRIBUTING.md).
- [`.agents/skills/churchcrm/maintainer-review-gates.md`](../../.agents/skills/churchcrm/maintainer-review-gates.md), [`git-workflow.md`](../../.agents/skills/churchcrm/git-workflow.md) and [`github-interaction.md`](../../.agents/skills/churchcrm/github-interaction.md) — existing agent and epic guidance; reference the canonical guides rather than repeat their content.
- [`.coderabbit.yaml`](../../.coderabbit.yaml) — existing automated review configuration, not a human-approval substitute.
- [`.github/CONTRIBUTING.md`](../../.github/CONTRIBUTING.md) — GitHub-facing entry point linking to **docs.churchcrm.io/contributing**, distinct from root `CONTRIBUTING.md`. The docs-site contributor page and its beginner-issue promises must be aligned during rollout.
- [`AGENTS.md`](../../AGENTS.md), `CLAUDE.md`, and relevant skills — the discoverable agent entry points.
- The current freeze on adding features to Query View is documented in `docs/contributing-pr-review.md`. The authoritative inventory of frozen/replacement areas will live in a **Frozen areas** section of that same file; triage must consult that section rather than invent a second contradictory list.

The companion community RFC governs **maintainer-authored, plain-language Feature Proposal Discussions** and **technical RFCs reviewed in draft PRs**. This RFC starts where a request becomes an implementation candidate.

## Accepted decisions — Q1–Q8 approved by George Dawoud

| ID | Accepted decision | Reviewer disposition |
| --- | --- | --- |
| Q1 | **Reuse** `docs/contributing-pr-review.md` for PR standards; create one concise **issue readiness** page in `docs/`; link both from root CONTRIBUTING and the GitHub/docs entry point. Treat this RFC as decision history. | **Accept** existing-rules critique; no new competing all-in-one PR guide |
| Q2 | Size primarily by **review burden**: touched subsystems, permission/schema/data risk, handoffs, test surface and non-generated diff size. Effort estimates are optional hints, never acceptance gates. | **Accept**; replaces hour/day classification |
| Q3 | Create an **Epic** for multiple independently shippable outcomes, significant cross-subsystem work or unresolved design/permissions/schema decisions; use GitHub **sub-issues**, not only hand-maintained checkboxes. Prefer *vertical end-to-end user outcomes*; allow strictly necessary additive infrastructure slices with explicit consumers and safety gates. | **Accept with safety exception**; audit #5238 children before using them as a model |
| Q4 | `good first issue` is **maintainer certification**, not difficulty guess: settled design, low-risk bounded task, existing test pattern, actionable paths and checks, owner available to review; no automatic size/time guarantee. | **Accept** |
| Q5 | Reuse existing `Design Decision`, `Epic`, and `good first issue` labels. Do **not** add `needs-design`, `ready-for-dev`, a Projects status or sizing taxonomy merely for this RFC. An issue is ready if it meets the contract and has no unresolved `Design Decision` blocker. | **Accept**; no label changes without separate audit |
| Q6 | Work is **claimed** only after checking open issues and PRs, commenting or being assigned on the issue, and posting an early draft PR for substantial work. On supersession/closure of **both issues and PRs**, comment early and cross-link replacement; protect actively reviewed pilot tickets from stale automation by checked, reversible action, not blanket exemptions. | **Accept** |
| Q7 | Name **human maintainers or accountable roles** as owners; coding agents are executors, not final approvers or accountable issue owners. Begin with CRM and update cross-repo guidance only after a successful pilot. | **Accept** |
| Q8 | Keep **one** rollout plan, **one** checklist, and a compact RFC. Use already existing PR hard-block guidance instead of restating it. | **Accept** |

George approved Q1–Q8 on 2026-10-17. This RFC records the accepted decisions; implementation remains a separate, tracked rollout. No templates, labels, CI, milestones, or backlog automation are changed by merging this RFC alone.

## Workflow: church request → maintainer triage → ready issue → PR

### Friendly intake

A church user can describe a problem, current workflow, expected improvement, version and optional screenshots. Do **not** demand technical architecture, PR knowledge, code paths, migration plans or acceptance tests from reporters. Maintainers curate Feature Proposal Discussions per the separate community RFC.

### Evidence-based maintainer triage

1. Verify current code/released behavior, existing issue(s), pending PR(s), frozen areas and cross-repository requirements. **Do not reimplement a fix already on master or reject a report just because its template is incomplete.**
2. Classify normal issue, epic/children, or unresolved `Design Decision`/RFC. Technical design approval must precede implementation work that depends on it.
3. Keep/update/split/supersede/complete/decline with a concise explanation and links. When superseding a PR, comment on the original PR promptly and link the replacement before the author spends time fixing it.
4. Define the intended user outcome, scope/non-goals, acceptance criteria, dependencies, review risks and how to test.
5. Remove unplanned/stale release promises and milestones; verify that the project/label workflow will not accidentally close pilot issues while they are being reviewed.
6. Specify an accountable person/maintainer role; no self-assigned work without coordination.

### Ready-for-development contract

For a nontrivial issue, maintainers provide:
- Problem, impacted church workflow and **verified baseline** (with relevant version/source).
- Expected independently verifiable outcome; scope and non-goals.
- Approved decisions and code entry points/patterns; links to canonical design RFC if needed.
- Dependencies, existing related PRs, frozen/obsolete areas, security/privacy/schema implications.
- Tests and evidence expected (unit/E2E, mobile, accessibility, localization, upgrade safety, screenshots where relevant).
- Review ownership and any real release gate; **a milestone only if scheduled**.

For a tiny bug or copy fix, a linked issue, clear reproduction/location, intended change and quick validation are enough. Reporters never have to fill the full maintainer contract.

### Reviewability and epic decomposition

**Judge review cost first**, not elapsed coding time: independently reviewable change, number of modules, risk to existing installs, migration coupling, and tests matter more than raw LOC. Ignore generated/localization churn when estimating diff size. A large atomic transaction/security fix may justify a larger PR **if splitting would introduce insecure intermediate states**.

An epic is a coordination object, **not one ticket to hand to an agent**. Use native GitHub sub-issues with independently testable acceptance criteria, owner, dependencies, order and explicit closure criteria. Prefer vertical user-visible slices, e.g. “an administrator can add and list an event location” with relevant backend, UI and tests. Allow foundation-only work only when required, bounded, independently verifiable, and safe without a consumer.

Before anyone starts an issue: search for open PRs, ask to be assigned or leave an intent comment, then open a draft PR early to avoid duplicated effort. Stacked PRs clearly state base and merge order.

### Maintainer-certified `good first issue`

Apply only if **all** are true:
- [ ] Design, permissions and data choices are settled; no unresolved sensitive decision.
- [ ] One low-risk, clearly bounded, independently testable result; realistic for a newcomer with guidance.
- [ ] Exact starting paths/repro and an **existing test to copy** or explicit lightweight test recipe.
- [ ] Clear acceptance checks and i18n/mobile/docs needs as relevant.
- [ ] No dangerous schema migration, new authentication boundary, sensitive data exposure or hidden dependency.
- [ ] No active duplicate PR and an identified human reviewer is available.

If an issue becomes design-heavy, remove the label with explanation. Do not promise a fixed number of hours, assume a volunteer's environment is already configured, or claim there are available beginner issues without verifying the actual open list.

### PR requirements

Continue to apply **`docs/contributing-pr-review.md` and existing merge gates**. PRs link the issue when user-visible behavior changes; explain what/why, scope, tests, migration/privacy/security/i18n/mobile effects and any limitations. Distinguish code and RFC/doc PRs. Human maintainers approve/merge; automated review cannot substitute for that. Avoid parallel descriptions of hard-block rules here.

## Pilot: Events and Kiosk only

Review #8821, #8822, #8825, #9525, #5238, #8575–#8583, #8826, #8650, #8647, #3902, #8978 against current code and releases. Avoid conflating reporting #10538, messaging #8693, and PDF modernization #8151.

- Record keep/update/split/supersede/close dispositions with evidence; **do not automatically recreate** closed children or the entire epic.
- Inspect Event Locations #5238 for horizontal schema/API/UI waves. Where feasible, regroup remaining work into complete church-facing outcomes without introducing breaking or unsafe intermediate states.
- Check the stale workflow and any currently flagged tickets (including #8825) **before** they close. Protect specific active pilot issues through maintainer-approved, reversible triage; do not globally disable cleanup.
- Curate at most **3–5 truly qualified** `good first issue` candidates only after assessing actual readiness; if none qualify, say so.
- Have a contributor/maintainer validate the example issue and PR flow before adopting rules across docs/website.

## Single rollout and acceptance checklist

Q1–Q8 are approved. This RFC records those decisions; it does not itself update project-wide rules or schedule contributor tasks. Execution is tracked in [#10634](https://github.com/ChurchCRM/CRM/issues/10634) and reviewed separately.

| Order | Accountable role | Follow-up change / verification |
| --- | --- | --- |
| 1 | Maintainer / rollout owner | Establish and maintain implementation tracking in [#10634](https://github.com/ChurchCRM/CRM/issues/10634); name an accountable CRM pilot owner before starting the pilot. |
| 2 | Contributor author; maintainer reviewer | Extend existing `docs/contributing-pr-review.md` only where gaps exist; add one issue-readiness guide, link both from **root** `CONTRIBUTING.md` and reconcile `.github/CONTRIBUTING.md` → docs-site contributing page. |
| 3 | Contributor author; maintainer reviewer | Update `.github/ISSUE_TEMPLATE/` and PR template lightly; update `AGENTS.md`, `CLAUDE.md` where relevant, `.agents/` skill links, `github-interaction.md`, `maintainer-review-gates.md`, and review CodeRabbit config for contradictions. |
| 4 | Maintainer triage owner | Audit existing labels and frozen-area references. No new labels/CI checks unless separately approved. Verify claiming, supersession and stale-process guidance. |
| 5 | Maintainer pilot owner; contributor reviewer | Evidence-based Events/Kiosk backlog review, GitHub sub-issue hygiene, and 3–5 beginner tickets only if certified. |
| 6 | George / maintainers | Review pilot outcomes with contributors before other repositories adopt rules. |

**Done only when:** approved guidance is findable from actual GitHub and docs entry points; templates and agents link it rather than fork it; existing labels and freeze rules are reconciled; representative bug/feature/epic/PR/beginner cases are tested; and the pilot records verifiable issue dispositions and independent review.

**Explicitly out of scope:** automatic issue closure; automatic new milestones; force-applying labels; subjective CI quality scoring; broad backlog rewrites; blanket stale-job exemptions. Any such expansion requires separate maintainer review and George's approval.
