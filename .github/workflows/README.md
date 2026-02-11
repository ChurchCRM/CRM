# GitHub Workflows Documentation

This directory contains automated GitHub Actions workflows for the ChurchCRM repository.

## Workflow: Review Closed Issues Without PRs

**File:** `review-closed-issues.yml`

### Purpose
This workflow automatically identifies closed issues that may need manual review by maintainers. Specifically, it:

1. Scans all closed issues in the repository
2. Filters for issues with the "stale" label (indicating they were auto-closed)
3. Checks if each issue has an associated Pull Request
4. Tags issues without PRs with the "needs-manual-review" label

### Why This Matters
The stale bot automatically closes inactive issues after a certain period. However, some legitimate issues may be closed without being properly resolved. This workflow helps maintainers identify such cases by:

- Finding issues that were auto-closed without a fix
- Preventing valid bug reports from being lost
- Ensuring community contributions aren't overlooked

### Schedule
- **Automatic:** Runs weekly on Mondays at 2:00 AM UTC
- **Manual:** Can be triggered manually via the Actions tab

### Manual Trigger
To manually run this workflow:

1. Go to the **Actions** tab in GitHub
2. Select **"Review Closed Issues Without PRs"**
3. Click **"Run workflow"**
4. Choose options:
   - **Dry run:** Check the box to see what would happen without making changes
   - **Branch:** Select the branch (usually `master`)
5. Click **"Run workflow"**

### How It Works

#### Step 1: Fetch Closed Issues
The workflow fetches all closed issues from the repository using pagination (up to 5,000 issues).

#### Step 2: Filter by "stale" Label
It filters the issues to only those marked with the "stale" label, which indicates they were likely auto-closed by the stale bot.

#### Step 3: Check for Linked PRs
For each stale issue, the workflow:
- Queries the issue's timeline using the GitHub API
- Looks for cross-reference events to Pull Requests
- Identifies if any PR fixed or mentioned the issue

#### Step 4: Tag for Review
Issues without linked PRs are tagged with the "needs-manual-review" label, making them easy for maintainers to find and evaluate.

### Output
The workflow generates a detailed log showing:
- Total number of closed issues
- Number of issues with the "stale" label
- Number of issues without associated PRs
- List of specific issues that were tagged

### Example Output
```
===============================================
Starting review of closed issues without PRs
Dry run mode: false
===============================================

Fetching closed issues from repository...
  Page 1: Found 100 closed issues
  Page 2: Found 75 closed issues

Total closed issues found: 175

Issues with "stale" label: 23

Checking for associated PRs...

  Issue #1234: No linked PRs found - needs review
  Issue #1256: Has 1 linked PR(s) - skipping
  Issue #1278: No linked PRs found - needs review

===============================================
Issues needing manual review: 2
===============================================

Tagging issues for manual review...

  ✓ Added "needs-manual-review" label to issue #1234
  ✓ Added "needs-manual-review" label to issue #1278

===============================================
Review complete!
===============================================

📊 Summary:
  Total closed issues: 175
  Issues with "stale" label: 23
  Issues without PRs: 2
  Issues tagged for review: 2

🔍 Issues tagged for manual review:
  - Issue #1234: Feature request that was auto-closed
    URL: https://github.com/ChurchCRM/CRM/issues/1234
  - Issue #1278: Bug report without resolution
    URL: https://github.com/ChurchCRM/CRM/issues/1278
```

### Maintainer Actions
When you see issues with the "needs-manual-review" label:

1. **Review the issue** to determine if it's still valid
2. **Take appropriate action:**
   - If resolved: Remove the label and close as completed
   - If invalid: Remove the label and mark as "not planned"
   - If still valid: Remove "stale" label, reopen, and address
3. **Remove the "needs-manual-review" label** once handled

### Permissions Required
- `contents: read` - To access repository data
- `issues: write` - To add labels to issues

### Rate Limiting
The workflow processes up to 50 pages of issues (5,000 issues max) to prevent API rate limiting. This should be sufficient for most repositories.

### Related Workflows
- **`stale.yml`** - Automatically marks inactive issues as stale and closes them
- **`issue-comment.yml`** - Posts helpful comments on new issues

---

## Contributing
To modify or add new workflows, please ensure:
1. YAML syntax is valid (`yamllint` passes)
2. Appropriate permissions are set
3. Documentation is updated
4. The workflow is tested in a fork first
