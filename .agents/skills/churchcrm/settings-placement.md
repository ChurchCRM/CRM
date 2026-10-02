---
title: Where a setting lives
intent: Decide where an admin setting goes, and the recipe for an area admin hub. Reference implementation is People (/admin/people).
tags: [settings, admin, ux]
---

# Where a setting lives

`/SystemSettings.php` is being retired. Do not add to `buildCategories()`. Pick the home by what the setting does:

| Home | Belongs | Test |
|------|---------|------|
| Feature Toggles (`/admin/system/feature-toggles`) | Site-wide on/off for a module or a public surface (Finance, Events, Lost Password) | Does it switch a capability on or off? |
| Area admin hub (`/admin/<area>`) | Everything else that changes how one area behaves: display, defaults, notifications, form fields, self-registration | Does it belong to People, Finance, Groups…? |
| A list editor column | A flag on a list row (Inactive, In Directory on classifications) | Is the value a set of IDs from one list? |
| Admin dashboard | Truly system-wide (scheduled tasks, PDF type) | Not owned by an area |

A setting has one home. Never in two.

## Area hub recipe

Reference: `src/admin/routes/people.php`, `src/admin/views/people.php`.

1. Route in `src/admin/routes/<area>.php`, required from `src/admin/index.php`. That app already applies `AdminRoleAuthMiddleware`; add none per route.
2. Sections are arrays of `ConfigItem` keys. Build them with `SystemConfig::getSettingsConfig()` so label, tooltip and choices come from the `ConfigItem`.
3. One `new window.CRM.SettingsPanel().init({ ..., autoSave: true, showAllSettingsLink: false })` per section. Auto-save posts one field to `POST /admin/api/system/config/{key}`; no Save button.
4. Panel types: `boolean`, `text`, `number`, `choice`, `textarea`, `persons` (stores comma-separated person IDs, picked by name; pass `selected: [{id, text}]` from the route).
5. Top of the hub: cards linking to the area's list and editor pages.
6. Every area admin page gets a header button back to the hub (`PageHeader::peopleSettingsButton()` is the pattern; add a sibling per area) and a breadcrumb through it.
7. The area dashboard keeps only a link to the hub. No settings panel there.
8. Group settings by what they edit (Person form, Family form, Notifications), not by when they were added.

## Moving a key

- Keep the `ConfigItem` in `buildConfigs()`. Removing it makes `scrapeDBConfigs()` delete the saved value.
- Remove the key from `buildCategories()`. Check the diff: a regex that deletes one category line can take its neighbour with it.
- Read the legacy help text before choosing the section (a "Family Editor" field belongs under Families).
- Hub label: short, Title Case, new msgid. Keep the old sentence as the tooltip so its translation survives. A tooltip that no longer describes the control (CSV of IDs → picker) is reworded; that is a new msgid.
- Panel chrome is addressed by class (`.settings-panel-fields`, `.settings-panel-save`), never id: several panels share a page.
- Before replacing a setting with a flag column, grep every consumer and name the column for what the code does with it.
- Update specs that reference the old location; add a spec that the old page no longer lists the key.

## Done when

- `npm run lint`, `npm run build:webpack`, `npm run build:php:validate` pass.
- Specs: hub renders and saves, admin-only, old location empty, any new endpoint has happy and error cases.
- Tablet and mobile pass stated in the PR body.
- PR body links the issue, lists new msgids with the reason, and notes follow-ups.
- Playwright capture issue and docs issue opened after push (`CLAUDE.md` → Session hygiene).
