---
title: "Member Portal"
intent: "How the member-facing /portal module, its Twig themes, calendar, volunteer and account pages work"
tags: ["portal", "twig", "themes", "routing"]
prereqs: ["[[routing-architecture]]"]
complexity: "advanced"
---

# Member Portal

The Member Portal is a member-facing area of the installation at `/portal`, with its own layout and navigation. Product principle: a member never sees the admin shell. Everything a member may do is inside the portal, and a page that would bounce a member to an admin page is unfinished.

Theme authors: https://docs.churchcrm.io/administration/member-portal-theme-guide. Template variables and hooks: https://docs.churchcrm.io/administration/member-portal-template-reference.

## Design rules

| # | Rule |
|---|------|
| P1 | `src/portal/` is an `MvcAppFactory` module at `/portal` (see `routing-architecture.md`). |
| P2 | Pages render with Twig through `ChurchCRM\Portal\PortalTwig`, with a theme override loader. Twig templates cannot execute PHP, so church-supplied overrides stay safe. |
| P3 | A theme is a folder; its folder name is its id and display name. `theme.json` optionally supplies `name`, `author`, `description`. |
| P4 | Church themes live in `Include/themes/<name>/`; the system theme is `Include/themes/default/` (core, overwritten on update). `Include/` survives updates, and the orphan-file scan (`AppIntegrityService::isExcludedFromOrphanDetection()`) and `generate-signatures-node.js` exempt `Include/themes/`. `.gitignore` ignores `src/Include/themes/*` except `default`. A church copies `default/` and renames the copy; it never edits `default/`. |
| P5 | The application serves theme assets at `/portal/theme/<name>/<path>`; `Include/` stays deny-all at the web-server level. |
| P6 | A theme is CSS, images, optional `theme.js`, optional template overrides. It holds no PHP. |
| P7 | Template edits apply on the next request (Twig `auto_reload`). |
| P8 | A broken theme fails loudly (section "Failure behaviour"). |
| P9 | Configuration lives on Admin -> Member Portal. Values are `ConfigItem`s in `config_cfg` without a System Settings category. |
| P10 | Landing rule: members have no account; they sign in at `/portal/login` with their email (member sign-in design, `docs/member-sign-in-design.md`) and only ever see the portal. Staff sign in at `/admin/` and land in `/v2/dashboard`, with a "Member Portal" entry in the user menu; an Edit-Self-only login is confined to `/portal`. In the portal, the account menu's **Admin Console** entry returns a staff session to the admin side. |
| P11 | Every portal API derives the acting person from the portal principal (`PortalContext`). No route accepts a `personId` naming the actor. |
| P12 | Family scope is the member's own family, through `User::canViewFamily()` / `canEditPerson()`. |
| P13 | The administrator chooses which calendars the portal shows (section "Calendar"). |
| P14 | Giving is not part of the portal; the nav reserves no page for it until a payment-gateway design exists. |
| P15 | The volunteer member pages are portal Twig templates around the `webpack/portal/` volunteer bundles and `/api/ministries/me/*`. |
| P16 | Member-facing volunteer functionality exists only in the portal; the admin shell keeps "Ministries". |
| P17 | Volunteer scopes count for members: a team leader manages their own team in the portal (`volunteer.team.manage`). Coordinators and managers are staff accounts. |
| P18 | Every portal page, API route and nav entry names a portal capability (`ChurchCRM\Portal\Permissions`, member sign-in design §8). Core and plugins register capabilities and rules the same way (`plugin-development.md` -> "Member Portal"). |

Out of scope: contribution history and online giving, per-event visibility flags, personal-device check-in, theme editor or upload UI, theming the admin shell. The member directory (below) is a draft that needs a privacy model first: whether a member can leave themselves out of the portal and printed directories is open in #10869 (design: `docs/design/member-directory.md`, #10870), and #10524's protected-household policy must apply. The team leader's name-only Add Volunteer picker (#10531) is not a directory.

## Layout on disk

```
src/portal/                  index.php, routes/*.php, .htaccess
src/ChurchCRM/Portal/        ThemeManager, ThemeValidator, PortalTwig, PortalExtension, PortalNav,
                             PortalAccessMiddleware, ThemeAssetStreamer, PortalSelfService, PortalTeams,
                             PortalCalendar*, PortalAccountPages, PortalStatsService, PortalSettings
src/ChurchCRM/Portal/Auth/   the portal principal and session, member sign-in, devices, passkeys, Login as Person
src/ChurchCRM/Portal/Permissions/  portal capabilities, rules, grants, RequirePortalCapability
src/Include/themes/default/  theme.css, images/, templates/**/*.twig (layout, partials, pages, errors)
src/admin/routes/member-portal.php, src/admin/routes/api/member-portal.php, src/admin/views/member-portal.php
src/api/routes/portal/       /api/portal/* (portal-me, portal-calendar, portal-emails)
webpack/portal/              portal.ts and per-page bundles
src/skin/scss/_portal.scss   part of portal.min.css
```

## Request flow

1. Members sign in at `/portal/login`, staff at `/admin/` (`/session/begin` redirects there); landing follows P10. `/external/limited-access` redirects to `/portal`. The portal is always on.
2. A request with a portal principal and no staff session passes `AuthMiddleware` only on `/portal`, `/api/portal` and the volunteer API (`/api/ministries/`). `AuthMiddleware` redirects an Edit-Self-only browser request outside `isLimitedAccessAllowedPath()` to `/portal`. The allowed paths include `/portal`, `/api/portal`, the auth-flow pages, `/api/ministries/me/` and `/api/ministries/` (team leaders). `/volunteer` and other admin areas stay closed. API-key calls from these accounts get 403. `Include/PageInit.php` applies the same rule to PHP pages; `AuthenticationManager::getDefaultLandingPath()` picks the post-login destination.
3. `src/portal/index.php` mounts `PortalSessionMiddleware` (the portal principal: a member session, or a staff session opening the portal as its own person; it ends the session on a block, a session-version change or idle time), then `PortalAccessMiddleware` (302 to `/portal/login` when signed out, API keys refused) and `CSRFMiddleware`, on the page group. `/api/portal/*` groups use `PortalSessionMiddleware` + `PortalApiMiddleware`. Portal code reads who it serves from `PortalContext`, never `AuthenticationManager::getCurrentUser()`.
4. A route handler builds a view-model and calls `PortalTwig::render($response, 'calendar/index.html.twig', $model, PortalNav::CALENDAR)`. The loader order is active theme, then default theme; a theme file with the same relative path wins.
5. `layout.html.twig` emits core CSS, `portal.min.css`, the theme's `theme.css`, the nav and flash partials, the page block, the footer, then scripts (core bundle, i18next, locale loader, `portal.min.js`, the page bundle, `theme.js`), all with the CSP nonce.
6. `GET /portal/theme/<name>/<path>` streams a theme file when `<name>` is a real theme folder, `<path>` is clean and relative (no empty, `.` or `..` segment, leading slash, drive letter, backslash or NUL), the extension is in `ThemeAssetStreamer::ALLOWED_TYPES`, and the `realpath()` of the file is a regular file inside the theme folder's `realpath()`. A symlink leaving the theme is refused like any miss. The response carries `Content-Type`, an `ETag` from mtime and size, `Cache-Control: public, max-age=31536000` (URLs carry `?v=<filemtime>`), and 304 on `If-None-Match`. Every miss is a plain-text 404. The route is public (`AuthMiddleware::isPublicPath()`), like `/portal/login` and `/portal/email/confirm/{token}`; a public page calls `PortalTwig::preparePage()` itself.

## Who lands where

| Account | Lands in | Portal | Admin shell |
|---|---|---|---|
| Member (no account, email sign-in at `/portal/login`) | `/portal` | yes | no |
| Staff login at `/admin/` (admin flag or any module permission) | `/v2/dashboard` | via user menu "Member Portal" | yes |
| Edit Self only | `/portal` | yes | no |

## Theming

### Theme contents

Every file is optional.

| Path | Purpose |
|---|---|
| `theme.css` | Loaded after `portal.min.css`; overrides design tokens and component styles. |
| `theme.js` | Loaded last, with the CSP nonce, after `window.CRM.onLocalesReady`. |
| `images/`, `fonts/` | Served by the asset route; CSS references them by relative path, templates by `theme_asset('images/x.png')`. |
| `templates/**` | A file with the same relative path as one in `default/templates/` replaces it. A theme may add partials. |
| `theme.json` | `{name, author, description}` for the admin page. |

### Discovery, validation, activation

`ThemeManager`:

- `listThemes()` scans `Include/themes/*/` (`default` first, the rest by display name). A folder is a theme by existing.
- `validate(name)` (`ThemeValidator`) compiles every `templates/**/*.twig` through the portal environment, flags overrides with no counterpart in `default/` ("not used by this version"), checks referenced `theme.css`/`theme.js`, and returns `{file, line, message, level}` findings.
- `activate(name)` runs `validate`; an error-level finding refuses activation and the admin page lists it. Warnings are shown and allowed.
- `getActiveTheme()` reads `sMemberPortalTheme`.

The Twig environment caches to `src/Include/cache/twig-portal/` with `auto_reload => true`. Developer mode (`bPortalDeveloperMode`) sets `cache => false` and prints template names in an HTML comment at the top of each page.

### Failure behaviour

| Situation | Administrator sees | Member sees | Logged |
|---|---|---|---|
| Activating a theme with a template error | Admin page refuses and lists file, line, message | nothing changes | app log, warning |
| Runtime error in the active theme, or active theme folder missing | `errors/theme-error.html.twig` (theme, file, line, message, link to the admin page) | `errors/unavailable.html.twig` | app log (Admin -> System -> Logs) and `error_log()`, one line with theme, file, line, message |

Both error pages render from the default theme, so they always render. The portal never falls back silently to the default theme.

### Twig environment

`PortalTwig` builds the environment: loader `FilesystemLoader([activeThemeTemplates, defaultTemplates])` with the `@default` namespace for the default tree (an override can `{% extends "@default/home.html.twig" %}` and change one block), `autoescape: 'html'`, `strict_variables: false`, `GettextExtension` (with `ngettext`) and `PortalExtension`. `PortalExtension` exposes exactly:

| Name | Returns |
|---|---|
| `url(path)` | `SystemURLs::getRootPath() . path` |
| `asset(path)` | `SystemURLs::assetVersioned(path)` |
| `theme_asset(path)` | `/portal/theme/<active>/<path>?v=<filemtime>`; the default theme's URL when the active theme lacks the file |
| `csrf_field()` | `CSRFUtils::getTokenInputField()` |
| `nonce()` | `SystemURLs::getCSPNonce()` |
| `church` | `{name, address, city, state, zip, phone, email, website, logoUrl, logoOnDarkUrl, socialLinks}` from `ChurchMetaData`; `socialLinks` is `[{id, label, url, icon}]` |
| `member` | `{id, firstName, lastName, fullName, email, avatarUrl, familyId, isTeamLeader, isStaff, signedInWithPassword, adminConsoleUrl}` |
| `portal_can(capability, scope)` | the portal permissions check, for showing a section or button; routes use `RequirePortalCapability` |
| `nav` | `PortalNav` model: `[{id, label, url, icon, capability, active, badge}]`, each entry shown when the member holds its capability |
| `flash` | `[{type, message}]` from the session |
| `portal` | `{rootPath, themeName, locale, isRTL, developerMode}` |
| `email_wrap` (filter) | escaped address with `<wbr>` after `@` and before each `.` |

Templates get no PHP includes, no filesystem or network functions, and no `$_SESSION`.

### Design tokens

`portal.min.css` styles components with CSS custom properties, so a colour-only theme is a short `theme.css`:

```css
:root {
  --portal-primary: #6b1d2c;
  --portal-primary-contrast: #ffffff;
  --portal-accent: #c9a227;
  --portal-surface: #ffffff;
  --portal-surface-alt: #f6f1ea;
  --portal-text: #1f1f1f;
  --portal-muted: #6b6b6b;
  --portal-font-body: "Source Sans 3", system-ui, sans-serif;
  --portal-font-heading: "Fraunces", Georgia, serif;
  --portal-radius: 12px;
  --portal-header-bg: var(--portal-primary);
  --portal-header-text: var(--portal-primary-contrast);
  --portal-hero-image: url("images/hero.jpg");
}
```

Notice colours are `--portal-success`, `--portal-warning`, `--portal-danger`, `--portal-info` (each with a `-contrast` twin) plus `--portal-toast-width`.

Every portal notice is a toast in the fixed `#portal-toasts` container (rendered by `partials/flash.html.twig`, filled by server `flash` messages and `window.CRM.portalToast(message, type)`), so the page never shifts under the member. `.portal-container` appears in the header, the nav and `<main>`; a bundle that places a notice by that selector lands in the header.

Dark mode: the default `theme.css` has a `[data-bs-theme="dark"]` block that a theme may override. Fonts come from `self`, `fonts.googleapis.com` or `fonts.gstatic.com` (the CSP). Portal CSS uses logical properties, so RTL needs no extra stylesheet.

### Template contract

`layout.html.twig` defines blocks `head_extra`, `header`, `nav`, `hero`, `content`, `sidebar`, `footer`, `scripts_extra`. Page templates fill `content` and optionally `hero` and `sidebar`. Partials are named, so a theme can override `partials/nav.html.twig` alone. Variables, ids and `data-` hooks per template are in https://docs.churchcrm.io/administration/member-portal-template-reference.

A bundle treats a missing hook as "this theme does without the feature". Adding a variable or block is compatible; renaming or removing one is a release-note item and keeps the old name as an alias for one release. The validator's "not used by this version" warning tells a theme author that an overridden template moved.

### Theme security

- Only allow-listed extensions leave through the asset route, only from real theme folders, never via `..` or a symlink that leaves the theme
- A theme is trusted like any other file the administrator places on the server; never use `|raw` on member data
- `theme.js` runs under the CSP nonce; inline scripts in theme templates carry `nonce="{{ nonce() }}"`
- The admin page states "Themes are provided by your church, not by ChurchCRM"
- Login as Person (`ChurchCRM\Portal\Auth\LoginAsPerson`, member sign-in design §6.5) sets the administrator's staff session aside while an ASSISTED principal runs, so portal code never sees a staff user then. `LoginAsPersonMiddleware` refuses (unless `bLoginAsPersonCanWrite`) and records every non-GET request; a new write path needs nothing more. A theme overriding `layout.html.twig` keeps `partials/login-as-person-banner.html.twig`: Stop is the only way back

## Admin -> Member Portal (`/admin/member-portal`)

Administrators only, under People Settings (breadcrumb and header button), one page with tabs. Statistics is first and open by default:

- **Statistics:** members who signed in during the last 24 hours, 7 days and 30 days, and members active in the last 15 minutes, from `person_signin_psi` (`psi_LastSignIn`, `psi_LastActivity`); everyone who may sign in; those who never have; the ten most recent sign-ins.
- **Sign-in, Members, Audit:** member sign-in settings, the member lists and the sign-in audit (member sign-in design).
- **Permissions:** the portal permissions (member sign-in design), under a "What members can use" panel for `bPortalAllowBirthdayEdit` and `bPortalShowVolunteer`.
- **Themes:** discovered folders (`listThemes()`, "System default" first) with display name, author, overridden-template count and validation badge. Each row's Check and Activate show their findings under that row; activation goes through `POST /admin/api/member-portal/theme` so it can refuse. Developer mode sits below the list.
- **Calendars:** one list of church calendars, ministry calendars and system calendars with a "Show in Member Portal" switch each, saved as it changes. Members see the Calendar page, its nav entry, its API and the home card (`portal.calendar.view`, Twig `portal.showCalendar`) while at least one calendar is switched on; a line above the list says which.
- **Directory:** `bPortalShowDirectory` on top; below it the classifications that are listed and the ones that may see it (both Member by default), the printed directory's "Information to Include" checkboxes, Birthday and Wedding Anniversary as "Show with year", "Show without year" (default) or "Do not show", and the search method: "Browse the full directory" (default) or "Search only". Changes auto-save through `POST /admin/api/member-portal/directory`, which `PortalDirectoryService::save()` validates.

The four booleans use the Settings Panel component and auto-save through `POST /admin/api/system/config/{name}`. The Sign-in tab saves each control as it changes; every tab says "Changes save as you make them" and has no Save button.

Admin APIs: `/admin/api/member-portal/{stats,themes,theme/{name}/validation,theme,calendars,directory}`.

Config items: `sMemberPortalTheme`, `bPortalDeveloperMode`, `bPortalShowVolunteer`, `bPortalAllowBirthdayEdit`, `bPortalShowDirectory`, `aPortalCalendars`, `aPortalDirectory`.

## Screens and APIs

Nav (`PortalNav`), in order, each hidden when its feature is off or the member has nothing there: Home, Calendar, Volunteering, My Teams, My Family, Profile. The header shows the church logo and name and one account menu: "Hello <first name>" over Email History, Sign-in and devices (`/portal/devices`, shown with `portal_can('portal.devices.manage')`; `PortalNav` gets no active id for it), Change Password (staff sessions only), Admin Console (back to the staff side from a staff session; the `/admin/` sign-in from a member session whose person has a staff account) and Sign out (`GET /session/end`).

### Home (`/portal`)

Cards: next portal-visible events, the member's next volunteer assignment and pending-response count (read client-side from `/api/ministries/me/assignments` by `portal.min.js`, silent on failure), a family-confirmation reminder, the "Ministries looking for help" teaser, and a Profile card that lists values from `PortalSelfService::getProfile()` (email, mobile, home phone, birthday while `bPortalAllowBirthdayEdit` is on, family role; empty fields skipped). Themes typically override this page first.

### Profile and My Family

- Read: own person and family.
- Edit own person: name parts, title/suffix, email, work email, phones, birthday (`bPortalAllowBirthdayEdit`), photo. Writes: `POST /api/portal/me`, `POST /api/portal/me/photo` (calls `Person::setImageFromBase64()` for the member's own record).
- Language (`psi_Language`, `ChurchCRM\Portal\MemberLanguage`): the profile form's Language field, "Church default" (`sLanguage`) or a language from `locales.json`. `Bootstrapper::getCurrentLocale()` resolves every request: a member session and Login as Person use the person's language, a STAFF session the staff account's `ui.locale`, a signed-out page `sLanguage`. Emails to the member are built in their language (`MemberLanguage::inLanguageOf()`), the office notice in the church's (`inChurchLanguage()`). No field in a STAFF session.
- Edit family (`portal.family.edit`, an adult of the family): address, city, state, zip, country, home phone, wedding date; email belongs to people, not families. Other members are view-only. "Add a family member" (`portal.family.edit`) creates a pending self-registration entry (`Person::SELF_REGISTER`) for staff review. `POST /api/portal/family`, `POST /api/portal/family/members`.
- Every write goes through `InputSanitizationMiddleware` and a field allow-list, and writes a timeline `Note` "edited via the Member Portal". Dates travel as ISO `YYYY-MM-DD`, sanitized as `text`; the service parses them strictly, so `2026-02-31` is refused.
- Photos: the portal serves its own copies because `isLimitedAccessAllowedPath()` confines self-service sessions to `/portal` and `/api/portal`. `GET /api/portal/me/photo` (cached privately for `Photo::CACHE_DURATION_SECONDS`) and `GET /api/portal/family/members/{personId}/photo` (`private, no-store`) serve through the `Photo` object; every `photoUrl` the portal hands out points at them with `?v=<mtime>`. The family photo route is the one place a portal route takes a person id; it checks the id against the actor's own family, and anything else is 404, never 403.
- Confirm family details (`/portal/family/confirm`, `POST /api/portal/family/confirm`): writes the same `verify` note as `/external/verify`, with `Person::SELF_VERIFY` as the enterer because People -> Verify selects on it. Confirming needs `portal.family.confirm` (an adult of the family). The emailed verify-token link keeps serving people without logins.
- A member with no family (or no person record) gets `family/none.html.twig` with HTTP 200: "You are not currently associated with a family", plus Church Information email as `mailto:` and phone as `tel:` (each omitted when empty; with neither, "Please contact the church office."). The portal 404 is for URLs that do not exist.
- Password and two-factor: `GET/POST /portal/profile/password` and `GET /portal/profile/two-factor`, in the portal layout, so an administrator who changes a password from the portal stays in the portal. They act on the staff account behind a STAFF portal session; a member session gets 404. `PortalAccountPages` is the single place a portal account page becomes a response; `User::userChangePassword()`, the form ids and the `PasswordChange.js` / `two-factor-enrollment` bundles are shared. `/v2/user/current/changepassword` and `/v2/user/current/manage2fa` render through the same class and stay at those URLs: `LocalAuthentication` returns them as `nextStepURL` for forced flows, which match `REQUEST_URI` to break their own redirect loop. The password template's `formAction` is a variable set by each route, never taken from the request.

### Calendar (`/portal/calendar`)

- Visibility is stored as `aPortalCalendars`, a JSON list of `{"type": "calendar"|"system", "id": <int>}`. System calendars (Birthdays, Anniversaries, Holidays, Unpinned events; virtual, from `SystemCalendars`, extensible via `Hooks::SYSTEM_CALENDARS_REGISTER`) are not table rows, and their ids overlap `calendars` ids, so each entry names its kind. `PortalCalendarService::visible()` drops entries that no longer match a calendar. Default `[]` after an upgrade; a new install enables the "Events" church calendar. Birthdays and Anniversaries render first name and last initial only, never ages or years.
- Every volunteer ministry has a calendar (`calendars.ministry_id`, FK to `volunteer_ministry_vmin`, `ON DELETE SET NULL`), created, renamed and deleted with the ministry by the volunteer service. Coordinators pin their ministry's events to it, and to church calendars an administrator opened to the ministry (`volunteer_calendar_vcal`), judged by `VolunteerCalendarService::mayPin()`. Calendar administration stays with Add Events or the coordinators of the calendar's own ministry (`CalendarWriteRoleAuthMiddleware`). The admin calendar page labels church calendars "Church Calendars" and lists ministry calendars under "Ministry Calendars".
- `GET /api/portal/calendar/events?from&to` returns events of portal-visible calendars (church, ministry, system), session-gated, in the configured timezone. `from`/`to` are `YYYY-MM-DD`; a reversed range is 400; the window clamps to `PortalCalendarService::MAX_WINDOW_DAYS` and the response echoes the window answered.
- UI: FullCalendar (month/agenda/list, read-only), one colour per calendar, an event detail panel. Legend entries are `<button aria-pressed data-calendar-key>` toggles that hide and show a calendar's events in place; the choice persists in `localStorage` `churchcrm.portal.calendar.hidden` (every access guarded). A theme without the toggle markup keeps a plain legend.
- Subscribe: a **Subscribe** button opens a dialog with one checkbox per shared calendar and returns one address, `/api/public/portal-calendar/{token}/calendar.ics`. The token (32 random bytes as hex, `psi_CalendarToken`) is the credential because calendar apps cannot sign in; **Reset link** (`POST /api/portal/calendar/subscription/reset`) retires it at once. The feed serves the member's selection (`psi_CalendarSelection`, `"<type>:<id>"` strings) intersected with the calendars shared now, includes only events, applies the same privacy rewriting, and answers an unknown token with a bare 404. Format: RFC 5545, three months back to eighteen months ahead, `VALUE=DATE` for whole-day and virtual events, UTC for timed ones, `CATEGORIES` naming the source calendar; built by `PortalCalendarFeed`. API: `GET/PUT /api/portal/calendar/subscription`.
- Volunteer occurrences not linked to an event live under "My Volunteer Schedule", not on calendars.

### Directory (`/portal/directory`)

- Capability `portal.directory.view`: feature switch `bPortalShowDirectory`, granted by `DirectoryViewerRule` to people whose classification is in the viewer list. The rule reads the setting on every request.
- `PortalDirectoryService` decides who is listed: the listed classifications, alive, active, not awaiting review, outside deactivated families. `withRecordPolicy()` is where #10524's protected-household policy applies. A family with two or more listed people is one entry (head, spouse, children, others, as the printed directory orders them); anyone else is a person entry. A field the administrator leaves off is never read into an entry. A person with Hide Age never shows a birth year.
- `GET /api/portal/directory` returns the same groups the page renders. `GET /api/portal/directory/{person|family}/{id}/photo` serves a photo only of someone the directory shows (`private, no-store`); anything else is 404.
- Browsing, the page lists every entry, filters by name in the browser (`webpack/portal/portal-directory.ts`) and links each first letter. Search only, `PortalDirectoryService::find()` answers nothing below `SEARCH_MIN_LENGTH` characters and at most `SEARCH_LIMIT` entries, on the page (`?q=`) and the API alike, so the whole list never leaves the server.

### Volunteering (`/portal/volunteer/schedule`, `/portal/volunteer/opportunities`)

Twig templates (`volunteer/schedule.html.twig`, `volunteer/opportunities.html.twig`) extending the portal layout load the `webpack/portal/volunteer-*.ts` bundles, which render from `/api/ministries/me/*`. The nav carries one **Volunteering** entry pointing at the schedule; the two pages carry a tab bar (*My schedule* / *Find something to do*). The entry and both routes ask the portal capability `portal.volunteer.use`, on while `User::isVolunteerV2Enabled()` and `bPortalShowVolunteer` are. The layout loads `bootbox`, and `portal.min.js` defines `window.CRM.escapeHtml` when `CRMJSOM.js` is absent.

### My Teams (`/portal/teams`, `/portal/teams/{teamId}`, `/portal/teams/{teamId}/occurrences/{id}`)

For team leaders and staff who lead a team. The team page is the ministry page's Positions, Volunteers, Schedules and Occurrences tabs narrowed to one team, rendered by `webpack/portal/teams.ts`. Team leaders create, edit and generate schedules for their own team. Omitted: Teams card, Ministry Coordinators, Help Wanted, Add from Cart, Remove Volunteer, team delete/rename.

**Add Volunteer (#10531).** The Volunteers tab offers a team leader **Add Volunteer**: an existing person, found with `GET /api/ministries/people/search?q=` (active people only, at least two characters, at most 20 results, each `{id, name, familyName}` and nothing else), joins the ministry's volunteer pool, optionally ticked for positions of this team, through `POST /api/ministries/teams/{teamId}/pool/{personId}`. The ministry's coordinators are emailed; the added person is not. A member login reaches the search only while it leads a team, so it can list directory names twenty at a time: the product owner's accepted trade-off (2026-10-11), kept narrow by the fields and the cap. The admin team-only view (#10530) offers the same dialog.

- No `/api/portal/teams/*` namespace: the index is server-rendered from `PortalTeams`; the other pages call volunteer endpoints that authorize per record, including the team-keyed `GET /volunteer/teams/{id}/qualification-matrix` and `GET /volunteer/teams/{id}/schedules` (behind `VolunteerTeamMiddleware`).
- `VolunteerCoordinatorRoleAuthMiddleware` admits `isVolunteerCoordinatorEnabled() || isVolunteerTeamLeaderEnabled()`. `isVolunteerCoordinatorEnabled()` stays false for self-service logins, which keeps the Ministries menu heading hidden for them.
- `teams/occurrence.html.twig` reproduces the ids `occurrence.ts` expects and loads `ministries-occurrence.min.js`; the linked calendar event is named, not linked, because `/event/view/{id}` is an admin-shell page. Its Assign dialog is the admin one, *Record they accepted* box included (#10528).
- The nav entry and every team page ask `volunteer.team.manage` on the team (`RequirePortalCapability`); another team's page is 404. Staff permissions add nothing: a coordinator, manager or administrator opening the portal sees only the teams they lead, and runs the rest from the ministry page.
- Shared components live in `webpack/ministries/components/` (`ui.ts`, `positions-table.ts`, `qualification-matrix.ts`, `schedules-table.ts`, `occurrences-table.ts`) and take a context object. Each looks up its controls by id and is inert when one is absent. `webpack/common/crm-helpers.ts` installs `window.CRM.escapeHtml`, `escapeAttribute` and `buildActionMenu` when absent; the portal has no DataTables, so its tables are plain tables.

### Email History (`/portal/email-history`, `/portal/email-history/{id}`)

What the church has emailed this member, newest first, paginated; reached from the account menu (`PortalNav` gets no active id for these pages). Rows come from `email_log_eml` through `EmailLogService`.

- List (`email/index.html.twig`): Date, Type (`EmailLogService::kindLabel()`), Subject (link; "(no subject)" when empty). Only mail that went out (`sent` rows) is listed, with no delivery status; an unsent row is the same 404 as somebody else's. `EmailLogService::DEFAULT_PAGE_SIZE` per page, `?page=` driven. Server-rendered, no bundle. Empty state: "The church has not emailed you yet." One `<table>` at every width, stacked on a phone by `_portal.scss` (`data-label` pattern).
- Detail (`email/show.html.twig`): the stored body renders in `<iframe sandbox srcdoc="...">` only; an empty `sandbox` gives an opaque origin with no scripts, forms or navigation, and Twig autoescaping fills the attribute. The frame has a fixed height and scrolls. An account email (body never stored) says "The content of this email was not kept."
- API: `GET /api/portal/me/emails?page=&limit=` and `GET /api/portal/me/emails/{id}`, session only. A row that is not this person's and a missing id are the same 404.
- Only rows whose `eml_per_ID` is this person appear. Mail to a family's shared address (`eml_per_ID` NULL, `eml_fam_ID` set) is left out; showing it to every adult of the household is a privacy decision (`EmailLogService::getForFamily()` would serve it).
- Read-only: no re-send, delete or mark-read.

## Data

| Item | Where |
|---|---|
| `calendars.ministry_id` (nullable, indexed, FK to `volunteer_ministry_vmin`) | `orm/schema.xml`, `Install.sql` |
| `person_signin_psi.psi_LastActivity`, `psi_CalendarToken` (UNIQUE), `psi_CalendarSelection` (the `user_usr` columns are no longer read), `psi_Language` (NULL = the church's) | `orm/schema.xml`, `Install.sql` |
| Config items listed above, no System Settings category | `SystemConfig::buildConfigs()` |

## Tests

Members have no account. The seeded member persona is person 100 (Lena Black, family 20), signed in with `cy.signInAsMember(100)` (an emailed code read from Mailpit, `cypress/support/member-session.js`); `cy.makeMemberAPICall(99, …)` calls the API in person 99's member session. `ui`, `ui-admin` and `api` suites run under separate configs.

## Minimal church theme

```
Include/themes/uccc/
  theme.css        the token block from "Design tokens" and nothing else
```

That recolours and refonts every portal page. Adding `templates/home.html.twig` that `{% extends "@default/home.html.twig" %}` and overrides `{% block hero %}` puts the church's photo and welcome text on the home page. Upload over FTP, pick the theme on Admin -> Member Portal, reload.
