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

Ensure the filled template remains in Markdown and includes the required sections (What Changed, Type, Testing, Security Check, Pre-Merge checklist).
