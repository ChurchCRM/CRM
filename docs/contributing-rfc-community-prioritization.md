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

The canonical feature requirement lives in its GitHub issue; the reviewed technical decision lives in the linked RFC PR. The [RFC portfolio #10550](https://github.com/ChurchCRM/CRM/issues/10550) is only an **index**, not a replacement source of truth. A curated Feature Proposal Discussion comment or reaction is an **expression of user demand**, not an implementation specification.

George approves significant product/architecture decisions, sensitive security/privacy policy, spending, publication, release scope, merges, and removal. Contributors can offer designs, feedback, code, translations and tests.

## Two linked RFC artifacts: use case and technical design

**Church staff, pastors, treasurers and volunteers should never need to understand a pull request to have a voice.** We distinguish a **feature/use-case proposal** from a **deep technical RFC**.

| Layer | Audience and purpose | Source, review, access |
| --- | --- | --- |
| **Public feature/use-case proposal** | Explain in ordinary church language the problem, who benefits, actual ministry workflows, expected outcome and a few focused feedback questions. No schema jargon, architectural debate or release promise. | **GitHub Discussions**, category **Feature Proposals** configured in **Announcements format**: people with *maintain/admin* access create topics; any signed-in GitHub user can comment and react. The maintainers curate one post per user-facing feature and update its status. |
| **Deep technical RFC** | Review architecture, code/release baseline, security, privacy, data model/migrations, permissions, alternatives, non-goals, accessibility, i18n, tests and rollout. | Markdown under `docs/proposals/`, reviewed in a **draft GitHub pull request**. Developers and interested reviewers provide line-level feedback; George approves architecture. |
| **Implementation** | Contributor-sized work, acceptance tests and ownership | GitHub Issues and PRs, with version milestones **only when scheduled** |
| **Portfolio** | Track feedback, RFC status, decision, readiness and implementation | [#10550](https://github.com/ChurchCRM/CRM/issues/10550) is the index, never the authoritative feature or design text |

### Moderated public Discussions model

- Discussions is **currently disabled per the maintainer**. Enabling the feature on `ChurchCRM/CRM` requires an explicit maintainer action. **Never link a church user to a Discussion until it exists and is tested.**
- Set up a category named **Feature Proposals** with format **Announcement** (not an ordinary open-ended category). GitHub restricts **creation** in that category to maintainers/admins but allows signed-in community members to comment and reply. Do **not** click GitHub's *Lock discussion* action as a publishing technique: that blocks regular comments.
- Use only this designated category for the publicly curated feature proposals. Repository-wide Discussions may still permit users to create discussions in **other enabled categories**; the Announcements restriction is **category-scoped**, not necessarily a repository-wide prohibition.
- Each post is drafted/edited by an authorized maintainer, links to the canonical feature issue and technical RFC if one exists, and clearly states **proposed / design approved but unscheduled / seeking contributor / planned / shipped / deferred**.
- Only a **maintainer-authorized proposal** should be promoted to the public Feature Proposals category; community-submitted ideas can arrive through the existing feedback intake or GitHub Issues/Discord and be triaged first.
- Comments and reactions are participation signals, not a binding priority vote. Meaningful church workflows, accessibility, international needs, security, feasibility and contributor availability also count. Moderation follows ChurchCRM's Code of Conduct.
- GitHub account sign-in is required to participate in GitHub Discussions. That friction is a conscious tradeoff for reusing GitHub; a plain-language notification and website can link directly to the discussion, but do not falsely promise account-free voting.
- **No additional voting service, GitHub Wiki RFC copy, custom voting database, embedded voting widget, or GitHub Project board is required.**

### Example — person versus family giving

**Public Feature Proposal** (Discussion): “When a husband and wife donate separately, would your church need to record gifts under each person while still seeing a combined household total? How should year-end giving statements work?” Link [#9313](https://github.com/ChurchCRM/CRM/issues/9313). Invite treasurers to describe their church workflow. **This is separate from online payment gateways** ([#10033](https://github.com/ChurchCRM/CRM/issues/10033)).

**Technical RFC** (draft PR): Determine optional person attribution, migration, spouse privacy, role-based access, reporting and tax-statement behavior, backward compatibility and audit tests. Link back to the Feature Proposal and issue.

**Implementation issues:** After technical approval, split into independently testable work. Do not infer an implementation milestone from a popular Discussion.

### Cross-linking and lifecycle

1. Intake a church user problem; search shipped software and existing issues, merge duplicates, and keep one canonical feature issue.
2. Prepare a human-readable Feature Proposal and, after maintainer approval, publish it as an Announcement-format Discussion. The Discussion is the **public comment/reaction destination**.
3. For consequential unresolved designs, prepare a technical RFC in a draft PR. Link the Discussion, RFC, feature issue and portfolio **both ways**.
4. Record decisions in the technical RFC and its linked GitHub issue; update the public Discussion with a plain-language outcome. A design can remain approved but unscheduled indefinitely.
5. When George explicitly schedules implementation, create/bind scoped issues with tests and assign milestones to those tasks, not to the feature Discussion or speculative RFC.
6. After verified release, update the public Discussion with release link, documentation and the final outcome. Do not manufacture an “in progress” or “shipped” status.

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
| **Community feedback** (optional, parallel to review or after design acceptance when safe) | One maintainer-authored **Feature Proposal Discussion** per **user-facing parent outcome**, concise question, clear status; community demand and comments summarized back in GitHub | No |
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
- **Links:** RFC, portfolio #10550, parent/child implementation issues, docs, and Feature Proposal Discussion URL if approved.
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

## Public feature Discussions and privacy: proposed policy

**Nothing in this section authorizes a live public campaign or creates an external account.** See [RFC #10543](https://github.com/ChurchCRM/CRM/issues/10543).

- **Use one native platform:** after a maintainer enables GitHub Discussions, curate Feature Proposals in an **Announcements-format** category (maintainers create, signed-in community comments/reacts). Until enabled, do not link publicly. Other intake channels may feed the backlog, but they are not competing ballots.
- Do **not** build a ChurchCRM voting database, host a new service, or embed provider scripts, cookies, trackers, pixels or iframes in ChurchCRM.
- Each maintainer-authored Discussion describes one user problem, who benefits, realistic constraints and links the canonical GitHub issue. The technical decision stays on GitHub.
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
- [ ] Public Discussions platform, privacy notice, identity/abuse/moderation and portability approved.
- [ ] No in-app external tracking or installation/person identifiers; remote links validated.
- [ ] Notification expiry/dismiss/frequency/locale/version/security tests approved.
- [ ] User-facing issue quality, i18n, accessibility, mobile and contributor handoff checked.
- [ ] Pilot owner, rollback and evaluation defined; publishing has explicit approval.
- [ ] No approved-but-unscheduled issue receives a release milestone or promise.
