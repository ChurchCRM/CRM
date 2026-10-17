# Issue readiness and contributor handoff

> **TL;DR / BLUF**
> Maintainers make work ready; contributors report the problem. Before coding, check for existing work, confirm the outcome and acceptance checks, resolve blocking design/security decisions, and claim the issue visibly. Split by independently reviewable user outcomes when that reduces risk. Humans own decisions and approval; agents execute. Use the existing labels and rules—do not invent new process.

**Use this page for issue readiness and decomposition.** For merge blockers, follow the [PR review gates](./contributing-pr-review.md). See [How to Contribute](../CONTRIBUTING.md) for onboarding.

## The fast path

1. **Find existing work.** Search open issues and PRs before creating or starting anything. Update or link existing work instead of duplicating it.
2. **Define the outcome.** State the user problem, expected behavior, scope, and non-goals where scope could drift.
3. **Make it verifiable.** Add evidence or reproduction steps and observable acceptance criteria/tests. Include upgrade compatibility when relevant.
4. **Resolve blockers.** Settle product, design, security, permissions, and schema decisions before implementation. Use the existing `Design Decision` label only for a real unresolved decision.
5. **Assess risk and size.** Consider touched subsystems, handoffs, authorization/privacy, data/schema changes, upgrade safety, test breadth, and non-generated diff—not estimated hours.
6. **Claim and coordinate.** Comment, claim, or assign using the normal workflow. For substantial work, open an early draft PR. Keep a human maintainer accountable for decisions, review, and approval.

Maintainers refine readiness; reporters do not need to complete this checklist at intake. Scale detail to risk: trivial fixes need little ceremony; risky or cross-cutting changes need explicit scope, dependencies, verification, and ownership.

## When to use an epic

Use an epic when work has multiple meaningful outcomes, crosses subsystems, or needs coordinated design/security decisions. Use GitHub's native sub-issues and the existing `Epic` label.

- Prefer vertical, end-to-end outcomes that can be tested and reviewed independently.
- Use a bounded foundation-only task only when required for safety or to unblock later outcomes; state its consumers and safety gates.
- Keep work together if splitting would create unsafe or misleading intermediate states.
- Do not split by arbitrary file counts or add a new sizing taxonomy.

## `good first issue` means ready for a beginner

Apply the existing label only when design is settled, scope is bounded and low-risk, relevant paths and validation guidance are clear, acceptance criteria are observable, and a human reviewer is available. Otherwise, improve the issue first or leave it unlabelled.

## Pull requests

Keep each PR focused and its title/body aligned with the actual diff. Explain changes, tests/results, risks, and migration or compatibility effects; include desktop/tablet/phone UI validation where applicable. Exclude unrelated refactors and speculative cleanup. The [PR review gates](./contributing-pr-review.md) remain authoritative and are not weakened by this guide.

## Triage, automation, and accountability

Verify current code and released behavior before declaring work obsolete. Explain supersession and link replacement work. Protect actively discussed, claimed, assigned, or reviewed work from stale automation with narrow, reversible safeguards—not blanket exemptions.

Agents may research, draft, implement, and test; they must not replace human accountability or approve their own work. Use existing labels and GitHub capabilities. Do not add mandatory project statuses, subjective CI quality scores, automatic issue closure, or lengthy intake forms for trivial fixes.
