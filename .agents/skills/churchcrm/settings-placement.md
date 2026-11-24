---
title: Where a setting lives
intent: Decide where an admin setting goes, and the recipe for an area admin hub. Reference implementation is People (/admin/people).
tags: [settings, admin, ux]
---

# Where a setting lives

Define each item in `buildConfigs()` only. Pick the home by what the setting does:

| Home | Belongs | Test |
|------|---------|------|
| Feature Toggles (`/admin/system/feature-toggles`) | Site-wide on/off for a module or a public surface (Finance, Events, Lost Password) | Does it switch a capability on or off? |
| Area admin hub (`/admin/<area>`) | Everything else that changes how one area behaves: display, defaults, notifications, form fields, self-registration | Does it belong to People, Finance, Groups…? |
| A list editor column | A flag on a list row (Inactive, In Directory on classifications) | Is the value a set of IDs from one list? |
| Admin Settings (`/admin/settings`) | Truly system-wide (Quick Search, scheduled tasks, PDF temp folder) | Not owned by an area |

A setting has one home. Never in two. Dashboards show status and link to settings; they hold no settings panels or switches. `/admin/settings` links to every settings home: add a card there when you add a hub.

## Area hub recipe

Reference: `src/admin/routes/people.php`, `src/admin/views/people.php`.

1. Route in `src/admin/routes/<area>.php`, required from `src/admin/index.php`. That app already applies `AdminRoleAuthMiddleware`; add none per route.
2. Sections are arrays of `ConfigItem` keys. Build them with `SystemConfig::getSettingsConfig()` so label, tooltip and choices come from the `ConfigItem`.
3. Render each section as a `<div id data-title data-icon data-settings="<?= InputUtils::jsonEncodeForAttribute($settings) ?>">` and load `admin-settings.min.js`, which turns every one into an auto-saving `SettingsPanel` (`data-reload-on-save` on a wrapper reloads after a save, for toggles that change the menus). Auto-save posts one field to `POST /admin/api/system/config/{key}`; no Save button. Keep `autoSave` off only when the fields are one batch: several values apply together and the page reloads or redraws from the whole set (ministry timing, statement wording), or the form is a credential set (Email SMTP). Map zoom is one field, so it auto-saves.
4. Panel types: `boolean` (a switch), `text`, `number`, `choice`, `textarea`, `persons` (stores comma-separated person IDs, picked by name; pass `selected: [{id, text}]` from the route).
5. Top of the hub: cards linking to the area's list and editor pages.
6. Every area admin page gets a header button back to the hub (`PageHeader::peopleSettingsButton()` is the pattern; add a sibling per area) and a breadcrumb through it.
7. The area dashboard keeps only a link to the hub. No settings panel there.
8. Group settings by what they edit (Person form, Family form, Notifications), not by when they were added.
9. Order sections by how often an administrator changes them: day-to-day behavior first, annual tasks next, set-once wording and disclaimers last. Do not add a section for one field; fold it into the nearest one.
10. A setting is for administrators only. A non-admin role never edits system settings: gate the page and its API with `AdminRoleAuthMiddleware`, and show a link to the settings only to admins. There is no module-level settings role.
11. A setting that only matters while a switch is on sets `dependsOn: '<switch key>'`; the panel hides it while the switch is off.
12. Settings never sit in a collapsed panel behind a header button. A page that is not a hub links to its section on the hub (`/admin/settings#securitySettings`). A tool page keeps a setting only when it changes what that page shows (log level on Logs, pre-release on Upgrade), as an always-visible panel.
13. Internal values (timer tuning, setup-window length, per-source search limits, run markers) get no control; their defaults stand.
14. A `ConfigItem` tooltip is help text the panel shows. Reword it when the control changes; delete it when it only repeats the label (the panel also drops a hint equal to its label). `getSettingsConfig()` translates it per request. A `ConfigItem` that no code reads is deleted.

## Reports

No reporting app. A report lives in the module that owns its data and permissions (`/people/reports`, `/finance/reports`). Its settings live in that area's admin hub. The Reports menu only collects what each module registers.

## Moving a key

- Keep the `ConfigItem` in `buildConfigs()`. Removing it makes `scrapeDBConfigs()` delete the saved value.
- Read the setting's current help text before choosing the section (a "Family Editor" field belongs under Families).
- Hub label: short, Title Case, new msgid. Keep the existing sentence as the tooltip so its translation survives. Reword a tooltip that does not describe the new control (CSV of IDs → picker); that is a new msgid.
- Panel chrome is addressed by class (`.settings-panel-fields`, `.settings-panel-save`) because several panels share a page.
- Before replacing a setting with a flag column, grep every consumer and name the column for what the code does with it.
- Update specs that reference the previous location.

## Done when

- `npm run lint`, `npm run build:webpack`, `npm run build:php:validate` pass.
- Specs: hub renders and saves, admin-only, any new endpoint has happy and error cases.
- Tablet and mobile pass stated in the PR body.
- PR body links the issue, lists new msgids with the reason, and notes follow-ups.
- Docs issue opened after push (`CLAUDE.md` → Session hygiene). A settings page earns no Playwright capture.
