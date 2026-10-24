# RFC: Issue Quality, Epic Sizing, PR Scope, and Good First Issue Readiness

> **BLUF**
> This RFC sets the durable rules for making ChurchCRM work clear, reviewable, and safe. Maintainers own readiness and decisions; agents execute, not approve. Size by review burden and risk, split epics by independently testable user outcomes, and use existing labels and review gates. The canonical [issue-readiness guide](../contributing/issue-readiness.md) is the short operational checklist; this RFC preserves the accepted decisions. Current pilot work and issue-by-issue findings belong only in the [implementation tracker](https://github.com/ChurchCRM/CRM/issues/10634).

**Status:** Accepted  
**Date:** 2026-10-17  
**Owner:** George Dawoud  
**Rollout:** [#10634](https://github.com/ChurchCRM/CRM/issues/10634)  
**Scope:** Pilot in `ChurchCRM/CRM`, then evaluate other repositories  
**Decision owner:** ChurchCRM maintainers; Q1–Q8 approved by the owner on the date above  
**Review input:** [cwhorton's review of PR #10539](https://github.com/ChurchCRM/CRM/pull/10539#issuecomment-6127612100)  
**Related but distinct:** [RFC portfolio/community feedback #10550](https://github.com/ChurchCRM/CRM/issues/10550) and [governance PR #10551](https://github.com/ChurchCRM/CRM/pull/10551). This RFC governs **implementation issue/PR quality**, not public feature voting, RFC stages, or release priorities.

## Purpose and current authorities

Keep reporting easy for churches, while requiring maintainers to make implementation tasks clear, safe and reviewable before asking volunteers or agents to build them. Design approval does not mean an implementation issue is scheduled or release-bound.

**Decision record:** George approved Q1–Q8 on 2026-10-17. This RFC is the final record of those policy decisions. It is not the implementation itself: existing operating rules remain authoritative until the follow-up work is reviewed and merged. The implementation tracker is [#10634](https://github.com/ChurchCRM/CRM/issues/10634).

**Extend existing rules; do not create parallel policies.** Current source-of-truth surfaces (until follow-up PRs change them):
- [`docs/contributing/pr-review.md`](../contributing/pr-review.md) — existing canonical PR merge/review requirements, linked from the **root** [`CONTRIBUTING.md`](../../CONTRIBUTING.md).
- [`.agents/skills/churchcrm/maintainer-review-gates.md`](../../.agents/skills/churchcrm/maintainer-review-gates.md), [`git-workflow.md`](../../.agents/skills/churchcrm/git-workflow.md) and [`github-interaction.md`](../../.agents/skills/churchcrm/github-interaction.md) — existing agent and epic guidance; reference the canonical guides rather than repeat their content.
- [`.coderabbit.yaml`](../../.coderabbit.yaml) — existing automated review configuration, not a human-approval substitute.
- [`.github/CONTRIBUTING.md`](../../.github/CONTRIBUTING.md) — GitHub-facing entry point linking to **docs.churchcrm.io/contributing**, distinct from root `CONTRIBUTING.md`. The docs-site contributor page and its beginner-issue promises must be aligned during rollout.
- [`AGENTS.md`](../../AGENTS.md), `CLAUDE.md`, and relevant skills — the discoverable agent entry points.
- The current freeze on adding features to Query View is documented in `docs/contributing/pr-review.md`. The authoritative inventory of frozen/replacement areas will live in a **Frozen areas** section of that same file; triage must consult that section rather than invent a second contradictory list.

The companion community RFC governs **maintainer-authored, plain-language Feature Proposal Discussions** and **technical RFCs reviewed in draft PRs**. This RFC starts where a request becomes an implementation candidate.

## Accepted decisions — Q1–Q8 approved by George Dawoud

| ID | Accepted decision | Reviewer disposition |
| --- | --- | --- |
| Q1 | **Reuse** `docs/contributing/pr-review.md` for PR standards; create one concise **issue readiness** page in `docs/`; link both from root CONTRIBUTING and the GitHub/docs entry point. Treat this RFC as decision history. | **Accept** existing-rules critique; no new competing all-in-one PR guide |
| Q2 | Size primarily by **review burden**: touched subsystems, permission/schema/data risk, handoffs, test surface and non-generated diff size. Effort estimates are optional hints, never acceptance gates. | **Accept**; replaces hour/day classification |
| Q3 | Create an **Epic** for multiple independently shippable outcomes, significant cross-subsystem work or unresolved design/permissions/schema decisions; use GitHub **sub-issues**, not only hand-maintained checkboxes. Prefer *vertical end-to-end user outcomes*; allow strictly necessary additive infrastructure slices with explicit consumers and safety gates. | **Accept with safety exception**; validate decomposition against the agreed criteria |
| Q4 | `good first issue` is **maintainer certification**, not difficulty guess: settled design, low-risk bounded task, existing test pattern, actionable paths and checks, owner available to review; no automatic size/time guarantee. | **Accept** |
| Q5 | Reuse existing `Design Decision`, `Epic`, and `good first issue` labels. Do **not** add `needs-design`, `ready-for-dev`, a Projects status or sizing taxonomy merely for this RFC. An issue is ready if it meets the contract and has no unresolved `Design Decision` blocker. | **Accept**; no label changes without separate audit |
| Q6 | Work is **claimed** only after checking open issues and PRs, commenting or being assigned on the issue, and posting an early draft PR for substantial work. On supersession/closure of **both issues and PRs**, comment early and cross-link replacement; protect actively reviewed pilot tickets from stale automation by checked, reversible action, not blanket exemptions. | **Accept** |
| Q7 | Name **human maintainers or accountable roles** as owners; coding agents are executors, not final approvers or accountable issue owners. Begin with CRM and update cross-repo guidance only after a successful pilot. | **Accept** |
| Q8 | Keep **one** rollout plan, **one** checklist, and a compact RFC. Use already existing PR hard-block guidance instead of restating it. | **Accept** |

George approved Q1–Q8 on 2026-10-17. This RFC records the accepted decisions; implementation remains a separate, tracked rollout. No templates, labels, CI, milestones, or backlog automation are changed by merging this RFC alone.

## How the decisions are applied

The operational rules are in the canonical [issue readiness guide](../contributing/issue-readiness.md), together with the existing [PR review gates](../contributing/pr-review.md). Keep those guides actionable and avoid copying the full checklist into this RFC.

The agreed model is:

- **Friendly intake:** reporters describe the church problem or desired outcome; maintainers refine scope, evidence, risk, and acceptance criteria before implementation.
- **Readiness:** resolve blocking product, design, security, permission, and schema decisions before implementation begins. Tiny fixes need proportionate detail; nontrivial work needs verifiable outcomes, scope/non-goals, dependencies, test guidance, risk notes, and human review ownership.
- **Sizing and epics:** size by review burden and risk rather than estimated hours. Use native GitHub sub-issues for multiple meaningful outcomes or cross-subsystem work, preferring vertical end-to-end outcomes while permitting safe, bounded foundation work where needed.
- **PRs:** retain the existing review gates. Keep the diff focused and describe tests, risks, and compatibility implications. Existing hard blocks are authoritative.
- **Beginner issues:** `good first issue` is a maintainer certification against settled design, bounded low risk, actionable starting paths, test guidance, and available human review.
- **Coordination:** check existing issues and PRs before starting; claim work visibly; use early draft PRs for substantial changes; explain supersession and link replacements. Protect active pilot work with narrow, reversible safeguards, not blanket stale-job exemptions.
- **Accountability:** agents may help research, draft, implement, and test, but humans remain accountable for decisions, review, and approval.

## Rollout

Pilot scope (Member Portal and RBAC), the execution checklist, ownership and evidence live in [#10634](https://github.com/ChurchCRM/CRM/issues/10634). This RFC does not duplicate them or activate unmerged operational changes.

Explicitly out of scope: automatic issue closure, automatic milestones, forced labels, subjective CI quality scoring, broad backlog rewrites, and blanket stale-job exemptions. Expanding scope requires a separate maintainer decision.
