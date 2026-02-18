# ChurchCRM AI Agent Instructions

This file is a pointer to the canonical development guidance for AI agents (Copilot, Claude, and other coding assistants).

## Where to Find Guidance

- **Development Skills:** [.agents/skills/](../.agents/skills/) — modular, task-focused guidance for every workflow
  - API development, database operations, testing, security, git workflow, and specialized ChurchCRM topics
  - Load only the skills relevant to your current task
  
- **AI Agent Workflow:** [Wiki: AI-Agent-Workflow](https://github.com/ChurchCRM/CRM/wiki/AI-Agent-Workflow) — how to use AI agents for features, bug fixes, and PRs
  - Principles, workflows, templates, and best practices for safe AI-driven development

- **Quick Reference by Task:**
  - Adding an API endpoint → `api-development.md`, `service-layer.md`, `slim-4-best-practices.md`
  - Migrating a legacy page → `admin-mvc-migration.md`, `frontend-development.md`
  - Fixing a bug → read the failing test, then `security-best-practices.md` / `php-best-practices.md`
  - Writing tests → `cypress-testing.md`, `testing.md`
  - Before committing → `git-workflow.md` pre-commit checklist

## Key Principles

1. **Follow ChurchCRM patterns:**
   - Use Perpl ORM (never raw SQL)
   - Implement business logic in `src/ChurchCRM/Service/`
   - Use `SlimUtils::renderErrorJSON()` for API errors
   - Use `InputUtils` for XSS protection and `RedirectUtils` for redirects

2. **Test & validate locally:** Ensure all tests pass and no CI failures before submitting

3. **Get human review:** All AI-generated code requires human approval before merge

4. **Security first:** Check authorization, input validation, and data sensitivity for every change

## Quick Start

1. **Identify your task** (e.g., "add API endpoint", "fix test failure", "refactor page")
2. **Read relevant skills** from `.agents/skills/`
3. **Follow repo conventions** (all skills document project-specific patterns)
4. **Test locally** and run `npm run build` + `npm run test`
5. **Request human review** before committing

## More Info

- Full skill list and descriptions: [.agents/skills/README.md](../.agents/skills/README.md)
- Workflow guide for contributors: [Wiki: AI-Agent-Workflow](https://github.com/ChurchCRM/CRM/wiki/AI-Agent-Workflow)
- Repository: [github.com/ChurchCRM/CRM](https://github.com/ChurchCRM/CRM)

---

Last updated: February 17, 2026
