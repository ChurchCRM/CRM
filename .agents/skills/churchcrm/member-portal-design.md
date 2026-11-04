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

Out of scope: contribution history and online giving, per-event visibility flags, personal-device check-in, theme editor or upload UI, theming the admin shell. The member directory (`docs/design/member-directory.md`) is a draft that needs a privacy model first: whether a member can leave themselves out of the portal and printed directories is open in #10869 (design: `docs/design/member-directory.md`, #10870), and #10524's protected-household policy must apply. The team leader's name-only Add Volunteer picker (#10531) is not a directory.

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

### Buttons and spacing

The default templates own the spacing. A theme that overrides a template keeps it.

- A row of actions is `.portal-action-row` or `.portal-form-actions`: flex, wrap, `0.5rem` gap. The main action is `.portal-button`. The action beside it is `.portal-button-quiet`.
- Volunteer and team templates use Bootstrap `.btn` because the shared bundles look those classes up. `portal.min.css` paints `.btn-primary` and `.btn-outline-primary` from `--portal-primary`, so a theme's colour reaches them. Success and danger buttons stay semantic.
- The calendar toolbar hook is `.portal-fc-toolbar`. Core CSS gives each button the portal radius and `0.5rem` before the next sibling. A theme recolours with the tokens and keeps that gap.
- Home cards are two columns from the tablet width up. At desktop, the calendar card spans half the row only when the volunteering card is absent, so four cards stay two rows of two.

A full portal on Admin → Member Portal: show the calendar and share the church's public calendar (birthdays and anniversaries are optional; they show a first name and last initial); show volunteering when ministry management is on v2; leave developer mode off. Birthday editing is the church's choice. Off hides the birthday. The system theme, or a copy that only sets the tokens above, is the look these rules describe.

### Template contract

`layout.html.twig` defines blocks `head_extra`, `header`, `nav`, `hero`, `content`, `sidebar`, `footer`, `scripts_extra`. Page templates fill `content` and optionally `hero` and `sidebar`. Partials are named, so a theme can override `partials/nav.html.twig` alone. Variables, ids and `data-` hooks per template are in https://docs.churchcrm.io/administration/member-portal-template-reference.

A bundle treats a missing hook as "this theme does without the feature". Adding a variable or block is compatible; renaming or removing one is a release-note item and keeps the old name as an alias for one release. The validator's "not used by this version" warning tells a theme author that an overridden template moved.

### Theme security

- Only allow-listed extensions leave through the asset route, only from real theme folders, never via `..` or a symlink that leaves the theme
- A theme is trusted like any other file the administrator places on the server; never use `|raw` on member data
- `theme.js` runs under the CSP nonce; inline scripts in theme templates carry `nonce="{{ nonce() }}"`
- The admin page states "Themes are provided by your church, not by ChurchCRM"
- Login as Person (`ChurchCRM\Portal\Auth\LoginAsPerson`, member sign-in design §6.5) sets the administrator's staff session aside while an ASSISTED principal runs, so portal code never sees a staff user then. `LoginAsPersonMiddleware` refuses (unless `bLoginAsPersonCanWrite`) and records every non-GET request; a new write path needs nothing more. A theme overriding `layout.html.twig` keeps `partials/login-as-person-banner.html.twig`: Stop is the only way back, so `PortalTwig` adds the banner after `<body>` to a page that leaves it out

## Tests

Members have no account. The seeded member persona is person 100 (Lena Black, family 20), signed in with `cy.signInAsMember(100)` (an emailed code read from Mailpit, `cypress/support/member-session.js`); `cy.makeMemberAPICall(99, …)` calls the API in person 99's member session. `ui`, `ui-admin` and `api` suites run under separate configs.

Request flow, admin page, screens, APIs, data and the minimal theme example: [`docs/design/member-portal.md`](../../../docs/design/member-portal.md).
