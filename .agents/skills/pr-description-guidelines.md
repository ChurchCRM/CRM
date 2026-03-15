# PR Description Guidelines

When creating a pull request, always author the PR description in Markdown and include the following sections:

- **Summary**: One-paragraph summary of the change.
- **Changes**: Short bullets describing what changed and why.
- **Files Changed**: List of key files or modules changed; include links when useful.
- **Validation**: How the change was validated (lint, unit tests, manual steps).
- **Testing Instructions**: Steps a reviewer can follow to verify the change locally.

Use code blocks and Markdown lists to make examples and commands easy to copy. Keep descriptions concise and focused on reviewer needs.

Repository PR template
---------------------

This repository provides a PR template at `.github/PULL_REQUEST_TEMPLATE.md`. When opening a PR, use that template as the base and fill each section before creating the PR. If using the GitHub CLI you can pass the template as the body file:

```bash
gh pr create --title "<short title>" --body-file .github/PULL_REQUEST_TEMPLATE.md --base master --head <branch>
```

Ensure the filled template remains in Markdown and includes the required sections (What Changed, Type, Testing, Pre-Merge checklist).

Safe workflow for using the repository template
----------------------------------------------

Do NOT edit `.github/PULL_REQUEST_TEMPLATE.md` in-place. Always create a temporary copy, edit that copy, and use it when creating the PR. Example workflow (bash):

```bash
# create a timestamped copy in /tmp (do not write to repo template)
TMP_BODY="/tmp/pr_body_$(date +%s).md"
cp .github/PULL_REQUEST_TEMPLATE.md "$TMP_BODY"

# open the copy in the user's editor
${EDITOR:-vi} "$TMP_BODY"

# create the PR using the copy as the body; this leaves the repo template untouched
gh pr create --title "<short title>" --body-file "$TMP_BODY" --base master --head <branch>

# optionally remove the temporary file when done
rm -f "$TMP_BODY"
```

Agents and scripts should follow this pattern whenever generating or programmatically editing PR bodies.

Resolving review comments after pushing
----------------------------------------

After pushing fixes for PR review comments, **always resolve every addressed thread**. There is no native `gh pr` subcommand for this — use `gh api graphql` with the `resolveReviewThread` mutation.

**Workflow:**

```bash
# 1. Get all unresolved thread node IDs
gh api graphql -f query='
{
  repository(owner: "OWNER", name: "REPO") {
    pullRequest(number: PR_NUMBER) {
      reviewThreads(first: 50) {
        nodes {
          id
          isResolved
          comments(first: 1) { nodes { databaseId } }
        }
      }
    }
  }
}' --jq '.data.repository.pullRequest.reviewThreads.nodes[] | select(.isResolved == false) | .id'

# 2. Resolve each thread
gh api graphql -f query='mutation {
  resolveReviewThread(input: {threadId: "THREAD_NODE_ID"}) {
    thread { id isResolved }
  }
}'
```

**Loop to resolve all at once:**

```bash
for thread_id in <id1> <id2> ...; do
  gh api graphql -f query="mutation { resolveReviewThread(input: {threadId: \"$thread_id\"}) { thread { isResolved } } }"
done
```

gh CLI preference
-----------------

Always prefer native `gh` subcommands (`gh pr`, `gh issue`, `gh repo`, etc.) over raw `gh api` REST calls. Use `gh api graphql` only for operations with no native command (e.g., resolving review threads, complex queries).
