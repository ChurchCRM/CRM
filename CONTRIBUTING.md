# How to Contribute to ChurchCRM

ChurchCRM is a community project. Every church that uses it does so because someone — a volunteer, a developer, a designer, a pastor's assistant — gave a little of their time to make it better.

**You don't need to write code to make a meaningful contribution.** Churches need to find ChurchCRM, understand it, and feel confident choosing it. That takes writers, designers, testers, social media managers, translators, and developers working together.

---

## Every Skill Matters

| Your skill | How you help |
|------------|-------------|
| **Developer** | Fix bugs, add features, build community plugins |
| **Plugin builder** | Create integrations ChurchCRM doesn't include yet |
| **QA tester** | Find bugs, test new features, validate fixes |
| **Designer** | UI/UX improvements, logo work, social graphics |
| **Writer / content creator** | Blog posts, tutorials, case studies |
| **Documentation writer** | User guides, admin docs, developer wiki |
| **Translator** | Translate ChurchCRM into your language |
| **Social media manager** | Grow ChurchCRM's presence and reach |
| **Marketer** | Help churches discover ChurchCRM |
| **Photographer / image creator** | Screenshots, promotional images, social assets |
| **Community helper** | Answer questions on Discord and GitHub |

---

## No Code Required

### Write Content

ChurchCRM's story needs to be told. Blog posts, tutorials, how-to guides, and church testimonials help other congregations understand what ChurchCRM can do and feel confident choosing it.

**What's needed:**
- Blog posts about ChurchCRM features and use cases
- Church testimonials ("how we use ChurchCRM at our church")
- Tutorial articles for common workflows
- Comparison guides for churches evaluating software

**Where to contribute:** Open an issue on [GitHub](https://github.com/ChurchCRM/CRM/issues) with the `documentation` label, or share your draft on [Discord](https://discord.gg/tuWyFzj3Nj).

---

### Improve Documentation

The [official documentation site](https://docs.churchcrm.io) covers every feature but always needs clearer explanations, better examples, and updated screenshots. The docs are Markdown files in the [docs.churchcrm.io repository](https://github.com/ChurchCRM/docs.churchcrm.io).

**What's needed:**
- Clearer how-to steps for existing guides
- New guides for features that lack documentation
- Correcting outdated information after releases
- Adding screenshots showing the current Tabler UI (7.1+)

**No GitHub experience required** — you can [edit pages directly in the browser](https://docs.github.com/en/repositories/working-with-files/managing-files/editing-files) on GitHub.

---

### Translate

ChurchCRM is used in 46 languages. Every new translation opens ChurchCRM to thousands of churches that couldn't use it before.

**Translate via POEditor (no Git required):**
1. Join the [ChurchCRM POEditor project](https://poeditor.com/join/project/RABdnDSqAt)
2. Select your language (or request a new one)
3. Translate strings directly in the browser
4. Translations are automatically pulled into the next release

**See:** [Localization for Translators](https://github.com/ChurchCRM/CRM/wiki/Localization-For-Translators)

---

### Test the Application

You don't need to be a developer to test ChurchCRM. Browser testing, accessibility checks, and feature validation catch real bugs before they reach churches.

**What's needed:**
- Test new releases on different browsers and devices
- Try common workflows (adding families, recording attendance, generating reports)
- Report anything confusing or broken on [GitHub Issues](https://github.com/ChurchCRM/CRM/issues)
- Test the [live demo](https://churchcrm.io/demo.html) and report issues

**Use the [Bug Reporting Guide](https://github.com/ChurchCRM/CRM/wiki/Bug-Reporting-and-Diagnostics)** to include useful information when filing issues.

---

### Design & Create Images

ChurchCRM's marketing site, documentation, and social presence all need visual assets — screenshots, promotional graphics, social media images, and UI design feedback.

**What's needed:**
- Updated screenshots showing the current ChurchCRM interface (Tabler UI)
- Social media graphics for feature announcements
- Promotional images for blog posts
- UI/UX feedback on the application — what feels confusing, what could be clearer
- Logo usage and brand asset improvements

**Share your work** on [Discord](https://discord.gg/tuWyFzj3Nj) in the `#design` channel or open a GitHub issue with the `design` label.

---

### Social Media & Marketing

ChurchCRM has thousands of users but most churches have never heard of it. Spreading the word is one of the highest-leverage ways to help — every church that switches from a paid subscription to ChurchCRM saves money they can redirect to ministry.

**What's needed:**
- Share ChurchCRM on social media (Facebook church groups, LinkedIn, X/Twitter, Instagram)
- Write reviews on software directories (Capterra, G2, SourceForge, AlternativeTo)
- Tell other churches in your denomination or network about ChurchCRM
- Help with ChurchCRM's own social media presence by suggesting content or post ideas

**Connect:** Share what you're doing on [Discord](https://discord.gg/tuWyFzj3Nj) so the team knows.

---

### Answer Questions in the Community

People get stuck. A quick, helpful answer to a question on Discord or GitHub Discussions saves a church administrator hours of frustration and keeps them using ChurchCRM.

**Where to help:**
- [Discord server](https://discord.gg/tuWyFzj3Nj) — real-time questions from users
- [GitHub Discussions](https://github.com/ChurchCRM/CRM/discussions) — in-depth questions

No expertise required for most questions — if you've used ChurchCRM for a while, your experience is valuable.

---

## Developer Contributions

### Contribute to the Core Application

Fix bugs, add features, improve performance, and expand test coverage in the main ChurchCRM codebase.

**Quick start:**
1. Make sure you have a [GitHub account](https://github.com/signup/free)
2. Join [Discord](https://discord.gg/tuWyFzj3Nj) and introduce yourself
3. Find a [`good first issue`](https://github.com/ChurchCRM/CRM/labels/good%20first%20issue) to start with
4. Set up your [development environment](#setting-up-your-development-environment)
5. Open a pull request

**All PRs must be linked to an open issue.** If the issue doesn't exist yet, open it first.

### Build a Community Plugin

Add a feature ChurchCRM doesn't include yet — a third-party API integration, a custom workflow, a specialized report — without modifying core code. Plugins survive upgrades and can be shared in the community registry.

**Best for:** Service integrations (MailChimp, SMS, OpenLP, etc.), church-specific workflows, optional features not every installation needs.

**Start here:** [Creating Community Plugins](https://github.com/ChurchCRM/CRM/wiki/Creating-Community-Plugins)

> **Upgrade safety:** Direct modifications to ChurchCRM source files are overwritten when you upgrade. All custom features must live in plugins to survive releases.

---

## Setting Up Your Development Environment

### Quick Start (Recommended)

**GitHub Codespaces (Easiest):**
1. Go to the [ChurchCRM GitHub repository](https://github.com/ChurchCRM/CRM)
2. Click "Code" → "Codespaces" → "Create codespace on master"
3. Wait 2–3 minutes for automatic setup
4. Run `npm run docker:dev:start` then open `http://localhost` with `admin`/`changeme`

**VS Code Dev Containers:**
1. Install the "Dev Containers" extension in VS Code
2. Clone the repo and open it in VS Code
3. Click "Reopen in Container" when prompted

**DDEV (Local Docker):**
```bash
git clone https://github.com/ChurchCRM/CRM.git churchcrm
cd churchcrm
ddev start
ddev setup-churchcrm
ddev launch
```

Login: **admin** / **changeme**

### Coding Standards

- **Database:** Propel ORM only — no raw SQL
- **UI:** Bootstrap 5 / Tabler CSS classes (not Bootstrap 4)
- **PHP:** 8.4+, PSR-12 style, explicit nullable types
- **i18n:** Wrap all UI text with `gettext()` (PHP) or i18n helpers (JS)
- **Business logic:** Service classes in `src/ChurchCRM/Service/`

Full standards: `.github/copilot-instructions.md` in the repo

### Testing

All pull requests require Cypress tests:

```bash
npm run test          # Run all tests (headless)
npm run test:ui       # Interactive browser testing
```

---

## First Steps for Any Contributor

1. **Join Discord** — [discord.gg/tuWyFzj3Nj](https://discord.gg/tuWyFzj3Nj) — introduce yourself and tell us what you'd like to help with
2. **Browse open issues** — [github.com/ChurchCRM/CRM/issues](https://github.com/ChurchCRM/CRM/issues) — filter by label to find work that matches your skill
3. **Try the demo** — [churchcrm.io/demo.html](https://churchcrm.io/demo.html) — use ChurchCRM so you understand what you're helping with

---

## Code of Conduct

All contributors are expected to follow our [Code of Conduct](CODE_OF_CONDUCT.md). We are a welcoming, respectful community.

Thank you — every contribution, no matter how small, helps a church somewhere run a little better.
