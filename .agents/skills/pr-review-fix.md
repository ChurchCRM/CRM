# PR Review & Fix Workflow

Use this after a human or bot leaves review comments. It follows `churchcrm/git-workflow.md`.

1. Read every thread: the GraphQL call in `churchcrm/pr-review.md` Phase 1 (inline threads from people and bots, plus review states), `gh pr view <num> --comments`, and the Files changed tab.
2. Confirm you are on the PR branch (`git branch --show-current`).
3. Apply each requested change as a separate logical edit. Treat Copilot and other-agent suggestions as hints; verify each against the ChurchCRM skills.
4. Run `npm run lint` and the matching build (`npm run build:php`, `npm run build:webpack`, or `npm run build`).
5. Show the diff to the maintainer and wait for yes before commit.
6. Push only when the latest message says push. Use a conventional commit; use `--force-with-lease` only after an approved rebase.
7. After the push, check each thread against the pushed code and resolve the ones it fixed. Reply with the commit SHA when the fix is not obvious. Leave unfixed threads open with a reply and list them in your report.
8. Propose a durable pattern as a skill edit in a separate follow-up PR.

## Bot review comments

CodeRabbit reads the diff and the skill cards. It finds repo-rule slips, missing tests for changed behavior and unescaped output. It does not run the stack, so it misses runtime coupling (seed counters, spec order, a 404 on a signed-in route); CI finds those.

- Verify each finding against the code. Fix a real one. A finding it labels Minor, Quick win or preventive rides the next push that has to happen anyway, because every push restarts CI. Say so in one line on the thread and resolve it.
- It reviews a limited number of times per hour and pauses after a few commits, so later commits can go unreviewed. Its pre-merge checks update only on a review: a gap you fixed stays red until the next one, and a `changes_requested` review from the bot clears the same way.
- A bot editing a thread you resolved, a refreshed summary comment and a "thanks" reply are echoes. They need no action.
- A required check that never started because of `paths:` in the workflow is not a failure (`ci-test-infrastructure.md`).
