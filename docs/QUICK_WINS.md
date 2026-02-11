# Quick Wins: GitHub Features to Enable

This document provides a prioritized list of GitHub features that ChurchCRM can quickly enable to improve security, automation, and community engagement.

## 🔴 Critical Priority - Implement This Week

### 1. Enable Dependabot (5 minutes)
**File**: Create `.github/dependabot.yml`

```yaml
version: 2
updates:
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
    labels: ["dependencies", "Package Dependencies"]
    
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
    labels: ["dependencies", "Package Dependencies"]
    
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "monthly"
    labels: ["dependencies", "build"]
```

**Why**: Automatic security updates and dependency management for npm, Composer, and GitHub Actions.

---

### 2. Enable CodeQL Security Scanning (10 minutes)
**File**: Create `.github/workflows/codeql.yml`

```yaml
name: "CodeQL Security Analysis"

on:
  push:
    branches: [ master ]
  pull_request:
    branches: [ master ]
  schedule:
    - cron: '0 6 * * 1'

jobs:
  analyze:
    name: Analyze
    runs-on: ubuntu-latest
    permissions:
      security-events: write
    strategy:
      matrix:
        language: [ 'javascript', 'php' ]
    steps:
      - uses: actions/checkout@v4
      - uses: github/codeql-action/init@v3
        with:
          languages: ${{ matrix.language }}
      - uses: github/codeql-action/autobuild@v3
      - uses: github/codeql-action/analyze@v3
```

**Why**: Advanced security scanning for SQL injection, XSS, and other vulnerabilities in PHP/JavaScript code.

---

### 3. Configure Branch Protection Rules (2 minutes)
**Location**: Settings → Branches → Add rule

**Settings for `master` branch**:
- ✅ Require pull request before merging
- ✅ Require 1 approval
- ✅ Require status checks: `Typecheck & Lint`, `test-n-package`
- ✅ Require conversation resolution
- ✅ Do not allow bypassing

**Why**: Prevent direct commits to master, enforce code review and CI checks.

---

## 🟡 High Priority - Next 2 Weeks

### 4. Set Up GitHub Sponsors (5 minutes)
**File**: Create `.github/FUNDING.yml`

```yaml
github: [ChurchCRM]
# patreon: churchcrm
# open_collective: churchcrm
```

**Why**: Enable sustainable funding for development and infrastructure costs.

---

### 5. Add Workflow Concurrency (2 minutes per workflow)
**Update**: Add to existing workflows

```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```

**Why**: Save CI minutes by cancelling redundant workflow runs on rapid commits.

---

### 6. Create GitHub Environments (10 minutes)
**Location**: Settings → Environments

**Create**:
- `production` (with approval required)
- `demo` (no restrictions)
- `staging` (no restrictions)

**Why**: Deployment protection, environment-specific secrets, deployment history.

---

## 🟢 Nice to Have - Future Consideration

### 7. Expand CODEOWNERS
Assign specific teams/people to different directories:

```
/.github/           @ChurchCRM/infrastructure
/src/api/           @ChurchCRM/api-team
/locale/            @ChurchCRM/localization
```

---

### 8. GitHub Projects v2
Create project boards for:
- Release roadmap
- Bug triage
- Feature backlog

---

## Implementation Checklist

**Week 1** (Security Focus):
- [ ] Enable Dependabot
- [ ] Enable CodeQL
- [ ] Configure branch protection

**Week 2** (Operations):
- [ ] Set up GitHub Sponsors
- [ ] Add workflow concurrency
- [ ] Create GitHub Environments

**Ongoing**:
- [ ] Expand CODEOWNERS
- [ ] Create GitHub Projects

---

## Additional Resources

- **Full Analysis**: See `docs/github-features-analysis.md` for detailed explanations
- **GitHub Docs**: https://docs.github.com
- **Security Best Practices**: https://docs.github.com/en/code-security

---

**Questions?** Open a discussion in [ChurchCRM/CRM Discussions](https://github.com/ChurchCRM/CRM/discussions)!
