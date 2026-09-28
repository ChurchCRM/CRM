# Welcome to ChurchCRM Contributors

## Using Claude Code with ChurchCRM

Claude Code helps you contribute more effectively. This guide walks you through the project structure, key tools, and workflow so you and your agents can work with confidence.

## Getting Started

### Essential Project Files
- [ ] **[CLAUDE.md](CLAUDE.md)** — Start here. Code standards, git workflow, and how the project is structured
- [ ] **[CONTRIBUTING.md](CONTRIBUTING.md)** — Ways to contribute (code, design, docs, testing, translation)
- [ ] **[DEVELOPING.md](DEVELOPING.md)** — Dev environment setup (PHP 8.4+, Node 24, Docker, MySQL)

### Claude-Specific Guidance
- **[Skill cards](https://github.com/ChurchCRM/CRM/tree/master/.agents/skills/churchcrm)** — Load the card that matches your task (UI, API, i18n, tests, security, plugins). They guide you through the review gates and project expectations.
- **/pr-review** — Use this when working on or reviewing PRs
- **/clear** — Start fresh when moving to a new task

### Key Commands
```bash
npm install              # Install dependencies
npm run lint            # Required before every commit (Biome)
npm run build           # Build the project
npm run docker:test:start  # Run the test environment
cd src && composer install  # PHP dependencies
```

## Next Steps

1. Read [CLAUDE.md](CLAUDE.md) — it covers code standards, the git workflow, and how agents navigate the project
2. Pick a [skill card](https://github.com/ChurchCRM/CRM/tree/master/.agents/skills/churchcrm) that matches the type of work you're doing
3. Find an open issue or ask on [Discord](https://discord.gg/tuWyFzj3Nj) where to start
4. Your agent can load skill cards to understand the review gates and project standards

<!-- INSTRUCTION FOR CLAUDE: A new contributor just pasted this guide into Claude Code to learn how to contribute to ChurchCRM effectively. You're their onboarding buddy — warm, conversational, direct.

Open with a welcome to ChurchCRM. Check what they have (CLAUDE.md, DEVELOPING.md, a local checkout) using checkboxes — [x] done, [ ] not yet. One sentence per item, lead with wins.

Then walk them through: (1) Read CLAUDE.md first — it's the source of truth for this project. (2) Pick a skill card that matches their task type. (3) Here's where to find issues and ask for guidance (Discord link, GitHub issues). (4) Your first step is to [whatever the maintainer set — see "Get Started" section].

Offer to help with setup, clarify any skill card, or start them on their first task. Keep it practical and welcoming. Don't invent details. -->
