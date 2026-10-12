# RFC: Issue Quality, Epic Sizing, PR Scope, and Good First Issue Readiness

**Status:** Proposal — review required; not yet policy  
**Scope:** ChurchCRM repositories, piloted in `ChurchCRM/CRM`  
**Decision owner:** ChurchCRM maintainers; final approval by project lead

## Goals
1. Make every actionable issue understandable, verifiable, and independently implementable.
2. Avoid duplicate/stale work by checking current code and merged releases before triage.
3. Keep volunteer contributions approachable, reviewable, and safe.
4. Break broad work into focused PRs with explicit dependencies and acceptance criteria.
5. Make `good first issue` a trusted promise that design and scope are already settled.
6. Avoid imposing unnecessary paperwork on small fixes or community feature suggestions.

## Non-goals
- Automatically closing old issues or forcing a rewrite of the backlog.
- Requiring newcomers to propose architecture before contributing.
- Treating time estimates as commitments or performance metrics.
- Enforcing subjective design quality with CI.
- Changing GitHub templates, labels, or branch protection in this RFC PR.

## Lifecycle: request → triage → ready → PR → closure

### Intake (reporter-friendly)
Accept imperfect bug reports and feature ideas. Templates should ask for the problem, expected outcome, steps/environment for bugs, and screenshots where useful. **Do not require reporters** to specify code locations, tests, or an architecture.

### Maintainer triage
Before marking an issue ready:
- Verify current behavior in the canonical repository; check existing code, merged PRs, releases, and related issues.
- Decide whether to **keep, clarify, split, supersede, close as completed, close as duplicate, or decline**.
- State the user outcome, scope, non-goals, acceptance criteria, dependencies, and risks.
- Identify unresolved product/architecture/security decisions and escalate before implementation.
- Remove stale milestones from unplanned work. Avoid creating duplicate issues just to rewrite a title.
- Leave a short explanatory comment when closing or superseding; link replacements both ways.

### Ready-for-development contract
An actionable issue should provide, proportionate to its size:
1. **Problem / who is affected** and verified current behavior.
2. **Expected outcome** and observable acceptance criteria.
3. **In scope / out of scope** (especially adjacent feature requests).
4. **Implementation guidance**, relevant paths/patterns, and known decisions; not a mandatory design dictated by the reporter.
5. **Dependencies / related issues**, including sequencing and release constraints.
6. **Validation**: test types/commands where known; security, privacy, accessibility, localization, mobile usability, documentation as relevant.
7. **Completion evidence** expected in the PR (test output, screenshots for UI, migration checks when relevant).

For tiny changes, a short issue with a precise location, desired correction, and test/verification is sufficient. Missing information means **needs triage/design**, not rejection of a contributor's report.

## Size and decomposition guidance

| Class | Typical focused implementation | Use when |
| --- | --- | --- |
| Good first issue | ~1–4 hours for someone familiar with the area | Fully designed, low-risk, one clear result; newcomer onboarding time may be longer |
| Small | Up to ~1 day | One outcome and one focused PR |
| Standard | ~1–3 days | Cohesive change with explicit validation |
| Large | ~3–5 days | Closely coupled work; maintainer explains why splitting would harm correctness |
| Epic | >~5 days **or** multiple independently deliverable outcomes | Coordinates child issues, dependencies, and reviewable milestones |

These are heuristics, not SLAs. **Split based on independent outcomes and risk, not only estimated time.** Consider an epic earlier if work spans several subsystems, requires decisions on permissions/data model, or cannot be safely reviewed as one PR. Security or transactional consistency may justify one larger atomic change.

### Epic contract
An epic contains a user/business goal, approved boundaries, high-level design and decisions, explicit non-goals, child issue checklist, dependencies/order, risks, and closure criteria. Children must have independent acceptance criteria, a small reviewable scope, and tests. An epic is **not** an implementation-ready ticket for a coding agent. Don't mark the epic `good first issue`.

### When to split an issue
Split when parts can be delivered and verified separately, have distinct owners or dependencies, or require different approvals. Do **not** split into empty scaffolding PRs that leave broken or insecure intermediate states. Link each child to its parent and state what remains in the parent.

## Good first issue: certification, not a difficulty guess
A maintainer may add `good first issue` **only when all apply**:
- [ ] The behavior and approach are approved; no open product, architecture, schema, or permission decisions.
- [ ] One small, meaningful, independently testable outcome (target ~1–4 focused hours).
- [ ] Exact starting points (files, entry points, or reproducible steps) and setup/test guidance are available.
- [ ] Clear acceptance criteria and expected tests; documentation/localization instructions where relevant.
- [ ] No sensitive data exposure, new authentication boundary, destructive migration, or complex deployment risk.
- [ ] Dependencies are merged/available; no hidden prerequisite work.
- [ ] A maintainer can review the PR promptly and help answer questions.

Examples that **may qualify after design is settled**: copy/help-text correction with locale guidance; isolated UI badge using an existing data contract; targeted regression test with a reproducible failing case. Examples that **do not qualify**: define visitor semantics; invent a new event-location API; redesign kiosk authentication; migrate a PDF engine.

If a task becomes blocked or design-heavy, remove the label and explain why. Prefer a small curated set of ready tickets over labeling an entire epic. Don't guarantee a fixed completion time to first-time contributors.

## PR quality and scope
A PR should:
- Link its issue (or explain why a trivial maintenance PR has none).
- Explain **what changed and why**, including user impact; state non-goals when scope could be confused.
- Keep a focused change set; separate unrelated refactors, formatting, generated assets, and dependency upgrades.
- Show test evidence and limitations; CI passing is necessary but not always sufficient.
- Address permission/privacy boundaries, migrations, accessibility, localization, and documentation when relevant.
- Include before/after screenshots or short recordings for meaningful UI changes, without real church/person data.
- Call out breaking changes, deployment steps, rollback concerns, and follow-up issues.
- Not merge solely because an automated agent generated it; maintainers review actual code and tests.

For stacked or dependent PRs, state the base, merge order, and dependency. Prefer one reviewable deliverable per PR; atomic cross-layer changes are acceptable when required for safety.

## Suggested labels (audit existing labels before creating any)
- Existing type/domain labels remain authoritative.
- Consider `needs-design` and `ready-for-dev` only if no equivalent already exists.
- `good first issue` is applied only after the checklist is satisfied.
- Epics use the existing `Epic` label where present.
- Do not use `Stale` or a milestone as a proxy for scope or priority.
- Do not apply size labels until there is demonstrated benefit.

## Automation: low friction, not bureaucracy
Potential future checks: broken links in docs, template presence in PR descriptions, and epic-child link hygiene. CI should not block community feature requests for missing maintainer-only fields or attempt to infer complexity/quality from text. Prefer existing local/pre-push checks over duplicating cheap validations in CI.

## Pilot: Events and Kiosk backlog
Review #8821, #8822, #8825, #9525, #5238 and #8575–#8583, #8826, #8650, #8647, #3902, #8978. For each: verify current code and release status; assign disposition (keep / update / split / supersede / close), identify minimal remaining deliverables, and **only then** consider contributor readiness. Keep reporting (#10538), messaging (#8693), and PDF modernization (#8151) separate where applicable.

## Rollout and acceptance criteria
1. Obtain maintainer review and settle open questions below.
2. Update `CONTRIBUTING.md` and existing issue/PR templates with lightweight links to the approved guide.
3. Audit existing labels and avoid duplicate classifications.
4. Pilot against the event backlog; leave evidence-based comments for closures and create only necessary, scoped follow-ups.
5. Curate 3–5 truly ready beginner issues; verify the guidance by having a new contributor attempt one.
6. Revisit after the pilot using feedback, not issue counts alone.

**This RFC PR intentionally changes no live templates or labeling rules.**

## Questions for reviewers
1. Are the proposed size thresholds appropriate for volunteer contributors?
2. Which fields are truly required for tiny bug fixes versus larger features?
3. Should `ready-for-dev` be a label, a project status, or neither?
4. What should qualify as an epic besides effort (cross-subsystem scope, decisions, risk)?
5. Is the good-first-issue checklist too strict or missing a safeguard?
6. Should we pilot only in CRM before applying to docs/website?

## Decision boundary: what this PR merges vs what ships later

**This PR is a design RFC, not policy activation.** Approval of the RFC means maintainers agree on the intended standards and rollout, not that current templates, labels, agents, or CI have already been updated. Until implementation PRs merge, existing repository instructions remain in force.

### What may land on `master` when this RFC merges
- This reviewed RFC under `docs/proposals/`, with its status changed to **Accepted / implementation pending** only after explicit maintainer approval.
- A clear decision record: approved issue-quality minimum, sizing/epic rules, PR contract, `good first issue` criteria, and owners for rollout.
- A linked implementation tracking issue or checklist. The RFC alone must not be presented as an enforced contributor policy.

### Required follow-up changes to `master` after approval
| Surface | Required outcome | Verification |
| --- | --- | --- |
| Canonical policy in CRM contributor docs | Publish a concise approved issue/PR quality guide, linked from `CONTRIBUTING.md`. Keep this RFC as decision history, not the only instructions. | A contributor can find the current rules from the repository entry point. |
| `.github/ISSUE_TEMPLATE/` | Review existing bug and feature templates; request reporter-friendly problem/repro information, while reserving technical design, scope, and acceptance-criteria refinement for maintainers. | Sample bug and feature submissions are usable without knowing implementation details. |
| `.github/PULL_REQUEST_TEMPLATE.md` | Link the guide; require concise why/what, issue links, scope, tests/evidence, risks and rollout notes **when applicable**, without bloating trivial PRs. | Example small fix and larger feature PR both fit the template. |
| `.agents/` and relevant repository agent instructions/skills | Audit **existing** issue-triage, planning, epic, coding and PR-review instructions; update their workflows to check duplicates/current code, choose appropriate issue size, require approved design before implementation, and link to the canonical guide. Avoid duplicate/conflicting policy copies. | Agent-generated issue, epic, and PR examples conform to the approved guide. |
| Labels and triage workflow | Audit labels first; document who can apply/remove `good first issue`, when design is pending, and how superseded/stale issues are closed. Create labels only if existing equivalents do not suffice. | Label usage matches the readiness checklist; no unreviewed beginner tickets. |
| Related repositories | Identify docs, website, and other ChurchCRM contributor/agent entry points and link to the canonical standard, with repository-specific exceptions where needed. | No conflicting requirements or duplicated independent policies. |
| Epic/backlog pilot | Audit the Events/Kiosk issue cluster against actual code and releases; keep/close/split/supersede with links, and prepare 3–5 truly ready beginner issues only if qualified. | Each disposition has evidence and each new issue is independently actionable. |

### Proposed implementation sequence and ownership
1. **Maintainer decision (George):** Approve or amend thresholds, epic triggers, minimum issue/PR content, good-first certification, and cross-repository scope.
2. **Docs/templates PR (Claude):** Publish the approved canonical guide and update CRM `CONTRIBUTING.md` and GitHub templates. Keep it small and independently reviewable.
3. **Agent-instructions PR (Claude; ChatGPT reviews):** Update relevant existing `.agents/` skills and agent entry points to **reference** the guide. Do not fork the policy into multiple divergent copies.
4. **Labels/triage review (ChatGPT; George approves any policy changes):** Reconcile existing labels and triage practices.
5. **Events/Kiosk pilot (ChatGPT prepares; George approves material scope decisions):** Verify implementation status and curate a starter backlog. Other repositories adopt the standard after CRM pilot review.

### Definition of done for the overall initiative
- [ ] Approved rules are discoverable on `master` from contributor entry points, not solely the RFC.
- [ ] Issue templates distinguish community intake from maintainer-ready implementation tasks.
- [ ] PR template and agent instructions use the **same canonical standard**.
- [ ] Epic decomposition and readiness checks are represented in agent planning/review workflows.
- [ ] Existing labels are audited; `good first issue` requires explicit maintainer certification.
- [ ] A sample bug, feature, epic, small PR, and beginner issue have been checked against the guide.
- [ ] Events/Kiosk pilot records evidence-based disposition and avoids duplicate issues.
- [ ] Rollout PRs/issues are linked from this RFC and any deviations are documented.

### Explicitly deferred
- No automatic issue closure, milestone assignment, or label application in the RFC.
- No subjective quality gate that blocks community issue submissions.
- No sweeping rewrite of every old issue or repo before the pilot.
- No mandatory effort estimate or elaborate design document for trivial fixes.
- No release/merge action without George's approval.

### Reviewer decision checklist
Reviewers should comment on (1) the **minimum standard** for issues and PRs, (2) when to create epics and children, (3) the certification threshold for `good first issue`, (4) canonical documentation ownership, (5) which agent instructions and templates must change, and (6) whether the proposed rollout sequence is appropriately incremental.
