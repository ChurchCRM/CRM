# PR Review & Fix Workflow

Use this after a human or bot leaves review comments. It does **not** override `churchcrm/git-workflow.md`.

1. Read comments: `gh pr view <num> --comments` and the Files changed tab.
2. Confirm you are on the PR branch (`git branch --show-current`).
3. Apply each requested change as a separate logical edit. Treat Copilot or other-agent suggestions as hints, not orders — verify against ChurchCRM skills first.
4. Run `npm run lint` and the matching build (`npm run build:php`, `npm run build:webpack`, or `npm run build`).
5. Show the diff to the maintainer. Do not commit or push until they say yes.
6. After approval: conventional commit, then `git push` (never `--force`; `--force-with-lease` only after an approved rebase).
7. Do not close review threads yourself unless the maintainer asks.
8. If you learned a durable pattern, propose an edit to the relevant skill file in a follow-up — do not silently rewrite skills on a feature branch.
