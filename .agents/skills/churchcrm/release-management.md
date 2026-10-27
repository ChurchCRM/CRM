---
title: Release Management
intent: Orchestrate an approved ChurchCRM release using the canonical release runbook.
---

# Release Management

Use this skill to prepare, check, start, publish, or verify a ChurchCRM release.
Follow [the release process](../../../docs/maintainers/release-process.md) for the steps and
current automation. Use [the locale pipeline](../../../docs/maintainers/locale-pipeline.md)
for localization details.

## Agent gates

- First step, before any check: in the main repository checkout, `git checkout master && git pull --ff-only`. Every later SHA, locale and file check reads that tree; a stale checkout gives false results.
- Right after the pull, ask the maintainer "What is the next version?" before any check. The answer is `next_version` for **Release & Start Next** and the target milestone for moved items. Check that milestone exists; if not, ask the maintainer to create it.
- Inspect current GitHub and repository state; memory is not evidence of readiness.
- Identify the release candidate by exact commit SHA. A change to that SHA invalidates its prior green checks.
- Do not start a draft or publish without the maintainer's explicit approval for that action. Approval to draft is not approval to publish.
- Do not replace or delete an existing release or tag without explicit approval.
- Security gate: only a High advisory blocks, and only while the code on the release commit is still exploitable. Read each draft High and check the release candidate SHA; a draft state or a missing `Fixes` link does not decide it. For every advisory you list as blocking, read the vulnerable lines on `master` (`gh api repos/ChurchCRM/CRM/contents/<path>?ref=master`) and search merged PRs for a fix nobody linked. A fix found on `master` is not a blocker: report that the advisory needs `patched_versions` set, and update it only on the maintainer's yes. A High the maintainer re-rates after review is edited on the advisory with the reason in its description. Medium and Low never block. List every blocking GHSA ID. Only the maintainer lifts a block, by naming the ID.
- Locale gate, right after the security gate and before any build or nightly check: `locale/terms/missing/` is empty on `master` and no `Update locale strings` or `locale: update translations` PR is open. Every locale merge moves `master`, so a green build or nightly taken earlier is for the wrong SHA. Translate and merge first (runbook Phase 1), then read the SHAs. A locale PR that opens after the maintainer closes this gate waits for the next release: do not merge it, and do not count it as a blocker.
- Release notes gate, after the locale gate and before any build or nightly check: write `changelog/<version>.md` and get the maintainer's approval first. Merge any PR that carries it, and any PR from the notes review, before the SHAs are read, because each merge moves `master`.
- Milestone review: use the next version already asked for, and check its milestone exists. Then ask about each open item in the release milestone one at a time, with the options 7.8.x next patch, next minor, keep, or close. A docs tracking issue stays in the milestone of the feature's commit while its docs PR is open in `ChurchCRM/docs.churchcrm.io`; a merged docs PR means ask to close it. An item whose remaining half is a PR in another repo (`ChurchCRM/visuals`, the website) also stays while that PR is open. Marketing capture issues and their epics stay in the milestone; bookkeeping rolls them forward at Publish. Any other item with no PR goes to the next patch milestone unless the maintainer names the next minor. If the milestone does not exist, ask the maintainer to create it, then apply the moves. Record each answer as a general rule in the skill or runbook when it fits.
- A fix to a release workflow (`release-publish.yml`, `release-prepare.yml`, `release-bookkeeping.yml`) cannot run end to end before it merges, because it creates real branches, PRs and releases. Open an issue for it (labels `Integration Testing` and `build`, next milestone, a done-when checklist) and confirm it in the next release.
- Do not merge the `Start <next> release` PR until the released version's `Marketing: capture screenshots & videos` run has finished. Open the PR early; merge it after the capture, then re-run `release-bookkeeping.yml` with `tag=<version>`.
- Report **READY FOR DRAFT** or **BLOCKED** with the evidence and remaining blockers. After publication, list every advisory fixed in the released version or older that is still draft or triage, and every published advisory with no CVE. Ask the maintainer before each CVE request and each publish; the request comes before the publish. After publication, report **RELEASE VERIFIED** only after completing the runbook's post-release checks, including that Docker Hub's `latest`, `latest-php8-apache` and `latest-php8-fpm` digests match the released version's tags (4.9), and that the open PRs on `ChurchCRM/docs.churchcrm.io` were reviewed (4.10): the release docs PR and held docs PRs can need a manual merge, so list them and ask the maintainer. On `docs.churchcrm.io` only the `Current` milestone merges into `main`; each upcoming version keeps a version milestone whose PRs merge only into `release-docs/<version>`. If the released version's docs milestone is still open after publishing, ask where its items go.

Use the [Release Notes skill](./release-notes.md) to prepare notes, and the
[Release Announcement skill](./release-announcement.md) for post-release community
actions. This skill does not replace their step-specific instructions.
