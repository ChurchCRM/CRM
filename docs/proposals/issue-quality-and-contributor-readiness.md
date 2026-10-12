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
