# RFC, community feedback, and implementation prioritization

> **Status: proposed policy; not adopted until approved and merged.** This document is the proposed canonical operating guide. The current portfolio index is [CRM #10550](https://github.com/ChurchCRM/CRM/issues/10550), governance RFC [#10543](https://github.com/ChurchCRM/CRM/issues/10543), and issue quality proposal [PR #10539](https://github.com/ChurchCRM/CRM/pull/10539). Until approved, established repository rules take precedence.

## Purpose and authority

ChurchCRM is free and volunteer maintained. The project may agree with a feature's design long before anyone can implement it. **Design approval does not promise implementation, volunteers, a release date, or a milestone.** Community demand helps choose *what* matters; maintainers decide *how* to build it, safely and sustainably.

Source-of-truth order:
1. Current `ChurchCRM/CRM` code, tests, schema, and actual feature behavior.
2. `ChurchCRM/docs.churchcrm.io` for released user/admin documentation.
3. `ChurchCRM/marketing` for approved outward-facing messaging and campaign preparation.
4. Released tags/notes to establish what shipped.
5. Issues, proposal/RFC threads and discussions for **planned**, not asserted shipped, behavior.

The canonical feature requirement lives in its GitHub issue; the reviewed technical decision lives in the linked RFC PR. The [RFC portfolio #10550](https://github.com/ChurchCRM/CRM/issues/10550) is only an **index**, not a replacement source of truth. A user-facing feedback page or GitHub reaction is an **expression of user demand**, not an implementation specification.

George approves significant product/architecture decisions, sensitive security/privacy policy, spending, publication, release scope, merges, and removal. Contributors can offer designs, feedback, code, translations and tests.

## Two kinds of proposal: church use case and technical RFC

**Church users are not expected to understand pull requests, GitHub branches, database schemas, or technical RFCs.** Distinguish the question **“What should ChurchCRM help my church do?”** from **“How should ChurchCRM implement this safely?”**

| Layer | Audience | What belongs there | Location and feedback |
| --- | --- | --- | --- |
| **Feature / use-case proposal** | Church administrators, pastors, volunteers and everyday operators | Short human-language problem, who benefits, examples of current pain, what success looks like, optional screenshots, questions about real workflows | A plain-language **public feature page** on ChurchCRM.io (proposed, not live), linking to a canonical GitHub **feature issue** for durable discussion and 👍 reactions where GitHub login is acceptable; optionally link the existing ChurchCRM feedback form for users without a GitHub account. **Do not require PR reviews.** |
| **Technical/design RFC** | Maintainers, developers, security/privacy/accessibility reviewers | Current code and released behavior, architectural alternatives, tradeoffs, schema/migrations, auth, data protection, i18n, testing, rollout and a recorded decision | Markdown in `docs/proposals/` with a **draft pull request** for line-by-line technical review, linked from the parent feature issue |
| **Implementation issues** | Contributors and maintainers | Small separately testable tasks, acceptance criteria, dependencies, risks, release scope and PRs | GitHub Issues and pull requests; milestone only after scheduling |
| **Portfolio** | Maintainers, interested community | Where the feature proposal, RFC, feedback, and children stand; no duplicate design text | [#10550](https://github.com/ChurchCRM/CRM/issues/10550); optional GitHub Project later |

### How one proposal moves through the system

1. **Listen:** Receive a simple church workflow request through the website/feedback form, existing GitHub issue or Discord; triage duplicates and summarize the problem on **one canonical feature issue**.
2. **Explain it publicly:** After maintainer review, prepare a short, accessible, mobile-friendly feature/use-case page. It is explanatory content, **not** a technical spec, and makes no promise to ship. Website source is version controlled in `ChurchCRM/ChurchCRM.io`, not the CRM Wiki.
3. **Gather reactions and comments:** If GitHub Discussions remains **disabled**, do **not** link to it. Use reactions/comments on the existing feature issue for GitHub users, and the **existing feedback form** for people who do not use GitHub. The form's privacy, ownership and triage must be validated before routing a campaign to it. Do not build a new polling application.
4. **Design it:** Only when there are meaningful unanswered decisions, write a technical RFC and review it in a draft PR. Link RFC and feature issue **both ways**. Technical approval does not approve the priority or release.
5. **Prioritize it:** Maintainer weighs church feedback alongside security, accessibility, effort, global relevance and volunteers' capacity. The public page accurately shows whether the idea is under consideration, design accepted but unscheduled, seeking contributors, planned, shipped or deferred.
6. **Build it:** Split accepted work into scoped issues; assign implementation milestones only when explicitly planned. Close with verified release and documentation links.

### Example — giving by person versus family

**Feature/use-case description, for church users:** “When two adults in a family give separately, our treasurer needs to record each gift under the right person while still seeing the family total. Would this help your church, and how do you prepare year-end statements?” Link [#9313](https://github.com/ChurchCRM/CRM/issues/9313) for feedback.

**Technical RFC, for developers:** Should donations have an optional person ID? How are migrations, spouse privacy, account permissions, reports, statement attribution and backward compatibility handled? Include alternatives, security testing and data migration. **This is independent from online payment gateways** ([#10033](https://github.com/ChurchCRM/CRM/issues/10033)).

**Implementer issues:** After approval, isolate migration, entry UI, reports/statement behavior, role permissions and tests; do not open or milestone implementation work merely because churches upvoted.

### Public feedback availability and boundaries

- **GitHub Discussions is currently disabled according to the maintainer.** Enabling it is a separate explicit decision; never state it is live or send churches to a dead Discussions URL.
- GitHub **Issues comments/reactions require GitHub login**. A public page without a usable feedback path is not a voting platform. Validate an existing low-friction form path rather than promising anonymous votes.
- Use plain wording: **“Tell us how your church works”** and **“I'd use this”**, not “Approve RFC”, “Review PR”, “Upvote architecture”, or “Funded roadmap”.
- A feature page must not include sensitive church/member details, unaudited claims about shipped features, unique tracking IDs or guaranteed timelines.
- No automatic third-party requests when a user only opens ChurchCRM; any optional in-app notice links to a reviewed public ChurchCRM page.
- **No new provider, dedicated voting tool, GitHub Project board, or Discussions re-enablement is required by this proposal.** Revisit only if evidence shows existing feedback paths are insufficient.

## Intake: decide what type of work this is

1. **Bug, regression, vulnerability, data loss, accessibility, performance, CI, dependency or maintenance fix**: ordinary issue, with severity, reproduction and acceptance tests. Do **not** delay a safety or reliability fix for votes. Privately report sensitive vulnerabilities through the security process rather than public polls.
2. **Clear bounded improvement**: normal issue (and focused PR); define outcome, non-goals, existing behavior and tests.
3. **Several independently deliverable changes**: an **epic** with ordered children, dependencies and completion conditions; an epic is not automatically an RFC.
4. **Unresolved consequential alternatives** affecting security, permissions, personal/financial data, schema/migration, cross-module API, long-term operations or complex user workflows: **RFC**. Ask reviewers to choose among real options, describe consequences, and record the decision.
5. **Church-facing optional capability with competing priorities**: assess as a **community feedback/voting candidate** once the problem, current status and feasible boundaries are understandable. RFC and voting eligibility are independent.
6. **Sensitive or internal decisions**: collect targeted user journeys if needed, but do not publish details for popularity voting.

### Real repository examples

| Issue | Classification and action |
| --- | --- |
| [#10033 Online Giving](https://github.com/ChurchCRM/CRM/issues/10033) | Financial workflow and gateway architecture **RFC**, with **separate** user-demand feedback on giving needs. Never delegate security/provider integration design to votes. |
| [#9313 Person vs family contributions](https://github.com/ChurchCRM/CRM/issues/9313) | **Separate financial data-model/product RFC and voting candidate**: person-level, household-level or optional both; not a child voting option for online payments. Assess privacy, reporting, statements and migration. |
| [#10463 Universal Member Portal sign-in](https://github.com/ChurchCRM/CRM/issues/10463) | Identity and recovery **RFC**, plus community discovery; architecture and auth rules remain maintainer decisions. |
| [#8694 Multi-campus](https://github.com/ChurchCRM/CRM/issues/8694) | Collect real church demand first, then RFC for branch scoping, data isolation and financial/reporting boundaries. |
| [#5238 Event Locations](https://github.com/ChurchCRM/CRM/issues/5238) | Existing implementation **epic**. Audit shipped work and children; not automatically another RFC. |
| [#10542 Email-system audit](https://github.com/ChurchCRM/CRM/issues/10542) | Shared SMTP/From/Reply-To/CC/privacy **audit and RFC**. Gather reply-use-case feedback, **not** a public security popularity vote. |
| [#10540 Event roster N+1](https://github.com/ChurchCRM/CRM/issues/10540) | Bounded performance **issue**, not an RFC or voting candidate. |
| [#10485 Deleted-person attendance rows](https://github.com/ChurchCRM/CRM/issues/10485) | Focused retention/privacy **decision**, then bug fix; no vote. |
| [#9525 Event attendance hardening](https://github.com/ChurchCRM/CRM/issues/9525) | Implementation/security **epic**; RFC only for unresolved shared access policy. |
| [#10538 Single-event report](https://github.com/ChurchCRM/CRM/issues/10538) | Normal scoped feature once data/permission/PDF questions are resolved; potential **user-value vote**, not automatically an RFC. |
| [#8693 SMS/WhatsApp](https://github.com/ChurchCRM/CRM/issues/8693) | Cross-cutting platform proposal if pursued, **not an Events-specific dependency**. |
| [#10181 Sensitive needs/volunteer matching](https://github.com/ChurchCRM/CRM/issues/10181) | Privacy-sensitive discovery/RFC; do not advertise sensitive workflows for a public vote before review. |

## Lifecycle and what GitHub records

| State | Required evidence in canonical GitHub issue/RFC | Milestone? |
| --- | --- | --- |
| **Discovery** | Church use case, current behavior, alternatives, missing facts | No |
| **RFC / design review** | Goal, affected workflows, options, tradeoffs, security/privacy/accessibility, non-goals, code sources, open questions, requested feedback, reviewers | No |
| **Design accepted, awaiting prioritization** | Explicit maintainer decision and date, accepted choices, rejected alternatives, unresolved risks | **No** |
| **Community feedback** (optional, parallel to review or after design acceptance when safe) | One church-facing feature page and feedback path per **user-facing parent outcome**, concise question, clear status; community demand and comments summarized back in GitHub | No |
| **Seeking contributor** | Bounded children, dependencies, acceptance tests and handoff; owners may volunteer | Not by default |
| **Planned** | Explicit maintainer scheduling decision, target version/window and owned implementation tasks | **Yes, only planned implementation issues** |
| **In progress** | Assigned PRs and validated test status | As planned |
| **Shipped** | Merged code, release tag/notes, updated docs and voting result | Resolve implementation issue |
| **Deferred / declined** | Rationale and conditions for reconsideration | No |

- The RFC is complete when the **design decision is recorded**; implementation can remain unscheduled indefinitely.
- An implementation issue is complete when the **tested implementation ships or is verified on the target branch**, not when an RFC is approved.
- Do not classify unscheduled design as `good first issue`. Follow the certification rules in PR #10539 once adopted.
- Avoid automatic milestones for backlog epics, design proposals, voting posts and not-planned issues.
- Do not invent statuses or add new GitHub labels/Projects fields without a reviewed taxonomy. Use explicit status text in issues first; an optional GitHub Project may later provide sortable views.

### Minimum issue/RFC metadata

In the description or a compact top section:
- **Problem and intended user outcome**; current verified baseline and affected software version.
- **Type:** normal issue / epic / RFC / voting candidate (can overlap where appropriate).
- **Design status:** discovery / in review / accepted-unscheduled / deferred / declined.
- **Decision owner and date**, reviewer questions, alternatives and supporting evidence.
- **Links:** RFC, portfolio #10550, parent/child implementation issues, docs, and external feedback entry if approved.
- **Scope, non-goals, dependencies, risks, acceptance criteria, tests, security/privacy/i18n/mobile implications.**
- **Implementation status and milestone only when truly scheduled.**
- **Last verified date** and links to current code or release; do not turn memory or historical comments into product claims.

## Portfolio triage operating procedure

Use [#10550](https://github.com/ChurchCRM/CRM/issues/10550) as the **index**. On an RFC/proposal being opened, redesigned, accepted, deferred, or shipped:
1. Locate the canonical issue/PR and verify duplicates and current product state.
2. Add/update its portfolio entry with type, decision gate, feedback eligibility, status, links and last-reviewed date.
3. Record the actual decision **on the canonical RFC**, not merely the index.
4. Link related children using GitHub references; do not create duplicate feature issues for each workstream.
5. When feedback or contributor offers arrive, summarize what changes a decision or schedule. Do not equate vote counts with funded capacity.
6. When implementation ships, verify release and docs, then update the original RFC/feature entry and public feedback status.
7. Audit aged proposals periodically; defer explicitly instead of assigning artificial release dates.

A GitHub Project with status/owner/feedback/contributor fields may later mirror this index if George approves. The issue-based process works without new project administration.

## Public feedback and privacy: proposed policy

**Nothing in this section authorizes a live public campaign or creates an external account.** See [RFC #10543](https://github.com/ChurchCRM/CRM/issues/10543).

- **Reuse existing channels first:** ChurchCRM.io for readable feature pages; GitHub feature-issue comments/reactions for GitHub account holders; existing church-feedback form for nontechnical users after privacy/ownership verification. GitHub Discussions is disabled according to the maintainer. Do not select Fider or another platform by default.
- Do **not** build a ChurchCRM voting database, host a new service, or embed provider scripts, cookies, trackers, pixels or iframes in ChurchCRM.
- Each public feature entry describes one user problem, who benefits, realistic constraints and links the canonical GitHub issue. The technical decision stays on GitHub.
- A vote is a demand signal, **not** consent to a design, a delivery promise, an implementation assignment or a guaranteed release date.
- No church, user, session, installation, database, email, domain-specific or telemetry identifiers go in outbound feedback links. Generic campaign identifiers must be identical for all installations; explain that clicking external links necessarily exposes network/browser information to the provider.
- Public votes must never override fixes for vulnerabilities, data loss, privacy, accessibility, reliability, upgrades or required maintenance.
- Moderation, data export, abuse responses, the provider's account/privacy notice and publish permissions must be documented **before** launch.

### Existing in-app notification integration

The existing `NotificationService` reads GitHub-hosted notification JSON at login, caches it in session, supports version patterns, admin-only targeting and per-user dismissal via `UiNotification`. It does **not** by itself prove campaign expiry, robust frequency controls, locale-specific copy or external URL allowlisting. [#10544](https://github.com/ChurchCRM/CRM/issues/10544) tracks the hardening and tests; no campaign is authorized by the issue.

Proposed administrator-only invitation:

> **Help shape ChurchCRM's future**  
> We're considering improvements to ChurchCRM and would love your input. Tell us what would help your church most.  
> **Action:** Share your feedback. **Dismiss:** Not interested.

Notification requirements: no interruptive modal, safe HTTPS destination, explicit external destination, unique dismissible campaign ID, expiry and frequency caps, appropriate locale fallback, admin-only/version-targeted pilot, graceful failure, safe new-tab behavior and no automatic request to the voting provider on render.

## Pilot, ownership and review gates

- [CRM #10545](https://github.com/ChurchCRM/CRM/issues/10545): document lifecycle and agent/contributor updates after policy approval.
- [CRM #10546](https://github.com/ChurchCRM/CRM/issues/10546): validate existing feature candidates against current code and releases; **person-vs-family giving #9313 is distinct from online payments #10033**.
- [Marketing #123](https://github.com/ChurchCRM/marketing/issues/123): compare hosted providers, free limits, moderation, data handling and portability.
- [CRM #10544](https://github.com/ChurchCRM/CRM/issues/10544): technical notification hardening and tests.
- [Marketing #124](https://github.com/ChurchCRM/marketing/issues/124): draft 3–5 eligible, user-facing feedback entries and a 60-day pilot invitation. Do not publish before approval.
- [Marketing #125](https://github.com/ChurchCRM/marketing/issues/125): review aggregate pilot feedback, volunteer offers, moderation workload, access/localization and actual priority decisions after the pilot runs.

**Gates:** policy and provider approval → candidate verification → application hardening and review → copy/privacy/moderation approval → **George explicitly authorizes publication** → limited administrator rollout → wider rollout only by approval → 60-day evaluation → continue/change/stop decision.

Roles: maintainers own technical truth and approval; coding agents implement approved bounded tasks and tests; ChatGPT coordinates and reviews; community volunteers can give feedback and help test/translate/implement. Any public communication needs authorized ownership, and social media work is **not** assigned to NanoClaw by default.

## Reviewer checklist

- [ ] Proposed policy, states and the distinct RFC/epic/vote definitions approved.
- [ ] Current repository and released behavior verified for initial proposals; no duplicate user-facing votes.
- [ ] External voting platform, privacy notice, identity/abuse/moderation and portability approved.
- [ ] No in-app external tracking or installation/person identifiers; remote links validated.
- [ ] Notification expiry/dismiss/frequency/locale/version/security tests approved.
- [ ] User-facing issue quality, i18n, accessibility, mobile and contributor handoff checked.
- [ ] Pilot owner, rollback and evaluation defined; publishing has explicit approval.
- [ ] No approved-but-unscheduled issue receives a release milestone or promise.
