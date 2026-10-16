# PR Review & Fix Workflow

Use this after a human or bot leaves review comments. It follows `churchcrm/git-workflow.md`.

1. Read comments: `gh pr view <num> --comments` and the Files changed tab.
2. Confirm you are on the PR branch (`git branch --show-current`).
3. Apply each requested change as a separate logical edit. Treat Copilot and other-agent suggestions as hints; verify each against the ChurchCRM skills.
4. Run `npm run lint` and the matching build (`npm run build:php`, `npm run build:webpack`, or `npm run build`).
5. Show the diff to the maintainer and wait for yes before commit.
6. Push only when the latest message says push. Use a conventional commit; use `--force-with-lease` only after an approved rebase.
7. After the push, check each thread against the pushed code and resolve the ones it fixed. Reply with the commit SHA when the fix is not obvious. Leave unfixed threads open with a reply and list them in your report.
8. Propose a durable pattern as a skill edit in a separate follow-up PR.
