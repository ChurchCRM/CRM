# Release

Start a ChurchCRM release: `/release`. The version is `package.json` on `master`; there is no argument.

Read [`.agents/skills/churchcrm/release-management.md`](../../.agents/skills/churchcrm/release-management.md) now and follow it exactly, with [`docs/maintainers/release-process.md`](../../docs/maintainers/release-process.md) as the runbook. This command is a pointer, not a copy.

1. Run the skill's first step: `git checkout master && git pull --ff-only` in the main checkout, then read `version` from `package.json`.
2. Ask the maintainer the first question: "What is the next version?" That answer is `next_version` and the milestone every moved item goes to. Check that milestone exists; if it does not, ask the maintainer to create it.
3. Check against current GitHub state, in this order: draft High advisories, then locales (`locale/terms/missing/` empty and no open locale PR), then release notes (`changelog/<version>.md` written and approved, any PR carrying it merged), then nightly SHA = build SHA = `master` HEAD, then the milestone for that version. Locales and release notes come before the build and nightly because each merge moves HEAD and voids those results.
4. Report **READY FOR DRAFT** or **BLOCKED** with the evidence and each blocker, using the `next_version` the maintainer gave.

Stop there. Run **Release & Start Next**, apply release notes, and Publish only after the maintainer approves that specific action.

If the request names a phase ("check the advisories", "write the notes"), run only that part.
