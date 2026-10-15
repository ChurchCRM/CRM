---
title: "Social Media Release Posts"
intent: "Generate platform-optimized social media posts for every ChurchCRM release"
tags: ["release", "social-media", "workflow"]
prereqs: ["[[release-notes]]"]
complexity: "beginner"
---
# Skill: Social Media Release Posts

## Purpose

Write native posts for X, Facebook, Instagram and LinkedIn for each release. Each platform has its own audience, length and tone. Run this after the changelog is written (`release-notes.md`); the release notes are the source of every fact.

## Accounts

| Platform | URL |
|----------|-----|
| X | https://x.com/getChurchCRM |
| Facebook | https://www.facebook.com/getChurchCRM |
| Instagram | https://www.instagram.com/getchurchcrm/ |
| LinkedIn | https://www.linkedin.com/company/getchurchcrm/ |

The GitHub release itself follows `release-notes.md`.

---

## Step 1 — Release Type

| Type | Post style |
|------|-----------|
| Major (X.0.0) | Full celebration, rich posts on every platform |
| Minor (X.Y.0) | Feature-focused, 1-2 key improvements |
| Patch (X.Y.Z) | Brief and factual |
| Security patch | Urgent: lead with "security update available" |

## Step 2 — Key Message

From the changelog, identify:

1. The single biggest user benefit (the biggest technical change is a different question)
2. 2-3 supporting features
3. Who benefits (welcome desk volunteers, finance team, all admins)
4. Any urgency: security fix, PHP version requirement, data migration

## Step 3 — Platform Posts

### X — 280 characters max

- One punchy opening line, 1-2 features, the version number, a link
- 2-3 hashtags: `#ChurchCRM #ChurchTech #OpenSource`
- Words a church admin knows

```
ChurchCRM X.Y.Z is out! 🎉

[One-line benefit statement]
[Optional: second feature in 1 line]

Download → https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #ChurchTech #OpenSource
```

Security patch:

```
🛡️ ChurchCRM X.Y.Z — Security Update

[Brief: type of vulnerability fixed]
All users should update now.

→ https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #Security
```

### Facebook — conversational, community-first

- 150-400 words: a punchy intro plus a bullet list, or 3-5 short paragraphs
- Warm tone addressed to the community, framed as "what this means for you"
- Sparing emojis; no hashtags
- Link in the post body (Facebook shows a preview card)
- End with a question or call to action

```
We just released ChurchCRM X.Y.Z! 🎉

[2-3 sentence intro: why this release matters to churches]

Here's what's new:
✅ [Feature 1, framed as user benefit]
✅ [Feature 2]
✅ [Feature 3]

[1 sentence on who benefits most]

[Optional: upgrade note for a PHP version or data migration]

Download the latest version or read the full release notes:
https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

[Closing question: "What feature are you most excited about?"]
```

### Instagram — visual-first

- The caption supports an attached graphic or screenshot. Name the image to create or use: logo plus version number (major), screenshot of the key feature from the Playwright capture pipeline (minor; `marketing-visuals-pipeline.md`), shield icon plus version number (security)
- The first line hooks within the ~125-character preview
- Emoji-forward and energetic, 150-300 words
- 15-20 hashtags at the end; "Link in bio" for the download

```
[Hook line with emoji] 🎉

[2-3 sentences: benefit framing, no technical detail]

What's new:
✨ [Feature 1]
✨ [Feature 2]
✨ [Feature 3]

[Closing line: community or mission]

🔗 Link in bio to download & release notes

#ChurchCRM #ChurchManagement #ChurchTech #OpenSource #ChurchSoftware
#ChurchAdmin #MinistryTech #ChurchLife #FaithTech #NonProfit
#ChurchCommunity #ChurchLeadership #WorshipTech #MinistryLeader
#OpenSourceSoftware
```

### LinkedIn — professional, value-focused

- Audience: church IT staff, pastors, nonprofit tech buyers, open-source contributors
- Professional and warm; ChurchCRM is a community project
- Lead with organizational impact; structure hook, context, feature bullets, call to action
- Generous line breaks, 200-500 words, 3-5 hashtags
- Tag the company page `@ChurchCRM` when posting as an individual

```
[Hook: why this release matters to a church organization]

[1-2 sentences of context about the project/version]

Key improvements in vX.Y.Z:

→ [Feature 1, organizational benefit]
→ [Feature 2]
→ [Feature 3]

[1 paragraph on broader significance: open source, community, mission]

[Upgrade note if applicable]

Full release notes: https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z

#ChurchCRM #NonProfitTech #OpenSource #ChurchManagement
```

## Step 4 — Security Patch Posts

Keep all four short and urgent.

- X: the security template, 3 lines max
- Facebook:
  ```
  🛡️ Security update: ChurchCRM X.Y.Z is available.

  This release patches [type of vulnerability, e.g. "a stored XSS vulnerability in group role names"]. All users should update.

  Download: https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z
  ```
- Instagram: short caption with a shield emoji and the hashtag block
- LinkedIn: 2-3 paragraphs in a professional advisory tone

## Step 5 — Save to Files

Write each post to `social-media/X.Y.Z/` (create the directory) for copy-paste:

| File | Contents |
|------|----------|
| `twitter.txt` | X post text |
| `facebook.txt` | Facebook post text |
| `instagram.txt` | Image brief on the first line, then the caption |
| `linkedin.txt` | LinkedIn post text |

Instagram file format:

```
📸 Suggested image: [describe the graphic/screenshot to use]

[caption text]
```

Finish with a short list of the files created.

## Step 6 — Quality Checklist

- [ ] Version number correct in every post
- [ ] X post under 280 characters
- [ ] Every fact comes from the changelog
- [ ] No developer jargon; church vocabulary (congregation, offerings)
- [ ] Security patches marked urgent in every post
- [ ] PHP version requirement stated on Facebook and LinkedIn when applicable
- [ ] Instagram names a specific image
- [ ] Release link is `https://github.com/ChurchCRM/CRM/releases/tag/X.Y.Z`
- [ ] All four files exist in `social-media/X.Y.Z/`

## Related

- [Release Notes](./release-notes.md): write the changelog first
- [GitHub Interaction](./github-interaction.md): milestones, advisories, issue hygiene
