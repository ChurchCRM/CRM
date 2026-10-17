# Issue readiness and contributor handoff

> **TL;DR / BLUF**
> Maintainers make work ready; contributors report the problem. Before coding, check existing work, agree on the outcome and acceptance checks, resolve blocking design/security decisions, and claim the issue visibly. Split by independently reviewable user outcomes when that reduces risk. Humans own decisions and approval; agents execute.

**Use this page for issue readiness and decomposition.** For merge requirements, follow the [PR review gates](./pr-review.md). See [How to Contribute](../../CONTRIBUTING.md) for onboarding.

## The fast path

1. **Find existing work.** Search open issues and PRs; update or link related work.
2. **Define the outcome.** State the user problem, expected behavior, scope, and acceptance criteria.
3. **Make it verifiable.** Add relevant evidence, reproduction steps, and tests or observable checks.
4. **Resolve blockers.** Settle product, design, security, permissions, and schema decisions before implementation. Use the existing `Design Decision` label for a real unresolved decision.
5. **Assess risk and size.** Consider touched subsystems, handoffs, authorization/privacy, data/schema changes, upgrade safety, test breadth, and non-generated diff—not estimated hours.
6. **Claim and coordinate.** Comment, claim, or assign using the normal workflow. For substantial work, open an early draft PR. Keep a human maintainer accountable for decisions, review, and approval.

Maintainers refine readiness; reporters do not need to complete this checklist at intake. Match detail to risk: small changes need little ceremony; risky or cross-cutting changes need clear scope, dependencies, verification, and ownership.

## When to use an epic

Use an epic when work has multiple meaningful outcomes, crosses subsystems, or needs coordinated design/security decisions. Use GitHub's native sub-issues and the existing `Epic` label.

- Prefer vertical, end-to-end outcomes that can be tested and reviewed independently.
- Use a bounded foundation task when needed for safety or to unblock later outcomes; state its consumers and safety gates.
- Keep work together when splitting would create unsafe or misleading intermediate states.

## `good first issue` means ready for a beginner

Apply the existing label when design is settled, scope is bounded and low-risk, relevant paths and validation guidance are clear, acceptance criteria are observable, and a human reviewer is available.

## Review for church value and simple UX

- Start with the real job a church staff member or volunteer is trying to do. Prefer outcomes and complete workflows over feature lists or implementation-led explanations.
- Keep the experience understandable and predictable, especially on mobile. Avoid extra steps, settings, or concepts unless they solve a real user problem.
- Write in plain, respectful language that works across roles, technical skill levels, and locales. Explain necessary technical terms; remove hype, jargon, and generic AI-sounding copy.
- Remember the intended audience: churches that need to get organized, especially budget-conscious or underserved churches and those that value owning and hosting their data. Do not assume ChurchCRM is only for small churches or that it fits every church.
- For user-visible changes, check the actual workflow, responsive behavior, accessibility, and localization impact. Verify product claims against current application behavior and documentation.

## Documentation and feature content

- Documentation, screenshots/video, and marketing content are valuable follow-ups for user-visible features, not routine code-PR merge gates.
- During review, note the useful follow-up and link or create one focused issue when it needs tracking. Keep it proportional; bug fixes and security-only changes may not need marketing.
- User documentation must describe released behavior and follow the docs repository's release process. Prepare screenshots/video from the real product with safe demo data.
- Invite non-code contributors to help with how-to guides, screenshots, short walkthroughs, blog posts, and translations. Give them the user problem, confirmed behavior, and a clear destination to contribute.
- Marketing should explain the church outcome and when the feature is available. Do not turn planned or proposed work into a current-product claim.

## Write for the human first

- Lead issues, PRs, docs, and review comments with a short **BLUF/TL;DR** when the work needs context; state the goal and outcome first.
- Keep the main summary short. Put implementation detail, logs, and supporting evidence after it or link to them.
- Describe the current state and what changed. Include non-goals, alternatives, and failure cases only when they affect a real decision or safety boundary.
- Prefer concrete, direct language over boilerplate, repeated summaries, generic assurances, and exhaustive inventories.
- Add code comments for intent or non-obvious constraints. Let clear names, structure, and tests explain straightforward code.
- Report the relevant validation and its result. Test the changed behavior and important security/error boundaries; keep CI focused on meaningful, reproducible checks rather than duplicating locally detectable checks.
- Docs-only PRs skip application build, typecheck/lint, and Cypress workflows. Mixed or application/configuration changes still run the relevant CI.

## Pull requests

Keep each PR focused and its title/body aligned with the actual diff. Summarize the goal, outcome, and relevant validation. Include migration, security, permissions, compatibility, or responsive UI details when they matter to review. The [PR review gates](./pr-review.md) remain authoritative.

## Triage, automation, and accountability

Verify current code and released behavior before declaring work obsolete. Explain supersession and link replacement work. Protect actively discussed, claimed, assigned, or reviewed work from stale automation with narrow, reversible safeguards.

Agents may research, draft, implement, and test; human maintainers remain accountable for product decisions, review, and approval. Use existing labels and GitHub capabilities, and keep the process proportionate to the work.
