---
title: "Release Management"
intent: "Orchestrate a ChurchCRM release from scope review through validated draft, maintainer approval, publication, and post-release verification"
tags: ["release", "testing", "github", "workflow"]
prereqs: ["[[github-interaction]]", "[[release-notes]]", "[[testing]]", "[[db-schema-migration]]"]
complexity: "advanced"
---

# Skill: Release Management

## Purpose

Run the ChurchCRM release process for the maintainer instead of asking the maintainer to operate a manual checklist.

Use this skill when asked to prepare, check, start, review, or complete a ChurchCRM release.

The agent owns routine investigation and orchestration. George remains the final authority for release scope when judgment is required and for making a release public.

## Source of Truth

Always inspect current GitHub/repository state. Never use memory as proof of release readiness.

The release candidate is identified by an exact commit SHA, not only by a version or branch name.

## Release States

`candidate → validated → draft → reviewed → published → verified`

Do not skip a state.

---

## 1. Review Release Scope

Identify the target version, target milestone, previous release, next planned version, and current `master` HEAD SHA.

Review every open issue and pull request assigned to the target milestone.

For unfinished items:

- Determine whether the item is required for the release, a release blocker, or safe to defer.
- Explain user impact and release risk rather than relying only on labels.
- Propose the appropriate next milestone for non-blocking work.
- If George has already decided an item should not ship, move it to the approved next milestone.
- If deferral changes product scope or is ambiguous, ask George for the decision before changing the milestone.
- Do not silently drop an issue or PR from release scope.

The release scope is clean when no unintended open item remains in the target milestone.

Once scope is accepted, treat the candidate SHA as frozen. If `master` changes, re-evaluate the release gate against the new SHA.

---

## 2. Validate Version, Database, and Release-Specific Risk

Verify from the repository:

- Version metadata represents the version being released.
- Database migrations intended for the release use the correct version/order and upgrade path.
- Installation/schema state and migration state are consistent.
- Dependency or runtime requirement changes are understood and documented.
- Relevant security work has no unresolved release blocker.
- Upgrade warnings or breaking changes are identified for the release notes.

Use the database, security, testing, and localization skills when those areas changed.

Do not invent manual tests. Derive any required manual validation from the changes actually shipping, especially user-visible changes that automation does not adequately cover.

---

## 3. Establish the SHA-Based Automated Release Gate

The normal release gate is:

```
current master HEAD SHA
        =
successful Build/Test/Package SHA
        =
successful Nightly SHA
```

Inspect the normal `master` Build/Test/Package result and the nightly workflow result.

A previous green run is useful health evidence but is not sufficient if it tested a different commit.

### When both workflows are green on the current HEAD

Do not rerun them merely for ceremony. Continue to release validation.

### When a required run is missing, stale, or failed

- Trigger the required workflow when tooling permits.
- Otherwise provide the exact GitHub/CLI action needed to start it.
- Wait for completion before declaring the release validated.
- Investigate failures and report the actual failing job/test.
- If a fix changes `master`, establish the gate again using the new HEAD SHA.

The nightly workflow is `.github/workflows/build-test-nightly.yml`. It supports `workflow_dispatch` and is therefore suitable for an on-demand final gate when needed.

Its successful summary must represent success of all required groups, including build/package, root/subdirectory application tests, setup tests, locale smoke tests, and the configured upgrade matrix.

The nightly's `release: published` trigger is post-release verification. It must not be substituted for the pre-release gate.

---

## 4. Produce the Readiness Report

Before starting the GitHub release, give George a compact report containing:

- Target version and previous version
- Candidate/master SHA
- Milestone status and any deferred issues/PRs
- Build/Test/Package status and tested SHA
- Nightly status and tested SHA
- Version/migration status
- Required manual validation and result
- Security/release blockers
- Release-note/upgrade warnings
- Next planned version

Conclude with exactly one operational state:

- **READY FOR DRAFT** — all required gates are satisfied.
- **BLOCKED** — list the blockers and what is required to clear them.

Do not start the release merely because an older build or nightly is green.

---

## 5. Approval to Start the Draft Release

Starting the release is a maintainer approval point.

When the release is READY FOR DRAFT, ask George to approve starting the draft release and confirm the next version if it has not already been decided.

Do not publish a public release at this point.

---

## 6. Start the Release Through the Canonical Workflow

The only normal path for starting a ChurchCRM GitHub release is:

`.github/workflows/release-publish.yml` — **Release & Start Next**

Run it with:

- `draft=true` unless George explicitly requests otherwise.
- The approved `next_version`.
- `prerelease` only when appropriate.
- Normally leave `run_id`, `release_version_override`, and `expected_version` empty so workflow safeguards operate normally.

The workflow:

1. Resolves a successful master Build/Test/Package run whose `package.json` version matches the release.
2. Uses the ZIP artifact from that build rather than rebuilding a different artifact.
3. Creates a GitHub draft release.
4. Starts the next release cycle through `release-prepare.yml` and opens the next-version PR.

After the workflow completes, verify that the source build SHA is the SHA validated in the readiness gate. If it differs, stop and investigate before publication.

### Safety warning

The current release workflow contains behavior that can delete and recreate an existing release and tag of the same version. Treat rerunning it for an existing version as a destructive/high-risk operation. Do not intentionally replace an existing release/tag without explicit George approval.

---

## 7. Transform the GitHub Draft into Release Notes

The GitHub-generated draft is the raw source material for user-facing release notes.

Invoke `release-notes.md` after the draft exists.

The release-notes skill should transform and organize the draft, then fact-check the result against the actual shipped changes. It must not manufacture features, performance claims, security claims, localization counts, or user benefits.

Present the polished notes to George for review.

---

## 8. Final Publication Approval

Publishing is a separate maintainer approval point.

Before asking for approval, verify:

- Draft tag/version is correct.
- Draft targets the validated release SHA.
- ZIP is the artifact from the validated build.
- User-facing release notes have been fact-checked.
- Required upgrade warnings are present.
- No new release blocker appeared after the readiness gate.

Ask George for explicit approval before making the draft public.

Never infer publication approval from approval to create the draft.

---

## 9. Post-Release Verification

After publication, verify:

- Public GitHub release exists at the expected version.
- Tag points to the intended release commit.
- Release ZIP exists, has the expected version/name, and is non-empty.
- Release-triggered nightly/post-release automation starts and completes as expected.
- The next-version PR was created by `release-prepare.yml`.
- Any release/version state that must be visible to ChurchCRM users is correct.

Do not merge the next-version PR solely because the release was published; follow normal PR review/approval rules.

Once verified, report **RELEASE VERIFIED** with links/evidence and any remaining follow-up.

Approved release communication can then be handed to the established community/communications workflow; do not create a competing communication process.

---

## Decision Boundaries

The agent should perform routine release work without repeatedly asking George to operate individual checks.

George's explicit decision is required for:

1. Ambiguous or material changes to release scope.
2. Starting the draft release workflow.
3. Publishing the public release.
4. Replacing/deleting an existing public release or tag.
5. Other sensitive release/security decisions.

Routine repository inspection, CI correlation, failure investigation, release-note preparation, and post-release verification do not require repeated approval.

## Failure Rule

Any change to the release candidate SHA invalidates SHA-specific green evidence from the previous candidate. Re-run or re-correlate the required checks against the new SHA before continuing.
