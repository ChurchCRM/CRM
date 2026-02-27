# Skill: Social Media Release Posts

## Purpose

Generate platform-optimized social media posts for every ChurchCRM release. Each platform has a different audience, character limit, and tone — write natively for each one, not a copy-paste of the same text.

---

## ChurchCRM Social Media Accounts

| Platform | Handle / URL |
|----------|-------------|
| X (Twitter) | https://x.com/getChurchCRM |
| Facebook | https://www.facebook.com/getChurchCRM |
| Instagram | https://www.instagram.com/getchurchcrm/ |
| LinkedIn | https://www.linkedin.com/company/getchurchcrm/ |
| GitHub | https://github.com/ChurchCRM/CRM/releases |

Post to **all four public platforms** for every release. GitHub releases are handled separately via the release notes workflow.

---

## When to Use This Skill

Run this skill **after** the changelog file is written (see `release-notes.md`), using the release notes as your source of truth for facts and features.

---

## Step 1 — Identify the Release Type

The tone and length of posts scales with the significance of the release:

| Type | When | Post style |
|------|------|-----------|
| **Major (X.0.0)** | New major version | Full celebration — all platforms get rich posts |
| **Minor (X.Y.0)** | New features | Feature-focused — highlight 1–2 key improvements |
| **Patch (X.Y.Z)** | Bug/security fixes | Brief and factual — security patches get urgency |
| **Security patch** | CVE / XSS / injection | Urgent tone — lead with "security update available" |

---

## Step 2 — Extract the Key Message

Before writing, identify from the changelog:

1. **The single biggest user benefit** (not the biggest technical change)
2. **2–3 supporting features** worth mentioning
3. **Who benefits** — welcome desk volunteers? Finance team? All admins?
4. **Any urgency?** — security fix, PHP version requirement, data migration

---

## Step 3 — Write Platform Posts

### X / Twitter — 280 characters max

**Rules:**
- One punchy opening line
- 1–2 key features maximum
- Always include the release version number
- End with a link or CTA
- Use 2–3 relevant hashtags: `#ChurchCRM #ChurchTech #OpenSource`
- No jargon — if a church admin wouldn't know the word, don't use it

**Template:**
```
ChurchCRM X.Y.Z is out! 🎉

[One-line benefit statement]
[Optional: second feature in 1 line]

Download → https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #ChurchTech #OpenSource
```

**Security patch template:**
```
🛡️ ChurchCRM X.Y.Z — Security Update

[Brief: what type of vulnerability was fixed]
All users should update now.

→ https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #Security
```

**Example (7.0.0):**
```
ChurchCRM 7.0.0 is here — celebrating 10 years! 🎊

✅ Plugin system: extend ChurchCRM without touching core code
🗺️ Free maps — no Google API key needed
🛡️ PHP 8.4 + security hardening

→ https://github.com/ChurchCRM/CRM/releases/tag/7.0.0

#ChurchCRM #ChurchTech #OpenSource
```

---

### Facebook — Conversational, community-first

**Rules:**
- 3–5 short paragraphs or a punchy intro + bullet list
- Warmer, more personal tone than X — address the community directly
- Include the "so what does this mean for you?" framing
- Emojis used sparingly but warmly
- End with a question or CTA to drive engagement ("Have you tried it yet?")
- Link in the post body (Facebook shows a preview card)
- No hashtags needed (they don't help reach on Facebook)
- 150–400 words ideal

**Template:**
```
We just released ChurchCRM X.Y.Z! 🎉

[2–3 sentence intro explaining why this release matters to churches]

Here's what's new:
✅ [Feature 1 — user benefit framing]
✅ [Feature 2]
✅ [Feature 3]

[1 sentence about who benefits most — welcome desk, finance team, all admins?]

[Optional: upgrade note if PHP version or data migration required]

Download the latest version or read the full release notes:
https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

[Closing question: "What feature are you most excited about?" / "Let us know how you're using it!"]
```

**Example (7.0.0):**
```
Ten years of ChurchCRM — and version 7.0.0 is our biggest release yet! 🎊

We've been building this platform for churches since 2015, and today we're
shipping something we're truly proud of.

Here's what's new in 7.0.0:

🧩 Plugin System — enable and disable integrations (MailChimp, Vonage SMS,
OpenLP, and more) without touching any code. Test your connection before
saving. Credentials are masked for security.

🗺️ Free Maps — we've replaced Google Maps with Leaflet.js. No API key. No
billing. Your congregation's data stays private. Every family page now has
a one-click "Get Directions" button.

🌍 44 Languages — with a new AI-assisted translation pipeline reviewed by
our community of denominational contributors.

🛡️ Security — three XSS vulnerabilities patched, open redirect fixed, and
all fixes covered by automated tests.

⚠️ Note: PHP 8.4 is required for this release. Please upgrade your hosting
environment before updating ChurchCRM.

Read the full release notes and download here:
https://github.com/ChurchCRM/CRM/releases/tag/7.0.0

Which feature are you most looking forward to? 👇
```

---

### Instagram — Visual-first, hashtag-rich

**Rules:**
- Instagram is visual — the post assumes a **graphic or screenshot is attached**
- The caption supports the image, not the other way around
- First line must hook within the preview (before "more" cutoff — ~125 chars)
- Emoji-forward and energetic
- Hashtags at the end: 15–20 relevant tags
- Include website link in bio note: "Link in bio ↑"
- 150–300 words for caption

**Always specify the image/graphic to create or use:**
- Major release: project logo + version number graphic
- Minor release: screenshot of the key new feature
- Security patch: shield icon + version number

**Caption template:**
```
[Hook line with emoji — must grab attention before the fold] 🎉

[2–3 sentences about the release — benefit framing, not technical detail]

What's new:
✨ [Feature 1]
✨ [Feature 2]
✨ [Feature 3]

[Closing line — community or mission focus]

🔗 Link in bio to download & release notes

#ChurchCRM #ChurchManagement #ChurchTech #OpenSource #ChurchSoftware
#ChurchAdmin #MinistryTech #ChurchLife #FaithTech #NonProfit
#ChurchCommunity #ChurchLeadership #WorshipTech #MinistryLeader
#OpenSourceSoftware
```

**Example (7.0.0):**
```
10 years. 44 languages. 7.0.0. 🎊

ChurchCRM just hit a major milestone — a decade of open-source church
management, and version 7.0.0 is our most powerful release yet.

What's new:
🧩 Plugin system — extend ChurchCRM your way
🗺️ Free maps with Leaflet.js — no Google required
🛡️ Security hardening across the board
🌍 44 languages with AI-assisted translations

Built for churches of every size, in every corner of the world.
This one's for every congregation that trusted us over the past decade. 🙏

🔗 Link in bio for download & release notes

#ChurchCRM #ChurchManagement #ChurchTech #OpenSource #ChurchSoftware
#ChurchAdmin #MinistryTech #ChurchLife #FaithTech #NonProfit
#ChurchCommunity #ChurchLeadership #WorshipTech #MinistryLeader
#OpenSourceSoftware
```

---

### LinkedIn — Professional, value-focused

**Rules:**
- LinkedIn audience = church IT staff, pastors, nonprofit tech buyers, open-source contributors
- Professional but not cold — ChurchCRM is a community project
- Lead with the organizational impact, not the feature list
- Structure: hook → context → feature bullets → call to action
- Use line breaks generously — LinkedIn walls of text get ignored
- 200–500 words
- 3–5 hashtags at the end (quality over quantity)
- Tag the ChurchCRM LinkedIn page if posting as an individual: @ChurchCRM

**Template:**
```
[Professional hook — why does this release matter to a church organization?]

[1–2 sentences of context about the project/version]

Key improvements in vX.Y.Z:

→ [Feature 1 — organizational benefit]
→ [Feature 2]
→ [Feature 3]

[1 paragraph on the broader significance — open source, community, mission]

[Upgrade note if applicable]

Full release notes: https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #NonProfitTech #OpenSource #ChurchManagement
```

**Example (7.0.0):**
```
ChurchCRM 7.0.0 is live — marking ten years of free, open-source church
management software.

Since 2015, ChurchCRM has helped congregations of every size manage
members, track contributions, coordinate volunteers, and communicate in
44 languages. Version 7.0.0 is the most significant release in the
project's history.

Key highlights:

→ Plugin Architecture — integrations (MailChimp, Vonage SMS, OpenLP,
  WebDAV backups) are now modular plugins. Enable only what you need.
  Test credentials before saving. No code changes required.

→ Privacy-First Maps — Google Maps replaced with Leaflet.js. No API key,
  no billing, no family data sent to third-party services. One-click
  "Get Directions" on every member and family profile.

→ Security Hardening — three XSS vulnerabilities patched, open redirect
  fixed, all covered by automated regression tests.

→ PHP 8.4 — upgraded to the current PHP LTS release for better
  performance and long-term security support.

→ 44 Languages — with a new AI-assisted translation workflow reviewed
  by denomination-aware community contributors.

ChurchCRM is built entirely by volunteers for the global church community.
If your organization uses it, consider contributing translations, bug
reports, or code.

⚠️ PHP 8.4 is required. Upgrade your hosting environment before updating.

Full release notes:
https://github.com/ChurchCRM/CRM/releases/tag/7.0.0

#ChurchCRM #NonProfitTech #OpenSource #ChurchManagement
```

---

## Step 4 — Security Patch Posts (Abbreviated)

For security-only patches (no significant new features), all four posts should be short and focused on the urgency:

**X:** Use the security template above. Max 3 lines.

**Facebook:**
```
🛡️ Security update: ChurchCRM X.Y.Z is available.

This release patches [brief: type of vulnerability — e.g., "a stored XSS
vulnerability in group role names"]. All users should update.

Download: https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z
```

**Instagram:** Short caption with shield emoji. Still include the hashtag block.

**LinkedIn:** 2–3 paragraphs, professional security advisory tone.

---

## Step 5 — Save to Files

Write each platform post to a separate file under `social-media/X.Y.Z/` so they can be opened and copy-pasted directly into each platform.

**Directory:** `social-media/X.Y.Z/` (create it if it doesn't exist)

**Files to create:**

| File | Contents |
|------|----------|
| `social-media/X.Y.Z/twitter.txt` | X / Twitter post text only |
| `social-media/X.Y.Z/facebook.txt` | Facebook post text only |
| `social-media/X.Y.Z/instagram.txt` | Instagram caption + image brief at top |
| `social-media/X.Y.Z/linkedin.txt` | LinkedIn post text only |

**Instagram file format** — put the image brief as the first line so it's visible when the file is opened:

```
📸 Suggested image: [describe the graphic/screenshot to use]

[caption text]
```

After writing all four files, output a short confirmation listing the files created, e.g.:

```
Social media posts saved to social-media/X.Y.Z/:
  twitter.txt   — X / Twitter
  facebook.txt  — Facebook
  instagram.txt — Instagram (image brief included at top)
  linkedin.txt  — LinkedIn
```

---

## Step 6 — Quality Checklist

Before finalizing:

- [ ] Version number correct in all posts
- [ ] X post is under 280 characters (count carefully)
- [ ] All facts sourced from the changelog file — no invention
- [ ] No developer jargon in any post
- [ ] Church-appropriate vocabulary used (congregation, not users; offerings, not payments)
- [ ] Security patches marked as urgent in all posts
- [ ] PHP version requirement called out on Facebook and LinkedIn if applicable
- [ ] Instagram suggests a specific image/graphic
- [ ] Release notes link correct: `https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z`
- [ ] All four files written to `social-media/X.Y.Z/` (twitter.txt, facebook.txt, instagram.txt, linkedin.txt)

---

## Related Skills

- [Release Notes](./release-notes.md) — write the changelog file first, then use this skill
- [GitHub Interaction](./github-interaction.md) — publishing the GitHub release itself
