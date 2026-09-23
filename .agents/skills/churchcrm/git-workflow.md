---
title: Git Workflow
intent: How agents commit and push on ChurchCRM.
---

# Git Workflow

## Must

- Branch from current `master`. Name: `fix/issue-N-short` or `feature/short`
- Every PR links an open issue
- Imperative commit subject, &lt; 72 chars
- `npm run lint` and the matching build before you ask to commit
- Show the diff. Do not commit or push until the maintainer says yes
- Never `--force`. `--force-with-lease` only after an approved rebase
- Do not merge `master` into a contributor branch or push to their branch unless asked
- Do not run `locale:build` or commit `messages.po` on a feature branch

## After push

Do not approve or merge. Do not close issues unless the maintainer answers yes to a direct question.
