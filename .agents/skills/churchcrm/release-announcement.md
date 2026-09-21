---
title: "Release Announcement"
intent: "Announce a published ChurchCRM release to the community: Discord post, notify reporters of fixed issues, surface docs PRs held for the release"
tags: ["release", "community", "discord", "workflow"]
prereqs: ["[[release-notes]]", "[[github-interaction]]"]
complexity: "beginner"
---
# Skill: Release Announcement

## Purpose

Everything that should happen for the *community* once a release is live on
GitHub, run on demand by the maintainer ("announce 7.7.0"). Three parts, each
independently runnable and safe to re-run — every step checks for its own
earlier output before writing.

This used to be a scheduled poll in the community agent. It is a skill now
because the maintainer already knows when a release happened; polling for it
cost wakes and occasionally announced late.

**Not covered here** — already automated by
[`sync-changelog.yml`](../../../.github/workflows/sync-changelog.yml) on
`release: published`: CHANGELOG sync, the social-media post drafts (see
`social-media-release.md`; the generated drafts are the `marketing-posts-<tag>`
workflow artifact), and closing/moving the milestone.

---

## Inputs

| Input | Default | Notes |
|-------|---------|-------|
| `TAG` | latest stable release | `gh release view --json tagName -q .tagName` |
| Repo | `ChurchCRM/CRM` | `gh repo view --json nameWithOwner -q .nameWithOwner` |
| Discord invite | `https://discord.gg/tuWyFzj3Nj` | used in the reporter comment |

```bash
TAG="${TAG:-$(gh release view --json tagName -q .tagName)}"
gh release view "$TAG" --json name,tagName,url,body,isPrerelease,isDraft
```

Stop if `isPrerelease` or `isDraft` is true — never announce those.

---

## Step 1 — Discord announcement

Post to the community announcements channel (or hand the finished text to the
community agent if you cannot post yourself — it is the single public voice
and will post it verbatim).

Format:
- Release name/version in the first line.
- 2–4 real highlights **summarized from the release body** — not the
  changelog dumped, not marketing adjectives. If the body is long, attach it
  as a `.md` file and keep the message to the highlights (Discord's 2,000
  char limit).
- Credit contributors by name when the release notes list them.
- Links are **cards with buttons, never bare or markdown URLs** (they render
  as dead text through the bot). Primary button: the install/getting-started
  page with UTM params —
  `?utm_source=discord&utm_medium=announcement&utm_campaign=release-<tag>`.
  Second button: the GitHub release (`url`).

Before posting, search the channel for the tag — a duplicate check is cheap,
a duplicate announcement looks broken.

---

## Step 2 — Tell reporters their issue shipped

```bash
gh issue list --state closed --milestone "$TAG" --json number,title --limit 200
```

For each issue, read its comments first; skip it if a "fixed in `<tag>`"
comment already exists. Otherwise post one comment:

> This has been addressed as part of release **<tag>** (<release url>).
> Please retest when you get a chance — if it's resolved, no action needed;
> if you're still seeing the issue, reopen this one (or comment here) and
> we'll take another look. You're also welcome to chat with us on Discord:
> https://discord.gg/tuWyFzj3Nj

Say plainly if the list is empty (common for a patch release). **Blind
spot:** only milestoned issues surface here — an issue closed by a PR's
`closes #NNNN` with no milestone won't. If that keeps happening, tell the
maintainer rather than silently under-covering release after release.

---

## Step 3 — Docs PRs held for this release

Docs describing an unreleased fix are wrong for everyone reading them today,
so docs PRs in **`ChurchCRM/docs.churchcrm.io`** are held as drafts until the
code ships — tagged with the source PR's milestone when it had one, or the
`docs-pending-release` label when it didn't. On release, list both:

```bash
DOCS=ChurchCRM/docs.churchcrm.io
gh pr list -R "$DOCS" --state open --search "milestone:$TAG" --json number,title,url,isDraft
gh pr list -R "$DOCS" --state open --label docs-pending-release --json number,title,url,isDraft
```

Hand the maintainer the list with the release note — they are ready to merge
now. **Do not merge them yourself.** Call out any open docs PR with neither a
milestone nor the label: that is the one case that never gets swept up.

---

## Report

One short Markdown block back to the maintainer:

```
## Release <tag> — community actions
- Discord: posted | already posted | handed to community agent
- Reporters notified: N issues (skipped M already commented) | none milestoned
- Docs PRs ready to merge: #… #… | none | ⚠ #… has no milestone
```
