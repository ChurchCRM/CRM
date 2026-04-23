# Skill: GitHub Interaction — concise checklist

Purpose: practical, step-by-step actions for handling reviews, commits, and PRs. This file is an actionable companion to the canonical `git-workflow.md` (policy) and the `gh-cli` skill (commands).

For a **full PR review workflow** (fetching changes, standards checklist, doc updates, manual validation, capturing learnings), see [`pr-review.md`](./pr-review.md).

Quick checklist — Addressing reviews
- Read comments and ask clarifying questions if needed.
- Make one focused change per review thread; add tests where applicable.
- Mark comments resolved and add a short reply summarizing the change.
- Run relevant tests and clear logs before testing:

```bash
rm -f src/logs/$(date +%Y-%m-%d)-*.log
```

Quick checklist — Commit
- Stage only intended files: `git add <files>`
- Use the commit format from `git-workflow.md` (imperative, reference issue).
- Verify staged diff: `git diff --staged`

Quick checklist — Create PR (example using `gh`)
- Push branch: `git push origin <branch>`
- Create PR with a template body:

```bash
gh pr create --title "Fix issue #1234: Short title" --body-file .github/PULL_REQUEST_TEMPLATE.md --base master
```

- Add reviewers and link issues (`Fixes #1234`) in the PR description.
- Wait for CI; respond to review comments and iterate.

Best practices (short)
- Keep commits small and focused.
- One issue per PR.
- Always run tests locally before pushing.
- Do not merge without human review and passing CI.

### Write PR descriptions from the branch diff, not the commit log <!-- learned: 2026-04-21 -->

When drafting a PR body, **describe what changed between the branch and
its merge base** — not the sequence of commits. Commits get rebased,
squashed, reordered, or absorbed into merges; a description that lists
"first I did X, then Y, then reverted Y" will be wrong the moment the
branch is cleaned up, and a reviewer reading the final diff won't
recognize the story.

Use these commands instead:

```bash
# Files changed and line counts vs merge base
git diff --stat $(git merge-base HEAD master)..HEAD

# Full diff vs merge base (pipe to head/less for large branches)
git diff $(git merge-base HEAD master)..HEAD

# Only the final state of each file (what a reviewer actually sees)
git diff $(git merge-base HEAD master)..HEAD -- <path>
```

Structure PR bodies as **was → now** for each affected area, grounded in
what the final diff shows. Reference commits only as a debugging aid,
not as narrative structure.

When a branch is stacked on another open PR (e.g. follow-up work on a
review branch), say so explicitly at the top of the PR body and note
which commits belong to the base PR so reviewers know the auto-rebase
will trim them when the base merges.

See also:
- `git-workflow.md` for branching, commit message format, and the full pre-commit checklist.
- `gh-cli` skill (local `.agents/skills/gh-cli/SKILL.md` or upstream) for concrete `gh` commands.

If you prefer, I can convert this into a checklist file that the CI or editors can surface (e.g., a JSON checklist or PR template snippet).

---

## Milestone = release that shipped it, not when it was closed <!-- learned: 2026-04-21 -->

When assigning a milestone to a closed issue, use the **first release tag that contains the merge commit** of the closing PR — not the milestone that was "current" on the close date. Issues closed months after the fix shipped belong on the shipped release, so users can cross-reference the changelog for the version they are actually running.

### How to find the shipped release

```bash
# 1. Find the closing PR (check timeline cross-references or grep the merge log)
git log --oneline master --grep="#<issue_number>" | head -5

# 2. Find the first non-rc tag that contains the merge commit
git tag --contains <merge_sha> | grep -v rc | sort -V | head -1
```

### Moving an issue to the right milestone

```bash
# Milestone NUMBER (not title). Get the number from:
#   gh api "repos/{owner}/{repo}/milestones?state=all" --paginate
gh api --method PATCH "repos/{owner}/{repo}/issues/<n>" -F milestone=<milestone_number>
```

### Why this matters

- Users who installed 7.1.0 must see the fix in the 7.1.0 release notes — not in 7.4.0 where the GitHub issue happened to be closed weeks later.
- Squashed / rebased merges: feature-branch commits may not appear on `master` at all. `git tag --contains <feature_sha>` will return empty. Use the **merge commit** (found via `git log --grep=#<pr>` on master) and run `git tag --contains` on that.
- If the closing PR is still open, leave the milestone on the active development one; only finalize after merge + release.

### Common trap

An issue closed on `2026-04-18` with milestone `7.4.0 (in development)` is almost always wrong when the fix PR was merged weeks or months earlier. Always verify via `git tag --contains`.

---

## Epic Hygiene: Duplicate Subtasks, Milestones, Links <!-- learned: 2026-04-21 -->

Multi-subtask epics in this repo have consistently shown three drift patterns.
Audit them together whenever you revisit an epic:

### 1. Duplicate subtask issues

Subtasks created in bulk (e.g. a templated `gh issue create` loop) can produce
two issues for the same scope — usually when a prior draft was partially
committed and the template ran again. Signal: two issues with nearly identical
titles created the same day (e.g. `#8191 "Subtask: Routes & Template"` vs
`#8192 "Subtask 1: Routes & Template"`).

```bash
# Scan a sequential range around a known issue to find siblings + dupes
for n in 8188 8189 ... 8196; do
  gh issue view $n --json number,title,state,milestone,createdAt \
    --jq '"\(.number) [\(.state)] created=\(.createdAt[:10]) milestone=\(.milestone.title // "none") — \(.title)"'
done
```

Resolve: keep the one with the richer history (more cross-references, correct
milestone). Close the other with a short comment pointing at the keeper:

```bash
gh issue comment <dup_n> --body "Closing as duplicate of #<keeper>. Work shipped via PR #NNNN in release X.Y.Z."
gh issue close <dup_n> --reason "not planned"
```

### 2. Milestones on the epic and its siblings drift apart

Because subtasks and the epic are often closed weeks apart, they end up on
different milestones. Apply the "milestone = release that shipped it" rule
(section above) to every issue in the group — epic included. Then verify they
all agree:

```bash
for n in <epic> <subtask1> <subtask2> ...; do
  gh issue view $n --json number,milestone \
    --jq '"#\(.number) milestone=\(.milestone.title // "none")"'
done
```

### 3. Epic body has no links to subtasks

Epics often get opened with a plan-in-prose body that never references the
actual issue numbers. Add a `## Subtasks` section at the bottom so future
readers (and `gh issue view`) can follow the trail:

```bash
gh issue view <epic> --json body --jq .body > /tmp/epic.md
cat >> /tmp/epic.md <<'MD'

---

## Subtasks

- [x] #NNNN — Subtask 1: Title (shipped in X.Y.Z via #PPPP)
- [x] #NNNN — Subtask 2: Title (shipped in X.Y.Z via #PPPP)

**Status**: All subtasks closed; feature shipped in **X.Y.Z**.
MD

gh issue edit <epic> --body-file /tmp/epic.md
```

Do all three together — fixing milestones without linking subtasks leaves the
next reviewer rediscovering the same mess, and closing duplicates without
fixing milestones just propagates the wrong release tag.

---

## Security Advisory Management <!-- learned: 2026-04-05 -->

Use `gh api` to manage GitHub Security Advisories (GHSAs). The full lifecycle is:
**draft → published → request CVE**.

### Listing advisories

```bash
gh api /repos/{owner}/{repo}/security-advisories --paginate \
  --jq '.[] | "\(.ghsa_id) state=\(.state) cve=\(.cve_id // "none")"'
```

### Re-opening a closed advisory (required before publishing)

A closed advisory cannot be published directly — it must be moved to `draft` first:

```bash
gh api --method PATCH /repos/{owner}/{repo}/security-advisories/{ghsa_id} \
  --input - <<< '{"state": "draft"}'
```

### Publishing with correct package metadata

```bash
gh api --method PATCH /repos/{owner}/{repo}/security-advisories/{ghsa_id} \
  --input - <<< '{
    "state": "published",
    "vulnerabilities": [{
      "package": {"ecosystem": "composer", "name": "ChurchCRM/CRM"},
      "vulnerable_version_range": "<= 7.0.5",
      "patched_versions": "7.1.0"
    }]
  }'
```

### Requesting a CVE ID

The advisory must already be published. An empty `{}` response means the request was accepted (not an error):

```bash
gh api --method POST /repos/{owner}/{repo}/security-advisories/{ghsa_id}/cve
```

### Closing a blocking PR on a private fork

If the advisory is linked to a private fork with open PRs, publishing will be blocked. Close them first:

```bash
gh pr list --repo {owner}/{repo}-ghsa-{id}
gh pr close 1 --repo {owner}/{repo}-ghsa-{id}
```

### Known gotchas

- **Ecosystem value**: PHP packages must use `"composer"` — `"php"` returns a 422 error.
- **`request_cve` key in PATCH**: NOT supported — use the dedicated `/cve` POST endpoint instead.
- **Closed → published requires draft step**: Direct `closed → published` fails; always go `closed → draft → published`.
- **Empty ecosystem blocks publishing**: If the existing vulnerability entry has an empty `ecosystem`/`name`, PATCH the vulnerabilities array to fix it before attempting to publish.
- **Empty `{}` CVE response is success**: GitHub returns `{}` when a CVE request is accepted — it is not an error.
- **Batch publishing — sort by `created_at` ascending**: Always publish the oldest report first. If two reports cover the same vulnerability, the earliest one should receive the CVE. Publishing in the wrong order means the later duplicate gets the CVE and the original doesn't.

### Handling duplicate advisories <!-- learned: 2026-04-05 -->

When two advisories describe the same root cause:

1. **Identify the original** — check `created_at` timestamp: `gh api /repos/ChurchCRM/CRM/security-advisories/{ghsa_id} --jq '.created_at'`
2. **Publish the original first** — so it gets the CVE
3. **If the duplicate already has a CVE** — contact GitHub Security at security@github.com, explain the duplication (include both GHSA IDs and creation timestamps), and request that the duplicate CVE be marked `REJECTED`. GitHub is the CNA for these CVEs and can reject duplicates.
4. **Request CVE for the original** — `gh api --method POST /repos/{owner}/{repo}/security-advisories/{original_ghsa}/cve`
5. **CVEs cannot be "un-assigned"** — only marked `REJECTED` by the CNA. Once a CVE is rejected, the NVD record shows `REJECTED` status and references the canonical CVE.

### Notifying reporters via GitHub Discussion <!-- learned: 2026-04-05 -->

GitHub Security Advisory comments have **no REST or GraphQL API** — neither
`POST .../security-advisories/{ghsa_id}/comments` (returns 404) nor any GraphQL
mutation exposes them. To thank reporters, create a GitHub Discussion that @-mentions
each reporter (they receive a notification automatically):

```bash
# Collect unique reporter logins from all published advisories
gh api /repos/{owner}/{repo}/security-advisories --paginate \
  --jq '.[] | select(.state == "published") | .credits[].login' | sort -u

# Then create a Discussion via GraphQL (get repositoryId + categoryId first)
gh api graphql -f query='
  mutation {
    createDiscussion(input: {
      repositoryId: "REPO_NODE_ID",
      categoryId:   "CATEGORY_NODE_ID",
      title: "Security Advisory — Thank You to Our Reporters",
      body:  "Thank you @reporter1, @reporter2 ..."
    }) { discussion { url } }
  }'
```

---

## Verify a fix is on master before closing an issue <!-- learned: 2026-04-21 -->

A commit titled `Fix: X` in `git log --all` does **not** mean the fix has
shipped. Copilot agents, stale branches, and abandoned PRs all produce
fix-shaped commits that never merged. Before closing any issue on the
claim "this is fixed":

1. Grep the commit log (include `--all` to catch unmerged branches):
   ```bash
   git log --all --oneline --grep="<keyword from issue>"
   ```
2. For each candidate commit, **verify it reached master**:
   ```bash
   git branch --contains <sha>
   # Must include "master" — not just "claude/check-issue-XXXX" or a feature branch.
   ```
3. Or check master directly for the closing PR:
   ```bash
   git log master --oneline --grep="closes #<issue>"
   git log master --oneline -- <expected file path>
   ```
4. Read the current file on master and confirm the buggy code is actually
   gone. A stale commit message on an unmerged branch is not evidence.

**Why:** Closed-when-actually-open issues confuse reporters and hide
regressions. The only authoritative signal is "the fix is in a commit
reachable from `master` and the bad code is gone from the working tree."

**How to apply:** Whenever a user says *"I think this is fixed, close
it"* — run `git branch --contains` on the candidate commit and read the
file on master. If either check fails, leave the issue open and explain
which branch holds the fix so it can be merged.