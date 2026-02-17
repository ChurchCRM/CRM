# Skill: GitHub Interaction for Code Reviews and PRs

## Context
This skill covers how to address code review comments, commit changes, and create pull requests (PRs) following ChurchCRM's standards and templates.

---

## Addressing Code Review Comments

1. **Understand the Feedback**:
   - Read all comments carefully.
   - If unclear, ask for clarification in the PR discussion.

2. **Make Changes**:
   - Address each comment in the code.
   - Ensure changes align with ChurchCRM's coding standards (see [Code Standards](./code-standards.md)).

3. **Mark Comments as Resolved**:
   - Once a comment is addressed, mark it as resolved in the GitHub interface.
   - Add a reply if necessary to explain your changes.

4. **Test Changes**:
   - Run all relevant tests (e.g., Cypress tests) to ensure no regressions.
   - Clear logs before testing:
     ```bash
     rm -f src/logs/$(date +%Y-%m-%d)-*.log
     ```

---

## Committing Changes

1. **Stage Changes**:
   ```bash
   git add <file1> <file2>
   ```

2. **Write a Descriptive Commit Message**:
   - Use imperative mood (e.g., "Fix validation in Checkin form").
   - Reference the issue number if applicable (e.g., "Fix issue #1234: Correct validation logic").

3. **Commit the Changes**:
   ```bash
   git commit -m "<commit message>"
   ```

4. **Verify the Commit**:
   - Ensure the commit includes only the intended changes.
   - Run `git diff --staged` to review staged changes.

---

## Creating a Pull Request (PR)

1. **Push the Branch**:
   ```bash
   git push origin <branch-name>
   ```

2. **Open a PR**:
   - Use the GitHub interface to create a PR.
   - Select the correct base branch (e.g., `master`).

3. **Follow the PR Template**:
   - Fill out all sections of the PR template:
     - **Summary**: Brief overview of changes.
     - **Changes**: Detailed list of modifications.
     - **Why**: Motivation and benefits.
     - **Files Changed**: List of modified/added/deleted files.
   - Use Markdown for formatting.

4. **Request Reviewers**:
   - Add relevant reviewers based on the codebase area.

5. **Link Issues**:
   - If the PR resolves an issue, link it using keywords like `Fixes #1234`.

6. **Run CI Checks**:
   - Ensure all CI checks pass before requesting a merge.

---

## Best Practices

- **Small, Focused Commits**:
  - Keep commits small and focused on a single change.

- **Descriptive PR Titles**:
  - Use clear and concise titles (e.g., "Fix issue #5678: Update API validation").

- **Respond to Feedback Promptly**:
  - Address review comments in a timely manner.

- **Test Thoroughly**:
  - Run all relevant tests before pushing changes.

- **Follow Templates**:
  - Always use ChurchCRM's commit and PR templates for consistency.

---

For more details, refer to the [Development Workflows](./development-workflows.md) and [Code Standards](./code-standards.md) skills.