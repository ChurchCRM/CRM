# Release Announcement

Run the owner-invoked post-release workflow defined in [`.agents/skills/churchcrm/release-announcement.md`](../../.agents/skills/churchcrm/release-announcement.md): Discord announcement, notify reporters whose issue shipped, and list docs PRs held for the release.

Read that skill file now and follow it exactly — Inputs, Step 1–3, and the Report block are the actual instructions, including the untrusted-content rule and the requirement to show the reporter-notification target list and wait for an explicit go before the first comment.

If no tag is given, it defaults to the latest stable release per the skill's Inputs section.
