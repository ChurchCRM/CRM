# RFC: Design portfolio, community feedback and implementation prioritization

> **BLUF**
> Accepting a design does not schedule it. Church users give feedback in maintainer-authored GitHub Discussions; maintainers decide architecture in RFC pull requests and schedule work as scoped issues. Votes and reactions are a demand signal, never approval, a promise or a release date. Issue readiness, sizing and PR gates stay as defined in [RFC 0001](0001-issue-quality-and-contributor-readiness.md) and the [issue-readiness guide](../contributing/issue-readiness.md).

**Status:** Proposed  
**Date:** 2026-10-17  
**Owner:** George Dawoud  
**Rollout:** [#10545](https://github.com/ChurchCRM/CRM/issues/10545) lifecycle docs · [#10546](https://github.com/ChurchCRM/CRM/issues/10546) candidate check · [#10544](https://github.com/ChurchCRM/CRM/issues/10544) notification hardening · [#10550](https://github.com/ChurchCRM/CRM/issues/10550) portfolio index  
**Related:** [#10543](https://github.com/ChurchCRM/CRM/issues/10543)

Until George accepts this RFC, current repository rules apply.

## Authority

ChurchCRM is free and volunteer maintained. A design can be agreed long before anyone can build it. Community demand helps choose *what* matters; maintainers decide *how* to build it safely.

Source of truth, highest first:

1. `ChurchCRM/CRM` code, tests, schema and shipped behavior
2. `ChurchCRM/docs.churchcrm.io` for released documentation
3. `ChurchCRM/marketing` for approved outward messaging
4. Release tags and notes for what shipped
5. Issues, RFC threads and Discussions for planned, not shipped, behavior

The feature requirement lives in its GitHub issue and the technical decision in its RFC PR. [#10550](https://github.com/ChurchCRM/CRM/issues/10550) is an index only. George approves significant product and architecture decisions, security and privacy policy, spending, publication, release scope and merges.

## Two linked artifacts

Church staff should never need to read a pull request to have a voice.

| Layer | Purpose | Where |
|---|---|---|
| Feature proposal | The church problem, who benefits, the workflow and a few focused questions, in plain language. No schema, no architecture, no release promise | GitHub Discussions, **Feature Proposals** category (Announcement format) |
| Technical RFC | Architecture, security, privacy, data model, permissions, alternatives, non-goals, i18n, tests, rollout | A `docs/decisions/NNNN-slug.md` file in a draft PR, reviewed line by line |
| Implementation | Contributor-sized work with acceptance tests | Issues and PRs; milestones only when scheduled |
| Portfolio | Status of each proposal | [#10550](https://github.com/ChurchCRM/CRM/issues/10550) |

Use the **RFC Reviews** category for a short invitation that links the PR and the plain-language issue. Keep the detailed thread in the PR.

### Discussions rules

- Maintainers create Feature Proposals posts; signed-in GitHub users comment and react. Do not use *Lock discussion* to publish, because it blocks comments.
- One post per user-facing feature. It links the canonical issue and RFC and states one status: proposed, design approved but unscheduled, seeking contributor, planned, shipped or deferred.
- Only maintainer-authorized proposals reach the public category. Community ideas arrive through issues or Discord and are triaged first.
- Comments and reactions are participation signals. Church workflow, accessibility, international needs, security, feasibility and contributor availability also count. Moderation follows the Code of Conduct.
- GitHub sign-in is required; say so rather than promising account-free voting.
- Add no voting service, wiki copy, database, widget or Project board.
- Verify Announcement format and non-maintainer comment and create behavior with a signed-in test account before promoting the category.

## Choose the work type

1. **Bug, regression, vulnerability, data loss, accessibility, performance, CI, dependency or maintenance:** an ordinary issue. Votes never delay a safety or reliability fix. Report sensitive vulnerabilities through the security process.
2. **Bounded improvement:** an issue and a focused PR with outcome, non-goals and tests.
3. **Several independently shippable changes:** an epic with ordered children. An epic is not automatically an RFC.
4. **Unresolved consequential choices** (security, permissions, personal or financial data, schema, cross-module API, long-term operations): an RFC.
5. **Optional church-facing capability:** a feedback candidate once the problem and boundaries are clear. RFC and feedback eligibility are independent.
6. **Sensitive or internal decisions:** gather targeted journeys; never publish for a popularity vote.

Individual issue classifications live in #10550, not here.

## Lifecycle

| State | Evidence in the canonical issue or RFC | Milestone |
|---|---|---|
| Discovery | Use case, current behavior, alternatives, missing facts | No |
| Design review | Goal, options, tradeoffs, security, privacy, accessibility, non-goals, questions | No |
| Design accepted, unscheduled | Maintainer decision and date, accepted and rejected options, risks | No |
| Seeking contributor | Bounded children, dependencies, acceptance tests | Not by default |
| Planned | Explicit scheduling decision and owned implementation issues | Yes, on those issues only |
| Shipped | Merged code, release notes, docs; Discussion updated | Resolve the issue |
| Deferred or declined | Rationale and conditions to reconsider | No |

- A design RFC is complete when the decision is recorded. An implementation issue is complete when tested code ships.
- Never give a backlog epic, design proposal, Discussion or not-planned issue an automatic milestone.
- Certify `good first issue` only under the [issue-readiness guide](../contributing/issue-readiness.md).
- Add no labels or Project fields without a reviewed taxonomy; use status text in the issue.

## Portfolio upkeep

When an RFC opens, is accepted, deferred or ships: find the canonical issue and PR, check duplicates and current behavior, record the decision on the RFC, update the #10550 entry (type, gate, status, links, last-reviewed date) and link children with GitHub references. Do not equate vote counts with capacity. After release, verify docs and update the Discussion. Review aged proposals and defer them explicitly instead of assigning dates.

## Privacy and in-app notices

This RFC enables nothing. Publication needs George's explicit approval.

- No ChurchCRM voting database, third-party script, cookie, tracker, pixel or iframe.
- Outbound feedback links carry no church, user, session, installation, email or domain identifiers. Campaign identifiers are identical for every installation. Opening GitHub exposes network and browser details to GitHub; say so.
- Public votes never override fixes for vulnerabilities, data loss, privacy, accessibility, reliability or upgrades.
- Document moderation, abuse handling and publish permissions before launch.

`NotificationService` reads GitHub-hosted JSON at login with version patterns, admin-only targeting and per-user dismissal. Campaign expiry, frequency caps, locale fallback and URL allowlisting are open work in [#10544](https://github.com/ChurchCRM/CRM/issues/10544). A notice needs a safe HTTPS destination, a unique dismissible campaign ID, expiry, a frequency cap, locale fallback, admin-only and version targeting for the pilot, graceful failure, new-tab safety, and no request to GitHub from merely viewing a page.

## Roles

Maintainers own technical truth and approval. Coding agents implement approved bounded tasks and tests; humans stay accountable (RFC 0001, Q7). Community volunteers give feedback and help test, translate and build. Public communication needs an authorized owner.

## Rollout gates

Approve this RFC → verify category permissions → verify candidates against code and releases → harden notifications → approve copy, privacy and moderation → George authorizes publication → administrator-only pilot → wider rollout by approval → 60-day review → continue, change or stop. The checklist and evidence live in the rollout issues above.
