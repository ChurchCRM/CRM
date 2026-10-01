# Repo Health Check

Run the on-demand GitHub health snapshot defined in [`.agents/skills/churchcrm/repo-health.md`](../../.agents/skills/churchcrm/repo-health.md): approved PRs waiting to merge, the good-first-issue pipeline, community-profile hygiene, and stale-branch cleanup.

Read that skill file now and follow it exactly — its Setup, Check 1–4, and Report sections are the actual instructions. This command is a pointer, not a copy, so the skill file is the one place to update when the checks change.

If the request names a specific check ("any approved PRs waiting?", "clean up stale branches"), run only that check. Otherwise run all four, worst-first, per the skill's Report section.
