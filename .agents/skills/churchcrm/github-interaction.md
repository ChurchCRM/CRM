---
title: "GitHub Interaction — Concise Checklist"
intent: "Practical, step-by-step actions for handling reviews, commits, and PRs — companion to git-workflow.md"
tags: ["workflow", "pr", "review", "github"]
prereqs: ["[[git-workflow]]"]
complexity: "beginner"
---
# Skill: GitHub Interaction — concise checklist

Actionable companion to `git-workflow.md` (policy). Full PR review workflow: [`pr-review.md`](./pr-review.md). PR body structure: `.agents/skills/pr-description-guidelines.md`.

## Addressing reviews

- Read comments and ask clarifying questions where needed
- Make one focused change per review thread and add tests where applicable
- Reply with a short summary of the change and resolve the thread (`git-workflow.md` → After push)
- Clear logs before running tests:

```bash
rm -f src/logs/$(date +%Y-%m-%d)-*.log
```

## Commit

- Stage by explicit path: `git add <files>`
- Use the commit format from `git-workflow.md`
- Verify the staged diff: `git diff --staged`

## Create a PR

- Push the branch once the latest user message approves it (`git-workflow.md` → Commit freely, push only on approval): `git push origin <branch>`
- Create the PR from the template:

```bash
gh pr create --title "Short imperative title" --body-file .github/PULL_REQUEST_TEMPLATE.md --base master
```

- Add reviewers and link issues with `Fixes #1234` in the description
- Wait for CI, respond to review comments and iterate

Keep commits small and focused, one issue per PR, run tests locally before pushing, and merge after human review and passing CI.

### Write PR descriptions from the branch diff

Describe what changed between the branch and its merge base. Commits get rebased, squashed and reordered, so the description follows the final diff:

```bash
git diff --stat $(git merge-base HEAD master)..HEAD       # files and line counts
git diff $(git merge-base HEAD master)..HEAD             # full diff
git diff $(git merge-base HEAD master)..HEAD -- <path>   # one file's final state
```

Structure the body as was → now for each affected area. When a branch is stacked on another open PR, say so at the top of the body and name the commits that belong to the base PR.

---

## Milestone = release that shipped it

A closed issue's milestone is the first release tag that contains the merge commit of the closing PR. Users running that version find the fix in its changelog.

```bash
# 1. Find the merge commit of the closing PR
git log --oneline master --grep="#<issue_number>" | head -5

# 2. Find the first non-rc tag that contains it
git tag --contains <merge_sha> | grep -v rc | sort -V | head -1
```

Move the issue with the milestone number (list numbers with `gh api "repos/{owner}/{repo}/milestones?state=all" --paginate`):

```bash
gh api --method PATCH "repos/{owner}/{repo}/issues/<n>" -F milestone=<milestone_number>
```

- Squashed or rebased merges can leave feature-branch SHAs off `master`; run `git tag --contains` on the merge commit found via `git log --grep=#<pr>` on master
- While the closing PR is open, the issue stays on the active development milestone; finalize after merge and release
- An issue closed weeks after its fix merged with the "in development" milestone is almost always wrong; check with `git tag --contains`

---

## Epic Hygiene

Audit three things together whenever you revisit an epic.

### 1. Duplicate subtask issues

Bulk-created subtasks can produce two issues for one scope (nearly identical titles created the same day). Scan a range of issue numbers around a known one:

```bash
for n in 8188 8189 8190; do
  gh issue view $n --json number,title,state,milestone,createdAt \
    --jq '"\(.number) [\(.state)] created=\(.createdAt[:10]) milestone=\(.milestone.title // "none") — \(.title)"'
done
```

Keep the issue with the richer history (more cross-references, correct milestone) and close the other:

```bash
gh issue comment <dup_n> --body "Closing as duplicate of #<keeper>. Work shipped via PR #NNNN in release X.Y.Z."
gh issue close <dup_n> --reason "not planned"
```

### 2. Milestones agree

Apply "milestone = release that shipped it" to every issue in the group, epic included, then verify:

```bash
for n in <epic> <subtask1> <subtask2>; do
  gh issue view $n --json number,milestone \
    --jq '"#\(.number) milestone=\(.milestone.title // "none")"'
done
```

### 3. The epic body links its subtasks

Add a `## Subtasks` section so readers can follow the trail:

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

---

## Security Advisory Management

Lifecycle: draft → published → request CVE. Use `gh api`.

### Listing advisories

```bash
gh api /repos/{owner}/{repo}/security-advisories --paginate \
  --jq '.[] | "\(.ghsa_id) state=\(.state) cve=\(.cve_id // "none")"'
```

### Re-opening a closed advisory

A closed advisory moves to `draft` before it can be published:

```bash
gh api --method PATCH /repos/{owner}/{repo}/security-advisories/{ghsa_id} \
  --input - <<< '{"state": "draft"}'
```

### Publishing with package metadata

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

Publish first. An empty `{}` response means GitHub accepted the request:

```bash
gh api --method POST /repos/{owner}/{repo}/security-advisories/{ghsa_id}/cve
```

### Closing a blocking PR on a private fork

Open PRs on the advisory's private fork block publishing. Close them first:

```bash
gh pr list --repo {owner}/{repo}-ghsa-{id}
gh pr close 1 --repo {owner}/{repo}-ghsa-{id}
```

### Gotchas

- Ecosystem for PHP packages is `"composer"`; `"php"` returns 422
- Request a CVE through the dedicated `/cve` POST endpoint; a `request_cve` key in PATCH is ignored
- Go `closed → draft → published`
- An existing vulnerability entry with an empty `ecosystem`/`name` gets fixed by PATCHing the `vulnerabilities` array before publishing
- Batch publishing goes by `created_at` ascending, so the earliest report of a vulnerability receives the CVE

### Handling duplicate advisories

When two advisories share a root cause:

1. Identify the original by `created_at`: `gh api /repos/ChurchCRM/CRM/security-advisories/{ghsa_id} --jq '.created_at'`
2. Publish the original first
3. When the duplicate already has a CVE, email security@github.com with both GHSA IDs and creation timestamps and ask that the duplicate CVE be marked `REJECTED` (GitHub is the CNA)
4. Request the CVE for the original: `gh api --method POST /repos/{owner}/{repo}/security-advisories/{original_ghsa}/cve`

A CVE is never un-assigned; the CNA marks it `REJECTED`, and the NVD record then references the canonical CVE.

### Notifying reporters via GitHub Discussion

Advisory comments have no REST or GraphQL API. Create a Discussion that @-mentions each reporter; they are notified automatically:

```bash
# Unique reporter logins from published advisories
gh api /repos/{owner}/{repo}/security-advisories --paginate \
  --jq '.[] | select(.state == "published") | .credits[].login' | sort -u

# Create the Discussion (look up repositoryId and categoryId first)
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

## Verify a fix is on master before closing an issue

A commit titled `Fix: X` can sit on an unmerged branch. Before closing an issue as fixed:

1. Search all branches: `git log --all --oneline --grep="<keyword from issue>"`
2. Confirm the candidate commit reached master: `git branch --contains <sha>` lists `master`
3. Or check master directly: `git log master --oneline --grep="closes #<issue>"` and `git log master --oneline -- <expected file path>`
4. Read the current file on master and confirm the buggy code is gone

When either check fails, leave the issue open and name the branch that holds the fix.

### Closing issues as "won't do"

Pair `--reason "not planned"` with removing the milestone in the same step, so milestone counts reflect release scope:

```bash
gh issue close 8056 --repo ChurchCRM/CRM \
  --reason "not planned" \
  --comment "Closing as won't do. <reason>."

gh issue edit 8056 --milestone "" --repo ChurchCRM/CRM
```

Issue closure follows `CLAUDE.md`: close only when the maintainer answers yes to a direct question.
