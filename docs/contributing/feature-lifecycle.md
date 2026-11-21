# Feature lifecycle

> **TL;DR / BLUF**
> Every piece of ChurchCRM work moves through the same stages — intake, discovery, an RFC gate when a consequential decision is open, decision, decomposition, readiness, claiming, implementation, and a shipped / deferred / declined outcome. Four states are kept distinct: **approved design**, **community feedback received**, **implementation-ready**, and **scheduled**. Only a maintainer schedules, and only a milestone means scheduled.

**Use this page for the lifecycle itself.** Issue readiness rules live in [Issue readiness and contributor handoff](./issue-readiness.md); agent roles and boundaries live in the [AI operating model](./ai-operating-model.md); the decisions this lifecycle runs on are [RFC 0001](../decisions/0001-issue-quality-and-contributor-readiness.md) (issue quality and readiness) and [RFC 0003](../decisions/0003-rfc-community-prioritization.md) (community feedback with GitHub Discussions, tracked from #10543). Where a rule already lives in one of those documents, this guide links to it instead of restating it.

## The lifecycle

Each stage names its owner and its exit test.

1. **Intake** — *Owner: triage (agent or maintainer).* An issue is filed. Exit: first response given; a vague report gets one round of specific asks (reproduction, version, environment).
2. **Discovery** — *Owner: triage / maintainer.* The problem and use case are established, and prior art and current master are checked before anything is called missing or new — closed does not mean done. Duplicates are folded into the surviving issue with a pointer comment (for example, #5237 folded into #11217). Exit: the work is understood well enough to route.
3. **RFC gate** — *Owner: maintainer.* Required when consequential decisions are unresolved. An RFC holds the use case, goals, non-goals, and open questions only; architecture goes into a separate document created after the RFC is approved. The maintainer reviews it one section at a time. Exit: the RFC is ready for a decision.
4. **Decision** — *Owner: maintainer.* The RFC lands as accepted, accepted with changes, deferred, or declined. **Accepted is not scheduled**: RFC 0004 was accepted on Oct 30, 2026 with its child issues still deliberately unfiled, and RFC 0008 is resolved with its §7 child issues deliberately not filed. Exit: the decision and its reasoning are recorded on the RFC.
5. **Feature Proposal Discussion (optional)** — *Owner: maintainer.* For user feedback on an accepted direction. A maintainer creates the Discussion in the Feature Proposals category (there is no agent/API path for creating Discussions); the Discussion links the canonical issue and the RFC, and link-back comments are posted on the issues. Feedback informs scope; it does not decide it — comments and reactions never determine a security or privacy implementation. Exit: feedback is summarized on the canonical issue.
6. **Decomposition** — *Owner: maintainer.* Implementation work is filed as an epic with native sub-issues, one per vertical end-to-end outcome. An issue carrying 3+ distinct tasks is split before it is worked (#10110 → #11457 + #11458; #11226 → #11455 once its first half shipped). Sequenced work names its keystone: in the Sortable epic (#11277–#11285), the consumer slices became ready only after the base helper #11278 landed. Exit: every outcome has its own issue.
7. **Readiness and good-first-issue certification** — *Owner: maintainer.* Per RFC 0001 as operationalized in the [readiness guide](./issue-readiness.md), including the two checks adopted Nov 13, 2026: **one task** and **spec'd enough to start cold**. A one-line wish is written up by a maintainer *before* it gets the label, not after. Certification is a maintainer act. Exit: the issue passes the readiness checks.
8. **Claiming** — *Owner: contributor.* Work is claimed visibly: assignment plus an early draft PR. Assignment staleness: no PR after one week earns a check-in comment; no reply in three days and the issue returns to the pool. Maintainer assignments are exempt. Exit: a draft PR exists.
9. **Implementation** — *Owner: contributor.* Focused PRs, one outcome per PR (the RFC 0001 PR-scope rule), merged by a maintainer. Exit: the PR is merged.
10. **Shipped / deferred / declined** — *Owner: maintainer.* Shipped work links to the real release: the milestone closes when its release ships, and the release-reporter notification posts the release link back on the reporter's issue. Deferred and declined items close with the reason and a pointer, never silently.

## The four states, kept distinct

| State | Test |
|---|---|
| **Approved design** | The RFC (or, for small work, the issue spec) is accepted by a maintainer. |
| **Community feedback received** | A linked Feature Proposal Discussion exists and its feedback is summarized on the canonical issue. |
| **Implementation-ready** | The issue passes the readiness checks: settled design, one task, spec'd enough to start cold, an existing test pattern, an owner available. |
| **Scheduled** | A maintainer has put it on a release milestone. Nothing else — not acceptance, not enthusiasm, not an assignee — means scheduled. |

## RFC, epic, or ordinary issue

The #10543 family is the worked example. #10543 is the tracker issue for the community-feedback process; its RFC is the decision document, reviewed and accepted on its own; its children are ordinary implementation issues filed only once the decision landed — one for the code (#10544), one for this guide (#10545). The RFC decided; the epic tracked; the ordinary issues did the work. Small work with a settled design skips the RFC entirely: the issue spec is its approved design.

## Linking rules

- Every Feature Proposal Discussion links its canonical user-facing issue and its technical RFC, where one applies; the issue and the RFC link back. Maintainers create Discussions; agents post the link-back comments.
- An epic's sub-issues are native sub-issues, never duplicate children filed alongside.
- A split closes the parent as a tracker with pointer comments to the children; a fold does the same into the surviving issue.
- Shipped status is only ever claimed with a link to the actual release.

## Milestone rules

- No milestones for unscheduled work. A milestone means scheduled, and only a maintainer schedules.
- A release milestone closes when its release ships, with the close date equal to the release date.

## Roles

- **Maintainer** — owns final scope, schedule, certification, and merges; creates Discussions; is the only scheduler.
- **Contributors** — claim visibly, work in focused PRs, and keep the issues they own moving or release them back to the pool.
- **Agents** — operate inside the [AI operating model](./ai-operating-model.md): public writes are low-risk comments (first responses, check-ins, release-reporter notices, docs-gap PRs), each carrying the bot footer; agents never review, approve, or merge, or close.
- **Moderation / publishing** — Discussions are maintainer-created and maintainer-moderated; release publishing is a maintainer action end to end.
- **Data-retention and exit roles** — *undefined.* No maintainer decision defines them yet; this guide records the gap rather than inventing a rule. (Open decision carried from #10545.)
