# Issue readiness, sizing, and contributor handoff

This guide defines how ChurchCRM turns community reports into actionable work. Keep intake friendly: reporters should describe the problem or desired outcome, not solve architecture before maintainers have triaged it.

Related: [How to Contribute](../CONTRIBUTING.md) · [PR review gates](./contributing-pr-review.md)

## Intake and maintainer readiness

An issue is ready for implementation when a contributor can understand the outcome, boundaries, relevant evidence, and how to verify the change without guessing at unresolved product or security decisions. Maintainers own readiness; reporters are not expected to complete every item at intake.

For implementation-ready issues, record what applies:

- **Outcome and scope:** user problem, expected behavior, boundaries, and explicit non-goals when scope could drift.
- **Evidence:** reproduction steps, affected workflow, current behavior, relevant code or released behavior, and links to related issues or PRs.
- **Verification:** expected tests or observable acceptance criteria, including upgrade compatibility when relevant.
- **Risk:** affected subsystems and handoffs; permissions/security, data exposure, schema/data migration, upgrade compatibility, performance, and test implications.
- **Decision status:** resolve blocking design questions before implementation. Use the existing `Design Decision` label when a real decision is pending; do not use it as a generic status.
- **Ownership and coordination:** check for existing work first. Before starting, comment, claim, or assign the issue using the repository's normal workflow. For substantial work, open an early draft PR to align on direction.

## Size work by review burden and risk

Do not size issues by estimated hours. Consider the size and risk of the diff a maintainer must understand: number of subsystems, cross-component handoffs, authorization or privacy implications, schema/data changes, upgrade safety, test breadth, and non-generated code.

Create an epic when work contains multiple meaningful outcomes, crosses subsystems, or has unresolved design/security decisions that need coordination. Prefer vertical, end-to-end child issues that deliver useful outcomes. A bounded foundation-only issue is appropriate when it is required to make later work safe or possible. Use GitHub's native sub-issues and the existing `Epic` label; do not introduce a new sizing taxonomy.

A large change is not automatically wrong. Keep work together when splitting would create unsafe or misleading intermediate states. Otherwise split by independently reviewable outcomes, not arbitrary file counts.

## PR expectations

A PR should have one coherent purpose and a title/body that match the current diff. Link an issue when required by [the PR review rules](./contributing-pr-review.md). Explain the implementation, tests and results, risks, and any migration or compatibility effect. Include UI validation for desktop, tablet, and phone where applicable.

Keep generated files, unrelated refactors, and speculative cleanup out of the diff. If scope changes materially, update the issue/PR description or split the work. Existing hard blocks in the PR review guide remain authoritative; this page does not weaken them.

## `good first issue` is a maintainer certification

Apply the existing `good first issue` label only when all of these are true:

- The design and expected behavior are settled; no blocking architecture, product, or security decision remains.
- Scope is bounded and low risk for a first contribution.
- Relevant files/paths and a test pattern or concrete validation instructions are identified.
- Acceptance criteria describe an observable result.
- A human reviewer is available to help and review the contribution.

If an issue does not meet these conditions, improve it first or leave it unlabelled. Do not label issues merely because they look small or because a contributor needs something to work on.

## Triage and automation

Before creating work, search existing issues and PRs. Prefer updating, splitting, superseding, or closing an existing issue with a clear explanation over creating duplicates. Verify the current code and released behavior before declaring work obsolete.

Stale automation must not close actively discussed, claimed, assigned, or reviewed pilot work by accident. Use narrow, reversible safeguards for specific cases; do not create blanket exemptions. Agents may research, draft, implement, and test, but a human maintainer remains accountable for product decisions, review, and approval. An agent must not approve its own work.

## Keep the process lightweight

Use existing labels and GitHub capabilities. Do not add new labels, mandatory project statuses, subjective CI quality scores, or lengthy intake forms for trivial fixes. A reporter-friendly issue can be accepted and then refined by maintainers before implementation.
