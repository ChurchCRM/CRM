---
title: "Documentation Content Architecture"
intent: "Which documentation home to use for end users, 3rd-party API devs, and ChurchCRM core developers"
tags: ["documentation", "workflow"]
prereqs: []
complexity: "beginner"
---
# Skill: Documentation Content Architecture

## Three Audiences, Three Homes

| Audience | Home | Tooling |
|---|---|---|
| End users (church staff, volunteers) | https://docs.churchcrm.io: `docs/user-guide/`, `docs/administration/`, installation | Docusaurus, plain Markdown |
| 3rd-party app developers (API consumers) | https://docs.churchcrm.io: `docs/api/` | OpenAPI specs from the published CRM tag in `crm-release.json` |
| ChurchCRM core developers (contributors) | Skills in `.agents/skills/churchcrm/`, the CRM repo guides, and the GitHub Wiki | Not the public docs site |

### What goes where

**End-user docs (`docs/user-guide/`, `docs/administration/`):**
- How to use features: what buttons do, what screens mean
- Plain language, with no HTTP endpoints, JSON, or code
- Admin docs may assume basic server knowledge, not developer knowledge
- Link to `docs/api/` for endpoint details instead of tabling them

**3rd-party API docs (`docs/api/`):**
- Built in CI from `docs/openapi/generated/*.yaml` at the ChurchCRM tag in `docs.churchcrm.io` `crm-release.json`
- Maintain the OpenAPI spec; endpoint docs come from it, and the docs repo holds no copy of the specs
- Hand-written overview pages cover authentication, API keys, and base URL

**Core developer docs:** architecture, plugin internals, contributor/PR/dev-setup guides, and the release process live in this repo's skills and guides and the GitHub Wiki.

### Publishing a user-doc change

The public site documents a released ChurchCRM version. A merged CRM pull request is not a release.

| Change | Milestone on the docs pull request | Label |
|---|---|---|
| Product behavior for a version that is not out yet | That version, for example `7.8.0` | none |
| A correction for a version that is already published | That version, for example `7.7.1` | none |
| CI, dependencies, or the docs site itself | none | `ci`, `repo-maintenance`, `infrastructure`, or `dependencies` |

Every product pull request carries a milestone. Site and CI work carries the label instead. The release gate fails until that version is a published stable GitHub Release, and the open pull request is the hold, so product docs are not marked as drafts.

`main` requires the docs build, the release gate, and a CodeRabbit approval; merge only on green checks.

When the CRM release is published, `release-publish.yml` and `release-bookkeeping.yml` dispatch `crm-released`. The docs workflow opens the API pin pull request and merges it, plus the product pull requests on that milestone, when their CI is green and CodeRabbit has approved; the rest stay open. Readers get help through Discord, then a GitHub issue on `ChurchCRM/CRM`. The agent guide in the docs repo is `.agents/DOCS_GUIDE.md`.

## MDX: Escape `{variable}` in Headings and Prose

docs.churchcrm.io uses Docusaurus 3 / MDX 3, which reads bare `{...}` in headings or prose as a JSX expression (`ReferenceError: id is not defined` during SSG fails CI). Escape the braces; code spans and fenced code blocks need no escaping.

```md
### GET /api/groups/\{id\}/emails
```

## GitHub Wiki Articles

Use the wiki for contributor-facing reference that outlives a single PR: multi-step workflows with edge cases, architecture decisions and trade-offs, troubleshooting, and integration guides. Put end-user and admin how-tos on docs.churchcrm.io.

Route other content elsewhere:
- API patterns: [API Development](./api-development.md)
- Code standards: [Code Standards](./code-standards.md)
- Install steps and contributing guidelines: `README.md`, `CONTRIBUTING.md`
- API reference: the OpenAPI spec
- Single-method notes: code comments

Article shape:

```markdown
# Topic Title (clear, searchable)

**Audience:** Admin | Developer | User | Integrator
**Last Updated:** YYYY-MM-DD
**Related:** [Other article](link), [Skill file](link)

## Overview
One or two sentences: what this covers and who should read it.

## Prerequisites
## Step-by-Step Process
## Configuration Options
## Examples
## Troubleshooting
## Related
```

Add a table of contents above five sections. Use Mermaid fenced blocks for diagrams.

Style: direct and active ("Click Save"), terminology matched to the audience, paragraphs of at most four sentences, numbered steps for procedures, code in backticks, links to related articles and skills.

Organize navigation by audience (Administrator, Developer, User) from a `Home.md` index with a short description of ChurchCRM, links by audience, and a getting-started section.

Update the article in the same change that alters the feature, review links quarterly, and name the version when documenting behavior that depends on it.

The wiki is a separate git repo:

```bash
git clone https://github.com/ChurchCRM/CRM.wiki.git
```

Resources: [ChurchCRM Wiki](https://github.com/ChurchCRM/CRM/wiki), [GitHub Wiki guide](https://docs.github.com/en/communities/documenting-your-project-with-wikis/adding-or-editing-wiki-pages), [ChurchCRM Issues](https://github.com/ChurchCRM/CRM/issues).
