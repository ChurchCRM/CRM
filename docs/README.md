# ChurchCRM Documentation

This `docs/` directory contains the **user-facing documentation** for ChurchCRM, structured as a manual for church staff and administrators who use the application day-to-day.

## Content Strategy

The documentation is split across three locations:

| Location | Audience | Content |
|----------|----------|---------|
| [`docs/`](.) (this directory) | **End users** | Day-to-day usage guides, feature how-tos, FAQs |
| [churchcrm.io website](https://churchcrm.io) | **Everyone** | Installation, features overview, downloads |
| [GitHub Wiki](https://github.com/ChurchCRM/CRM/wiki) | **Developers & Admins** | Dev setup, architecture, contributing, advanced config |

## Why This Split?

The [GitHub Wiki](https://github.com/ChurchCRM/CRM/wiki) grew to contain both end-user how-tos and detailed technical content. This made it harder for non-technical church staff to find what they needed. This `docs/` folder:

- Provides a **user manual** written for church staff, not developers
- Can be published directly to [churchcrm.io](https://churchcrm.io) as static HTML pages
- Keeps technical developer content out of the way of day-to-day users

## User Manual

The user manual lives in [`docs/user-manual/`](./user-manual/).

| Page | Description |
|------|-------------|
| [User Manual Home](./user-manual/index.md) | Overview and quick navigation |
| [Getting Started](./user-manual/getting-started.md) | First steps after installation |
| [Families](./user-manual/families.md) | Managing family records |
| [Persons](./user-manual/persons.md) | Managing individual member profiles |
| [Groups](./user-manual/groups.md) | Groups, roles, and ministries |
| [Events](./user-manual/events.md) | Creating events and taking attendance |
| [Finances](./user-manual/finances.md) | Pledges, donations, and deposits |
| [Search & Cart](./user-manual/search-and-cart.md) | Finding records and batch operations |
| [FAQs](./user-manual/faqs.md) | Frequently asked questions |

## Publishing to ChurchCRM.io

These Markdown files are ready to be converted to HTML pages for [churchcrm.io](https://churchcrm.io). The [ChurchCRM.io repository](https://github.com/ChurchCRM/ChurchCRM.io) hosts the website and is where the HTML versions of these pages should ultimately live.

To add a new page to the website:
1. Write it here as Markdown in `docs/user-manual/`
2. Open a PR in this repo (ChurchCRM/CRM) for review
3. Once merged, port the content as a new HTML page in [ChurchCRM/ChurchCRM.io](https://github.com/ChurchCRM/ChurchCRM.io)

## Wiki Migration Plan

The following wiki pages have been migrated into this `docs/` directory and should be simplified in the wiki to link here once the content is live on the website:

| Wiki Page | Migrated To | Status |
|-----------|-------------|--------|
| [User-Docs](https://github.com/ChurchCRM/CRM/wiki/User-Docs) | `user-manual/index.md` | ✅ Migrated |
| [Families](https://github.com/ChurchCRM/CRM/wiki/Families) | `user-manual/families.md` | ✅ Migrated |
| [Persons](https://github.com/ChurchCRM/CRM/wiki/Persons) | `user-manual/persons.md` | ✅ Migrated |
| [Groups](https://github.com/ChurchCRM/CRM/wiki/Groups) | `user-manual/groups.md` | ✅ Migrated |
| [Events](https://github.com/ChurchCRM/CRM/wiki/Events) | `user-manual/events.md` | ✅ Migrated |
| [Finances](https://github.com/ChurchCRM/CRM/wiki/Finances) | `user-manual/finances.md` | ✅ Migrated |
| [Search](https://github.com/ChurchCRM/CRM/wiki/Search) | `user-manual/search-and-cart.md` | ✅ Migrated |
| [Cart](https://github.com/ChurchCRM/CRM/wiki/Cart) | `user-manual/search-and-cart.md` | ✅ Migrated |
| [FAQs](https://github.com/ChurchCRM/CRM/wiki/FAQs) | `user-manual/faqs.md` | ✅ Migrated |

### Wiki Pages That Should Remain in the Wiki

The following wiki pages contain developer or admin-level technical content and should **stay in the wiki**:

- [Development](https://github.com/ChurchCRM/CRM/wiki/Development) — dev environment setup
- [Code Conventions & Style Guide](https://github.com/ChurchCRM/CRM/wiki/Code-Conventions---Style-Guide) — coding standards
- [Database Structure](https://github.com/ChurchCRM/CRM/wiki/Database-Structure) — schema reference
- [Docker](https://github.com/ChurchCRM/CRM/wiki/Docker) — container setup
- [Adding a v2 MVC Page](https://github.com/ChurchCRM/CRM/wiki/Adding-a-v2-MVC-Page) — developer guide
- [Internal APIs](https://github.com/ChurchCRM/CRM/wiki/Internal-APIs) — API reference
- [Contributing](https://github.com/ChurchCRM/CRM/wiki/Contributing) — contributor guide
- [Testing Guide](https://github.com/ChurchCRM/CRM/wiki/Testing) — test infrastructure
- [The Release Process](https://github.com/ChurchCRM/CRM/wiki/The-Release-Process) — release workflow
- [AI-Agent-Workflow](https://github.com/ChurchCRM/CRM/wiki/AI-Agent-Workflow) — AI agent patterns

### Wiki Pages That Could Move to the Website (Admin Guide — Future Work)

These admin-oriented pages would complement the user manual as a separate "Admin Guide" section on the website:

- [First-Run Configuration](https://github.com/ChurchCRM/CRM/wiki/First-Run-Configuration-Items)
- [Users & Permissions](https://github.com/ChurchCRM/CRM/wiki/Users)
- [Backup & Restore](https://github.com/ChurchCRM/CRM/wiki/Backup-Restore)
- [Troubleshooting](https://github.com/ChurchCRM/CRM/wiki/Troubleshooting)
- [Logging & Diagnostics](https://github.com/ChurchCRM/CRM/wiki/Logging-and-Diagnostics)
