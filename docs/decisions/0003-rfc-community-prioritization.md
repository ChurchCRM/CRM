# RFC: Community feedback with GitHub Discussions

> **BLUF**
> Church users give feedback in two GitHub Discussions categories that maintainers fill: **Feature Proposals** (the church outcome, in plain language) and **RFC Reviews** (an invitation to review a technical design in its pull request). A reaction or comment shows demand. Maintainers approve designs and set the schedule. Issue readiness, sizing and PR gates stay in [RFC 0001](0001-issue-quality-and-contributor-readiness.md) and the [issue-readiness guide](../contributing/issue-readiness.md).

**Status:** Accepted  
**Date:** 2026-10-17  
**Owner:** George Dawoud  
**Index:** [#10550](https://github.com/ChurchCRM/CRM/issues/10550) lists each proposal and its status. Decisions live in the RFC.  
**Rollout:** [#10545](https://github.com/ChurchCRM/CRM/issues/10545), [#10546](https://github.com/ChurchCRM/CRM/issues/10546), [#10544](https://github.com/ChurchCRM/CRM/issues/10544)

## The two categories

| | [Feature Proposals](https://github.com/ChurchCRM/CRM/discussions/categories/feature-proposals) | [RFC Reviews](https://github.com/ChurchCRM/CRM/discussions/categories/rfc-reviews) |
|---|---|---|
| For | A church-facing outcome | A technical design that needs review |
| Audience | Pastors, treasurers, office staff, volunteers | Developers and security reviewers |
| Language | Plain church language about the outcome | Technical |
| Links | The canonical feature issue | The draft PR that holds the RFC, and the feature issue when one exists |
| Detailed thread | The Discussion | The PR. The Discussion only invites and points there |
| Who posts | Maintainers, one post per user-facing feature | Maintainers, one post per RFC PR |

Both categories use Announcement format: maintainers create posts, and signed-in GitHub users comment and react. Each post says GitHub sign-in is required.

## Workflow

1. Anyone files an issue, recommendation or PR. Maintainers triage it.
2. When it is ready for feedback, a maintainer posts it to the matching category. The issue holds the draft post, written to the templates below, so agents and contributors can prepare it and a maintainer publishes it.
3. The maintainer shares the Discussion link on Discord and asks for comments there. Useful Discord feedback is copied into the Discussion.
4. The Discussion URL goes into the issue and the [#10550](https://github.com/ChurchCRM/CRM/issues/10550) entry; the post links the issue.
5. Feedback is summarized in the issue or RFC, and the post's status is updated. Discord hears about status changes only: opened, accepted, shipped.

### A Feature Proposals post

```
Title:  <The church outcome, plain words>
Body:   Why: the problem, in two or three sentences
        What we are considering: the outcome, in a few bullets
        We would like to hear: two or three focused questions
        Status: Proposed | Design approved, unscheduled | Seeking contributor | Planned | Shipped | Deferred
        Details: link to the canonical issue
        A reaction or comment helps us see demand. Maintainers decide the design and the schedule.
```

### An RFC Reviews post

```
Title:  RFC: <name>
Body:   What it decides, in two sentences
        What feedback we want: the open questions
        Review it in: link to the PR (line-level feedback goes there)
        Church-facing context: link to the feature issue, if any
        Status: Proposed | Accepted | Rejected
```

Update the post's status when the PR or issue changes, and add the release link when it ships.

## Rules

- A reaction shows demand. Church workflow, accessibility, international needs, security, feasibility and contributor availability weigh in too.
- Fixes for vulnerabilities, data loss, privacy, accessibility, reliability and upgrades proceed on their own schedule. Report vulnerabilities through the security process.
- Sensitive or internal decisions are decided by maintainers.
- Moderation follows the Code of Conduct.
- GitHub Discussions is the only feedback tool: reactions are the vote.

## Lifecycle

| State | Milestone |
|---|---|
| Discovery, design review | No |
| Design accepted, unscheduled | No |
| Seeking contributor | Not by default |
| Planned (explicit scheduling decision, owned implementation issues) | Yes, on those issues only |
| Shipped, deferred or declined | Resolve the issue |

An accepted design can stay unscheduled. The RFC holds the decision; the Discussion and the index link to it. Certify `good first issue` only under the [issue-readiness guide](../contributing/issue-readiness.md).

## In-app notices

An in-app link to a Discussion needs a safe HTTPS destination, a unique dismissible campaign ID, expiry, a frequency cap, locale fallback, and admin-only and version targeting for any pilot. It carries the same campaign identifier for every installation. Say that opening GitHub shares network and browser details with GitHub. [#10544](https://github.com/ChurchCRM/CRM/issues/10544) tracks the hardening. George authorizes each publication.
