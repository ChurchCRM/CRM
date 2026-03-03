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
