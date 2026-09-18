# Member Portal — design draft

Status: **proposal v3** (2026-09-16), the design document that epic
[#8977 "Member self-service portal"](https://github.com/ChurchCRM/CRM/issues/8977) asks for before
implementation, extended with the theming and volunteer requirements University City Church of
Christ (UCCC) needs. Product decisions were made by UCCC's product owner; the maintainer's open
points are listed in §8.

Sequencing decision (product owner, 2026-09-15): **this epic ships before the Volunteer v2 epic (#9701)**,
because the volunteer member surface and the team-leader experience depend on it.

Changes in v3 (2026-09-16): admins leave the portal through the same exit control the masquerade uses; no PHP extension point, ever; no feature flag — the portal is simply on, and the limited-access page is retired; the uploaded church logo (PR #9719) is the default theme's logo; a theme's display name is its folder name exactly unless `theme.json` overrides it; ministry calendars and the portal calendar list (§5.3).

Changes in v2 (2026-09-16, product-owner review): no theme manifest; themes live under
`Include/themes/`; theme assets served through the application, not the web server; a dedicated
Admin → Member Portal page instead of System Settings items; a broken theme fails loudly instead of
falling back; landing rule simplified to "Edit Self only → portal, everyone else → admin";
Giving deferred out of the first version; volunteer pages become Twig templates around the
existing bundles; the admin "Volunteer" heading goes away; team leaders may create schedules for
their own team.

---

## 0. Preface

### 0.1 Purpose

Most people who log in to a church's ChurchCRM are not staff. Today every login lands in the
administrator shell; a login with only the "Edit Self" flag is bounced to a one-page
"limited access" landing with three buttons, and the moment such a person opens the volunteer
pages they are inside the admin skin again, with a sidebar of links that all bounce them back.

The Member Portal is a **separate, member-facing area** of the same installation, at its own URL
prefix, with its own layout and navigation, that a congregation member lands in after login. It
is what a hosting control panel calls a *client area*: everything a member may do for
themselves, nothing they may not, and no administrator furniture in sight.

### 0.2 The product principle

> **A member never sees the admin shell.** Everything a member may do is inside the portal;
> anything the portal does not offer, the member cannot do.

Every decision below is measured against that. A page that would have to bounce the member to
an admin page is not finished.

### 0.3 Driving use cases

| # | Use case | What it forces |
|---|---|---|
| **UC1** | **Ordinary member.** Logs in on a phone. Sees this week's church calendar, their family's details, and their volunteer schedule. Updates their phone number. | Read-only calendar; family self-edit; volunteer pages inside the portal; mobile-first layout. |
| **UC2** | **Team leader with a member login.** Tony Smith leads the Wednesday Evening team of Children's Ministry. He has a self-service login, not a staff account. He must see his ministry, manage his team's positions, volunteers and schedules, and staff his team's occurrences — and nothing outside his team. | Volunteer scopes must count for self-service accounts (revises Volunteer v2 decision D14); a team-scoped ministry view inside the portal. |
| **UC3** | **The church's brand.** University City Church of Christ wants the portal in its own colours, fonts and imagery, with the option to change the markup of a page or two, by uploading files over FTP that survive every ChurchCRM update. Another church wants only a colour change. Upstream wants none of this to touch core. | Themes: a default theme in core, church themes as drop-in folders in an update-safe location, selected on an admin page, with per-template override and live editing. |
| **UC4** | **Staff member who is also a member.** An administrator volunteers and has a family too. | Admins keep landing in the admin shell but can open the portal from their user menu; the same person id drives both. |

### 0.4 Fixed decisions (do not relitigate in a PR)

| # | Decision | Why |
|---|---|---|
| P1 | **New MVC module `src/portal/`, URL prefix `/portal`.** | Same shape as `src/volunteer/`, `src/event/` etc. (`MvcAppFactory`); nothing new for reviewers to learn. |
| P2 | **Twig for portal pages, with a theme override loader.** | Twig 3 is already a dependency (`twig/twig ^3.20`) and already renders the emails with a `gettext` extension (`src/ChurchCRM/Twig/GettextExtension.php`), so reviewers know it. Its templates cannot execute PHP, only what the environment exposes, which is what makes church-supplied overrides safe. Its `FilesystemLoader` takes an ordered path list, which is the override mechanism itself. It compiles to cached PHP and recompiles on file change, which gives live editing for free. Plain-PHP views (`PhpRenderer`, used by every other module) offer none of that. |
| P3 | **A theme is a folder. Its folder name is its identity. No manifest is required.** | The folder path already says everything the system needs. The display name is the folder name exactly, unless an optional `theme.json` provides `name` (and `author`, `description`) for the admin page. |
| P4 | **Church themes live in `Include/themes/<name>/`; the system theme in `Include/themes/default/` (in core).** | `Include/` is the directory ChurchCRM already promises survives updates (it holds `Config.php`, which the upgrader never overwrites, and the orphan-file scan already exempts it). It is where a church FTPs files today. Payment gateways and other church-supplied modules get sibling folders under `Include/modules/<type>/` when they exist (§3.8). |
| P5 | **Theme assets (CSS, JS, images, fonts) are served by the application at `/portal/theme/<name>/<path>`, never directly by the web server.** | `Include/` is deny-all at the web-server level on purpose (GHSA-mp2w-4q3r-ppx7) and must stay that way. A small streaming route with an extension allow-list, ETag and long cache headers costs one cheap request per asset and works identically on Apache, nginx and FrankenPHP with no server configuration. It also means templates and any stray file in a theme are never web-readable. |
| P6 | **A theme is CSS + images + optional `theme.js` + optional template overrides. No PHP.** | A theme folder is uploaded by the church's administrator. Twig templates can only call what the portal exposes; a PHP file in a theme would be code execution by upload. "Minor custom code changes" are served by template overrides and `theme.js`. There is no PHP extension point, now or later. |
| P7 | **Templates are live: an edited template file is picked up on the next request.** | Twig compiles to a PHP cache and, with `auto_reload`, recompiles a template whose file changed. Designers edit over FTP and reload. A "Developer mode" switch on the admin page turns the compile cache off entirely. |
| P8 | **A broken theme fails loudly.** Activation compiles every template in the theme and refuses with the exact file, line and message. At render time an error in the active theme shows the administrator the error and shows members a portal-styled "temporarily unavailable" page; the error is written to the application log (Admin → System → Logs) and to PHP's `error_log` (the web server's error log). There is no silent fallback to the default theme. | Silent fallback hides the problem from the designer. |
| P9 | **Configuration lives on a dedicated Admin → Member Portal page**, not in System Settings. | System Settings is not accepting new items; the page is also the home for theme validation, statistics and, later, calendar visibility and modules. Values are still `ConfigItem`s in `config_cfg` so backups, exports and the config API work unchanged; they carry no System Settings category, which is exactly how an item is hidden from that page. |
| P10 | **Landing rule: an Edit-Self-only login lands in `/portal` and cannot reach the admin shell; every other login lands in the admin shell as today and gets a "Member Portal" entry in its user menu.** While a staff member is in the portal, the header's account menu carries an **Admin Console** entry that returns them to the admin dashboard; it is shown for every staff login and hidden during a masquerade. | Every staff login, however limited, holds View on people and families; "self-service" is the only member persona. No third case. Staff need an obvious way back, but a fixed bar on every page was too loud a way to give them one (product review, 2026-09-17): the menu entry does the same job and costs no vertical space. A masquerading administrator leaves through the banner's own exit control, so the entry would be a second, wrong way out. |
| P11 | **Every portal API derives the acting person from the session. No route accepts a `personId` naming the actor.** | Same invariant Volunteer v2 §3.3.3 uses; it makes IDOR structurally impossible on the member surface. |
| P12 | **Family scope = the member's own family**, via the existing `User::canViewFamily()` / `canEditPerson()` rules. | Nothing new to audit; the rules already exist for the Edit Self flag. |
| P13 | **The administrator chooses which calendars the portal shows**, on the Admin → Member Portal page, from one list that holds the church calendars (the `calendars` rows) and the system calendars (Birthdays, Anniversaries, Holidays, Unpinned events). Stored as a JSON config value, not a column, because system calendars are virtual. Events inherit from the calendars they are pinned to. **Every volunteer ministry gets its own calendar**, created with the ministry (`calendars.ministry_id`), which its coordinators may pin events to and which the administrator may show in the portal like any other. | There is no per-event visibility flag; the system calendars are not table rows; ministries have no calendar today (§5.3). |
| P14 | **Giving is not in the first version.** The portal is where contribution history and online giving will live; both wait for a payment-gateway module design (out of scope here, §5.7). | No gateway, giving model or payment flow exists in the codebase; designing one is its own epic. |
| P15 | **The volunteer member pages move into the portal: Twig templates replace their PHP views; their TypeScript bundles and `/api/ministries/me/*` are reused unchanged.** | The bundles render everything from the API; the PHP views were only chrome. |
| P16 | **The admin shell loses its "Volunteer" heading. Member-facing volunteer functionality exists only in the portal; the admin side keeps "Ministries".** | One place for each audience. Staff reach their own schedule through the portal link in their user menu. |
| P17 | **Volunteer v2 D14 is revised: scopes count for self-service accounts, and team leaders may create schedules for their own team.** Coordinators and managers stay staff accounts (D12 tiers unchanged). | UC2. D14's rationale (least authority for a volunteer login) still holds for volunteers; it never needed to deny scopes. |
| P18 | **Release target 7.8.0**, migrations named `7.8.0-member-portal-*.sql`, not registered in `upgrade.json` until the block opens. | Maintainer's release policy, as for the volunteer epic. |

### 0.5 Non-goals (first version)

- Contribution history, online giving, pledges, payment gateways and payment flows (P14, §5.7).
- Per-event visibility flags (calendar-level only, P13).
- Directory search across members (#8977 asks for it; it needs a privacy model that does not
  exist — §5.6 sketches phase 2).
- Personal-device check-in (#8978) — depends on the portal; separate epic.
- Coordinator or manager tiers on a self-service login (P17 keeps them staff).
- A theme editor or upload UI; themes are files on disk, uploaded by the administrator.
- Theming the admin shell. Themes apply to the portal only.

### 0.6 Facts about the current codebase this design relies on

Verified 2026-09-15/16 on `feature/volunteer-v2-integration` (2d43e432b); file:line as of then.

1. **Page views are plain PHP** via `Slim\Views\PhpRenderer`; every module view `require`s
   `Include/Header.php` (the admin shell) and `Include/Footer.php`. The auth-flow pages use the
   minimal `Include/HeaderNotLoggedIn.php` / `FooterNotLoggedIn.php` (core CSS/JS bundle, moment,
   plugin head content; no sidebar).
2. **Twig is present for email only**: `BaseEmail.php:42` builds a `FilesystemLoader` on
   `src/templates/email`; `GettextExtension` exposes `gettext()`.
3. **`Include/` is deny-all at the web level** (`src/Include/.htaccess`, GHSA-mp2w-4q3r-ppx7) and
   holds `Config.php`. The integrity/orphan scan (`AppIntegrityService::isExcludedFromOrphanDetection`,
   `AppIntegrityService.php:~463`) exempts `Include/Config.php` and `plugins/community/`; a new
   `Include/themes/` pattern joins that list (and `generate-signatures-node.js`), so church themes
   are never reported as orphaned files.
4. **Self-service persona** = `User::isEditSelfExclusive()` (`!isAdmin() && isEditSelf()`,
   `User.php:138`). Every module permission getter short-circuits to false for it. `AuthMiddleware`
   302s such browser sessions to `/external/limited-access` except for paths in
   `isLimitedAccessAllowedPath()`; `Include/PageInit.php:25` applies the same bounce to every
   legacy `*.php` page.
5. **User id is the person id** (`user_usr.usr_per_ID`). `User::canEditPerson()` (`User.php:399`)
   and `canViewFamily()` (`:435`) already express "own record / own family". `usr_LastLogin` and
   `usr_LoginCount` are stamped by a real login (`LocalAuthentication.php:131-133`); a masquerade
   does not touch them (#9843).
6. **Family verification** is a token flow (`Token::TYPE_FAMILY_VERIFY`) whose form is read-only plus
   a comment, ending in a `Note` of type `verify`. Members cannot edit fields through it.
7. **Public calendars** are token-addressed, gated by `bEnableExternalCalendarAPI` and
   `PublicCalendarMiddleware`; events have no public/private flag. FullCalendar v7 is bundled in
   the `event-calendars` and `external-calendar` webpack entries.
8. **Branding today**: the church logo upload (issue #9717, PR #9719, under review upstream;
   already live for UCCC through the `uccc-prod` branch) stores an uploaded logo and uses it in the
   sidebar, login and auth pages; `ChurchMetaData::getChurchLogoURL()` resolves it, falling back to
   the stock image. Per-user light/dark mode. No custom-CSS hook anywhere.
9. **Settings**: `ConfigItem`s are declared in `SystemConfig::init()`; only items listed in a
   category (`SystemConfig.php:~300-320`) appear on `src/SystemSettings.php`. Writes go through
   `POST /admin/api/system/config/{name}`. An item with no category is a first-class config value
   that the System Settings page never shows.
10. **Plugins** can inject `<head>`/footer HTML and register routes under `/plugins/*` only; no asset
    serving, no template overrides. Community plugins are installed by an admin-only URL installer
    with verification into `plugins/community/`.
11. **Cypress**: seeded member persona is user 100 (`lena.black.editself.notes@example.com`,
    password `changeme`, family 20; the username is truncated to 32 characters at login); user 99
    is the API-only Edit Self persona; `ui`, `ui-admin`, `api` suites run under separate configs.

---

## 1. Reuse matrix

| Need | Existing thing | Verdict | Notes |
|---|---|---|---|
| Module skeleton, auth stack, CSRF, error pages | `MvcAppFactory`, `CSRFMiddleware`, `SlimUtils::registerDefaultHtmlErrorHandler` | **Reuse** | `src/portal/index.php` = `MvcAppFactory::create('/portal', [...])`. |
| Template engine | Twig 3 + `GettextExtension` | **Reuse + extend** | New `ChurchCRM\Portal\PortalTwig` factory (§3.4). |
| Minimal page chrome | `HeaderNotLoggedIn.php` / `FooterNotLoggedIn.php` | **Do not reuse as-is** | The portal renders its own `layout.html.twig`. The asset list those includes load (core CSS/JS bundle, moment, plugin head content, i18next, locale loader) is reproduced in the layout so plugins and translations keep working. |
| Core bundle, Tabler, Bootstrap 5, TomSelect, bootbox, DataTables | `skin/v2/churchcrm.min.{css,js}` + Footer scripts | **Reuse** | The portal ships one extra entry `portal.min.{css,js}`; theme CSS loads after it. |
| Self-service persona and family scoping | `isEditSelfExclusive()`, `canEditPerson()`, `canViewFamily()` | **Reuse** | The portal never invents a second permission model. |
| Post-login bounce | `AuthMiddleware` session branch, `PageInit.php` | **Extend** | Redirect target becomes `/portal`; `/portal/` and `/api/portal/` join the allowed paths; the volunteer entries in that list go when the pages move (P15). |
| Family verification | `Token`, `/external/verify`, `family-verify` bundle | **Reuse the token and the note, replace the page** | The portal's "Confirm your family details" writes the same `verify` note, so staff review is unchanged. |
| Person / family edit | `/api/person`, `/api/family` (writes behind `EditRecords`) | **Do not reuse for writes** | New `/api/portal/me/*` endpoints with the field allow-list of §5.2. |
| Calendar | `PublicCalendarMiddleware::getEvents()` logic, `external-calendar` bundle | **Reuse the event query, new gate** | `GET /api/portal/calendar/events` returns events of portal-visible calendars for the session; no access token in the URL. |
| Volunteer member pages | `webpack/volunteer/{my-schedule,opportunities,member-ui}.ts`, `/api/ministries/me/*` | **Reuse bundles and API; replace the PHP views with Twig** | §5.4. |
| Team-scoped ministry management | `VolunteerAuthorizationService` (`canManageTeam`, `getManagedTeamIds`), team-level APIs | **Reuse** | The scope model already narrows at query level; only the short-circuit for self-service accounts goes (P17). |
| Church identity | `ChurchMetaData` | **Reuse** | Exposed to every template as `church`. |
| Plugins in the portal | `PluginManager::getPluginHeadContent()`, `Hooks` | **Reuse + one new hook** | `Hooks::PORTAL_NAV_BUILDING` lets a plugin add a portal nav item. |
| Impersonation banner | `Include/ImpersonationBanner.php` (#9843) | **Reuse** | Rendered by the portal layout, so "Login as User" shows the portal exactly as the member sees it. |
| Config storage and API | `ConfigItem`, `config_cfg`, `POST /admin/api/system/config/{name}` | **Reuse** | The Member Portal admin page reads and writes through them (P9). |
| Admin page pattern | `src/admin/` MVC module (`routes/system.php`, `views/*.php`) | **Reuse** | `/admin/member-portal` is one more admin route + view. |
| i18n | `gettext` in templates, `i18next` in TS, `locale-loader` | **Reuse** | Twig gets `gettext`/`ngettext`; `Include/themes/default/templates/**/*.twig` joins the extraction globs. |

---

## 2. Architecture

### 2.1 Layout on disk

```
src/portal/                              the module (core)
  index.php                              MvcAppFactory::create('/portal', ...)
  routes/{home,profile,family,calendar,volunteer,teams,theme-asset}.php
src/ChurchCRM/Portal/
  ThemeManager.php                       discovers, validates, activates themes
  ThemeValidator.php                     compiles every template, reports file/line/message
  PortalTwig.php                         builds the Twig Environment (loader order, extensions, globals)
  PortalNav.php                          the nav model (what this member may see)
  PortalAccessMiddleware.php             "authenticated session, not an API key"
  ThemeAssetStreamer.php                 serves theme files with an extension allow-list
src/Include/themes/
  default/                               the system theme (core, versioned, overwritten on update)
    theme.css                            design tokens + component styles on top of portal.min.css
    images/…
    templates/                           every portal template lives here
      layout.html.twig
      partials/{nav,header,footer,flash}.html.twig
      home.html.twig
      profile/{index,edit}.html.twig
      family/{index,edit,confirm,none}.html.twig
      calendar/index.html.twig
      volunteer/{schedule,opportunities}.html.twig
      teams/{index,team,occurrence}.html.twig
      errors/{403,404,500,theme-error,unavailable}.html.twig
  universitycitychurchofchrist/          a church theme, uploaded over FTP; git-ignored; survives updates
    theme.json                           optional: {"name": "...", "author": "..."}
    theme.css
    theme.js                             optional
    images/…
    templates/                           optional; any subset of the default tree
      home.html.twig
      partials/header.html.twig
src/Include/modules/                     reserved: church-supplied modules by type (§3.8), e.g. gateways/
src/admin/routes/member-portal.php       Admin → Member Portal page + its API
src/admin/views/member-portal.php
src/api/routes/portal/                   /api/portal/* (session-only, actor from session)
webpack/portal/{portal.ts, calendar.ts, teams.ts}
src/skin/scss/_portal.scss               → part of portal.min.css
```

`Include/themes/*` is git-ignored except `default` (`.gitignore`: `src/Include/themes/*`,
`!src/Include/themes/default`), the same treatment `plugins/community/` gets, so a church theme
lives in its own repository and is deployed by FTP or by the church's deploy script.

**Why it survives updates.** ChurchCRM's upgrader (in-app or by unpacking a release) writes the
files in the release archive and leaves everything else in place; that is how `Include/Config.php`
and uploaded photos under `Images/` survive today. The release archive contains
`Include/themes/default/` and nothing else under `Include/themes/`, so a church's folder is
untouched, while the default theme is refreshed. The orphan-file scan exempts `Include/themes/`
so the folder is never flagged. `default/` must never be edited by a church: it is overwritten on
every update; copy it and rename the copy.

### 2.2 Request flow

1. Login as today (`/session/begin`). On success the landing is decided by one rule:
   `isEditSelfExclusive()` → `/portal`; everyone else → `/v2/dashboard`. The interim
   `/external/limited-access` page is retired: its route redirects to `/portal`. There is no
   feature flag; installing the release is what turns the portal on, and it only adds capability
   for an existing church.
2. `AuthMiddleware` session branch: an Edit-Self-only browser request outside
   `isLimitedAccessAllowedPath()` is redirected to `/portal`. `/portal/` and `/api/portal/` are
   allowed paths. The API-key branch stays a hard 403 for those accounts (P11).
3. `src/portal/index.php` mounts the module with `PortalAccessMiddleware` (302 to login when
   unauthenticated; API-key callers refused; CSRF on every POST).
4. A route handler builds a view-model and calls `PortalTwig::render($response,
   'calendar/index.html.twig', $model)`. The loader order is *active theme → default theme*, so a
   theme file with the same relative path wins.
5. The template extends `layout.html.twig` (which the theme may also override). The layout emits
   `<head>` (core CSS, `portal.min.css`, then the theme's `theme.css` via the asset route if the
   file exists), the nav partial, the flash partial, the page block, the footer partial, then the
   scripts (core bundle, i18next, locale loader, `portal.min.js`, the page's bundle, `theme.js` if
   present) — all with the CSP nonce.
6. `GET /portal/theme/<name>/<path>` streams a theme file when `<name>` is a real theme folder,
   `<path>` contains no `..`, and the extension is one of `css js png jpg jpeg gif svg webp ico
   woff woff2 ttf` — with `Content-Type`, `ETag` from `filemtime`+size, `Cache-Control:
   public, max-age=31536000` (URLs carry `?v=<filemtime>`), and `304` on `If-None-Match`. Anything
   else is 404. This route is public (no session) so a cached page never breaks on a logged-out
   asset request, and it exposes nothing but the allow-listed static files.

### 2.3 Who lands where

| Account | Lands in | Portal | Admin shell |
|---|---|---|---|
| Any staff login (admin flag or any module permission, including View-only) | `/v2/dashboard` | Yes, via user-menu "Member Portal"; the top bar's exit control returns to admin | Yes |
| Edit Self only (self-service) | `/portal` | Yes | No |

---

## 3. Theming

### 3.1 What a theme is

A folder under `Include/themes/`. The folder name is the theme's id and its display name, shown
exactly as spelled, unless `theme.json` provides a `name`. Every file is optional:

| Path | Purpose |
|---|---|
| `theme.css` | Loaded after `portal.min.css`. Overrides design tokens (§3.5) and any component style. |
| `theme.js` | Loaded last, with the CSP nonce, after `window.CRM.onLocalesReady`. |
| `images/`, `fonts/` | Served by the asset route; referenced from CSS by relative path and from templates by `theme_asset('images/x.png')`. |
| `templates/**` | Any file with the same relative path as one in `default/templates/` replaces it. A theme may add new partials and include them from its overrides. |
| `theme.json` | Optional `{name, author, description}` for the admin page. Nothing functional. |

### 3.2 Discovery, validation and activation

`ThemeManager`:

- `listThemes()` scans `Include/themes/*/` (directories only; `default` first, the rest by display
  name). A folder is a theme by existing. Nothing is skipped or hidden.
- `validate(name)` (`ThemeValidator`) compiles every `templates/**/*.twig` in the theme through the
  portal environment, checks that every override corresponds to a template that exists in
  `default/` (an override with no counterpart is reported as "not used by this version"), checks
  `theme.css`/`theme.js` exist if referenced, and returns a list of `{file, line, message, level}`.
- `activate(name)` runs `validate`; any error-level finding refuses activation and the admin page
  shows the list. Warnings (unused overrides, missing optional files) are shown and allowed.
- `getActiveTheme()` reads `sMemberPortalTheme`. If the folder no longer exists the portal renders
  the `theme-error` state (§3.3), it does not quietly switch to `default`.

Live editing (P7): the Twig environment is created with `cache => src/Include/cache/twig-portal/`
and `auto_reload => true`, so a changed file recompiles on the next request. With "Developer
mode" on, `cache => false`. Either way an editor never needs to clear anything.

### 3.3 Failure behaviour (P8)

| Situation | Administrator sees | Member sees | Logged |
|---|---|---|---|
| Activation of a theme with a template error | The admin page refuses, listing file, line, message | nothing changes | app log, level warning |
| Runtime error in the active theme (syntax after a live edit, missing include, exception in a function) | `errors/theme-error.html.twig` from the **default** theme: theme name, file, line, message, link to the admin page | `errors/unavailable.html.twig` from the default theme: "The member portal is temporarily unavailable. The church office has been notified." | app log (Admin → System → Logs) **and** `error_log()` → web server error log, one line with theme, file, line, message |
| Active theme folder missing | same as above, message "theme folder not found" | same | same |

The default theme's templates are the only ones used to render these two pages, so they always
render.

### 3.4 The Twig environment

`PortalTwig::create()` builds one `Twig\Environment` per request:

- Loader: `FilesystemLoader([activeThemeTemplates, defaultTemplates])`; first path wins. The
  `@default` namespace is registered for the default tree so an override can
  `{% extends "@default/home.html.twig" %}` and change one block.
- `autoescape: 'html'`; `strict_variables: false`; cache and `auto_reload` as in §3.2.
- Extensions: the existing `GettextExtension` extended with `ngettext`; a new `PortalExtension`
  exposing exactly these functions and globals and nothing else:

| Function / global | Returns |
|---|---|
| `url(path)` | `SystemURLs::getRootPath() . path` |
| `asset(path)` | `SystemURLs::assetVersioned(path)` (core assets) |
| `theme_asset(path)` | `/portal/theme/<active>/<path>?v=<filemtime>`; if the file is missing in the active theme but present in `default`, the default's URL |
| `csrf_field()` | `CSRFUtils::getTokenInputField()` |
| `nonce()` | `SystemURLs::getCSPNonce()` |
| `church` | `{name, address, city, state, zip, phone, email, website, logoUrl, socialLinks}` from `ChurchMetaData`; `logoUrl` is the uploaded logo from PR #9719 when set, else the stock image. The default theme's header uses it; a theme may replace it with `theme_asset()`. `socialLinks` (#9907) is the church's configured social accounts, ordered X, YouTube, Facebook, Instagram, each `{id, label, url, icon}`, empty when none is set — the default theme's footer renders them as icon links on its trailing edge |
| `member` | `{id, firstName, lastName, fullName, email, avatarUrl, familyId, isTeamLeader, isStaff}` |
| `nav` | the `PortalNav` model: ordered `[{id, label, url, icon, active, badge}]` |
| `flash` | `[{type, message}]` from the session |
| `portal` | `{rootPath, themeName, locale, isRTL, impersonating, developerMode}` |

No PHP includes, no filesystem or network functions, no `$_SESSION`. Twig's sandbox extension is
not needed: the surface is what the extension exposes.

### 3.5 Design tokens

`portal.min.css` (from `src/skin/scss/_portal.scss` on top of Tabler) styles every component with
CSS custom properties, so a colour-only theme is a `theme.css` of a dozen lines:

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

Notices are part of that palette: `--portal-success`, `--portal-warning`, `--portal-danger` and
`--portal-info`, each with a `-contrast` twin for the text drawn on it, plus `--portal-toast-width`.
The full list a theme may set is `docs/portal-themes.md` → "Design tokens".

**Every portal notice is a toast in one fixed container** (`#portal-toasts`, rendered by
`partials/flash.html.twig`, filled by the server's `flash` messages and by
`window.CRM.portalToast(message, type)`), never a card in the document flow — a notice that takes
part in the flow moves the page under the member as it appears and again as it goes. Note that
`.portal-container` is not unique: the header, the nav and `<main>` each have one, so a bundle that
inserts a notice "at the top of the page" by that selector lands in the **header** and displaces the
church logo. <!-- learned: 2026-09-16 -->

Dark mode: tokens have a `[data-bs-theme="dark"]` block in the default `theme.css`; a theme may
override it or not. Fonts must come from `self` or `fonts.googleapis.com`/`fonts.gstatic.com`
(the CSP allows those). RTL: portal CSS uses logical properties only, so a theme is not flipped by
`churchcrm-rtl.min.css` and does not need to be.

### 3.6 Template contract

`layout.html.twig` defines the blocks `head_extra`, `header`, `nav`, `hero`, `content`, `sidebar`,
`footer`, `scripts_extra`. Page templates fill `content` and optionally `hero` and `sidebar`.
Partials are named, so a theme can override `partials/nav.html.twig` alone. The view-model of every
page is documented in `docs/portal-templates.md` in the repo (each template, its variables, their
shapes). Rules for core changes: adding a variable or block is compatible; renaming or removing is
called out in the release notes and the old name is kept as an alias for one release. The
validator's "not used by this version" warning is how a theme learns a template it overrode has
moved.

### 3.7 Security

- Theme files are never web-served directly (`Include/` stays deny-all); only the allow-listed
  extensions come out through the asset route, only from real theme folders, never via `..`.
- A theme is uploaded by the church's administrator and trusted to the degree of anything else that
  administrator puts on the server; the authoring guide says so and says never to use `|raw` on
  member data. Twig autoescape covers everything else.
- `theme.js` runs under the page's CSP nonce; inline scripts in theme templates need
  `nonce="{{ nonce() }}"`.
- Themes are not sanctioned or verified by ChurchCRM, unlike community plugins; the admin page
  says "Themes are provided by your church, not by ChurchCRM" next to the list.

### 3.8 Church-supplied modules, one folder family

`Include/themes/` is the first of a family: `Include/themes/<name>/` (this epic),
`Include/modules/gateways/<name>/` (payment gateways, when the giving epic exists),
`Include/modules/<type>/<name>/` for other church-supplied module types. Themes never contain
PHP; modules that need code are a different thing with a different security model. All share the properties
above: survive updates, ignored by the orphan scan, never web-served directly, discovered by folder
name. Community plugins already live at `plugins/community/` by the maintainer's decision; this
design does not move them, and proposes in §8 that new module types follow the `Include/` pattern.

---

## 4. Admin → Member Portal page

`/admin/member-portal`, administrators only, one page with tabs:

**Settings** — theme (dropdown from `listThemes()`, "System
default" first, with a validation status badge per theme and a "Check" button that runs the
validator and lists findings), Developer mode (switch; disables the template cache and shows
template names in an HTML comment at the top of each page), section switches
(`bPortalShowCalendar`, `bPortalShowVolunteer`), `bPortalAllowBirthdayEdit`. Saved through
`POST /admin/api/system/config/{name}`; theme changes go through `POST /admin/api/member-portal/theme`
so activation can refuse.

**Statistics** — members who signed in during the last 24 hours, 7 days and 30 days (from
`usr_LastLogin`, self-service accounts only); members active in the last 15 minutes (from a new
`usr_LastPortalActivity` stamped by `PortalAccessMiddleware` at most once per five minutes per
session — cheap, and it makes "online now" honest); total self-service accounts; accounts that
have never signed in; the ten most recent sign-ins. Page-view counting is not in the first version.

**Themes** — the discovered folders with display name, author (from `theme.json` when present),
number of overridden templates, last validation result, and the note that themes are church-
provided and not verified by ChurchCRM.

Config items (all without a System Settings category): `sMemberPortalTheme`,
`bPortalDeveloperMode`, `bPortalShowCalendar`, `bPortalShowVolunteer`, `bPortalAllowBirthdayEdit`.

---

## 5. Screens and APIs

Navigation (the `nav` model), in order, each hidden when its feature is off or the member has
nothing there: **Home · Calendar · Volunteering · My Teams · My Family · Profile**. The header
shows the church logo and name, and one account menu: a button reading "Hello <first name>" over
**Email History** (§5.8), **Change Password**, **Admin Console** (staff logins only, never during a
masquerade) and **Sign out**.
The church name is not a link that restyles itself under the pointer. No admin sidebar anywhere.
Staff opening the portal leave it again through Admin Console (P10); there is no fixed "viewing as
yourself" bar. A masquerade still shows the banner from #9843, with its own exit control.

### 5.1 Home (`/portal`)

Cards: next portal-visible events, the member's next volunteer assignment and any pending
response, a family confirmation reminder when one is due, and the "Ministries looking for help"
teaser. Themes typically override this page first.

### 5.2 Profile (`/portal/profile`) and My Family (`/portal/family`)

- Read: own person and family (existing read routes suffice).
- Edit, own person: first/middle/last name, title/suffix, email, work email, home/cell/work phone,
  birthday (`bPortalAllowBirthdayEdit`), photo. The avatar upload route is scoped by
  `canEditPerson` but gated by `EditRecordsRoleAuthMiddleware`, which a self-service login never
  passes, so the portal has its own `POST /api/portal/me/photo` calling the same model method
  (`Person::setImageFromBase64()`) for the member's own record and nothing else.
- **Reading a photo is the portal's own route too.** `AuthMiddleware::isLimitedAccessAllowedPath()`
  confines a self-service session to `/portal` and `/api/portal`, so `GET /api/person/{id}/photo`
  answers a member with 403 and every avatar renders broken. `GET /api/portal/me/photo` and
  `GET /api/portal/family/members/{id}/photo` serve the same bytes through the same `Photo` object,
  privately cached for the same two hours, and every `photoUrl` the portal hands out points at
  them, cache-busted with `?v=<mtime>`. The family route is the one place a portal route takes a
  person id: it is checked against the members of the actor's own family (P12) and anything else is
  **404, never 403**, so a member cannot learn who exists outside their family.
  Edit, family: address, city, state, zip, country, home phone, email, wedding
  date — only for the family's adults (head/spouse), the same audience the verify flow addresses.
  Other members: view only; "add a family member" — also an adults-only action, because it writes
  into the family — creates a pending self-registration entry
  (`Person::SELF_REGISTER`) for staff review on the existing page rather than a live person. That
  entry sits in an existing, *not* self-registered family, a case the staff review page did not
  list before: `GET /api/persons/self-register` now excludes only the members of self-registered
  families instead of everybody who has a family at all.
- Every write: `POST /api/portal/me` and `POST /api/portal/family` with
  `InputSanitizationMiddleware` and a field allow-list; a timeline `Note` "edited via the Member
  Portal" is written so staff see who changed what. Dates (`birthday`, `weddingDate`) travel as
  ISO `YYYY-MM-DD` from `<input type="date">` and are sanitized as `text`, because the sanitizer
  has no `date` type yet (#9821); the service parses them strictly, so `2026-02-31` is refused
  rather than rolled forward.
- "Confirm your family details" (`/portal/family/confirm`): same outcome as `/external/verify`
  (a `verify` note, no changes / changes needed + comment), and offered to every member of the
  family, not only its adults — it records an opinion, it does not change a record. The note is
  written exactly as `/external/verify` writes it, `Person::SELF_VERIFY` and all, because the
  People → Verify dashboard selects on `EnteredBy = SELF_VERIFY`; the confirming member's own id
  is deliberately not used. The emailed verify-token link keeps working for people without logins.
- **A member with no family is not an error.** `/portal/family`, `/family/edit` and
  `/family/confirm` answer `family/none.html.twig` — HTTP 200, the normal portal chrome, "My
  Family" still the active nav entry — when the person has no family, and when the account has no
  person record at all. It says *"You are not currently associated with a family"*, asks the member
  to contact the church office, and offers **Admin → Church Information**'s email as a `mailto:`
  link and its phone as a `tel:` link (the `tel:` href keeps the digits and a leading `+`; the
  number is displayed as the church typed it). Either line is left out when its setting is empty,
  and with neither configured the page says only "Please contact the church office." The home
  page's My Family card says the same thing in one line and links here. The portal's 404 stays for
  URLs that really do not exist: "This page was not found" told a member their record was broken
  when it was only incomplete.
- **The home page's Profile card shows the values, not their names.** It reads the same
  `PortalSelfService::getProfile()` the Profile page renders and lists email, mobile, home phone,
  birthday (only while `bPortalAllowBirthdayEdit` is on, exactly as the Profile page gates it) and
  the family role (only for a member who has a family). Empty fields are skipped; with nothing on
  file the card says "No contact details on file yet."
- Password and two-factor: **the portal's own pages**, `GET/POST /portal/profile/password` and
  `GET /portal/profile/two-factor`, rendered in the portal layout for *every* role — member,
  staff, administrator, and during a masquerade. This is a product-owner decision (2026-09-17):
  leaving the portal is the "Admin Console" control's job and nothing else's, so an administrator
  who changes their password from the portal must not be dropped back into the admin shell.
  Delivered in MP4 (#9865) rather than MP3: MP3 is the admin page and never touches these routes.
  The pages act on the signed-in *account*, not on a person record, so an account with no person
  linked can still change its password. `PortalAccountPages` is the single place a portal account
  page becomes a response; the change itself is `User::userChangePassword()`, the field names, the
  form id, the CSRF form id and the `PasswordChange.js` / `two-factor-enrollment` bundles are
  unchanged, so each flow still has one implementation.

  The older `/v2/user/current/changepassword` and `/v2/user/current/manage2fa` keep their
  behaviour unchanged and render through the same `PortalAccountPages`: they are what
  `LocalAuthentication` returns as `nextStepURL` for a forced first-login password change or a
  required 2FA enrollment, and what `AuthMiddleware::isLimitedAccessAllowedPath()` exempts by
  name. They deliberately do **not** redirect to the portal URLs: the forced flows break out of
  their own redirect loop by matching `/v2/user/current/changepassword` against `REQUEST_URI`, so
  a redirect would bounce the browser between the two paths forever. The password template's form
  target is a variable (`formAction`) so each route posts back to itself; it is never taken from
  the request, so there is no redirect for an attacker to steer.

### 5.3 Calendar (`/portal/calendar`)

**How calendars work today (facts).** The `calendars` table holds church-wide calendars: name,
colours, optional public access token. There is no owner column; the admin page's "My Calendars"
heading is a misnomer — "New Calendar" creates a calendar every staff user with View Events sees,
and anyone with Add Events may create one. Every event must be pinned to at least one calendar
(`PinnedCalendars` is required on create). The "System Calendars" (Birthdays, Anniversaries,
Unpinned events, and Holidays from the core plugin) are virtual: computed on the fly by
`SystemCalendars`, not rows, extensible by plugins through `Hooks::SYSTEM_CALENDARS_REGISTER`.
Volunteer v2 gave events an optional owning ministry (`event_ministry_id`, D9) so a coordinator may
create events, but a ministry has no calendar; a coordinator creating "Car Wash" for Youth Ministry
must pin it to some existing church calendar.

**Decisions.**

- **Portal visibility is chosen per calendar on the Admin → Member Portal page**, Calendars tab:
  one list with every church calendar and every system calendar, each with a "Show in Member
  Portal" switch. Stored as `aPortalCalendars`, a JSON config value of calendar ids and system
  calendar ids (system calendars are virtual, so a column on `calendars` cannot cover them). All
  off by default after an upgrade; on a new install the "Events" church calendar is on and the
  system calendars are off. Birthdays and Anniversaries are a privacy call the administrator makes
  knowingly; when shown, the portal renders first name and last initial only, never ages or years.
- **Every volunteer ministry gets a calendar.** `calendars.ministry_id` (nullable, FK to the
  ministry, `ON DELETE SET NULL`) — the same pattern as the ministry's Group (D19) and the
  ministry's events (D9). Created with the ministry, named after it, renamed and deleted with it.
  Its coordinators may pin their ministry's events to it without Add Events (the calendar
  middleware gains the same "coordinator of the owning ministry" exception the Group hooks got).
  When a coordinator creates an event with a ministry, the event editor pre-pins that ministry's
  calendar. Administrators decide per ministry calendar whether the portal shows it, like any
  other. The admin calendar page lists ministry calendars under their own heading, "Ministry
  Calendars", between church and system calendars.
- **Rename "My Calendars" to "Church Calendars"** on the admin calendar page (label only; a
  one-line upstream fix filed as its own issue, since the current label misleads).
- Data: `GET /api/portal/calendar/events?from&to` = events of the portal-visible calendars for the
  window, union of church, ministry and system sources, shaped by the same code
  `PublicCalendarMiddleware` and `SystemCalendars` use, in the configured timezone. Session-gated,
  no token in the URL.
- UI: FullCalendar from the existing `external-calendar` bundle, month/agenda/list, read-only, one
  colour per calendar as on the admin page; event click opens a detail panel (title, when, location,
  description, ministry name when set). A member who leads a team sees their ministry's calendar
  highlighted.
- **Subscribing.** A member may take the calendar with them. The calendar page
  carries a **Subscribe** button in the top-right of the title row; it opens a
  dialog with one checkbox per calendar the administrator shares, and saving
  hands back a single address — `(Church name) Calendar` — that feeds exactly
  the calendars ticked. The address is
  `/api/public/portal-calendar/{token}/calendar.ics`: no session, because a
  calendar app cannot sign in, so the token *is* the credential. It is 32
  random bytes as hex, minted on the first save and rotated by **Reset link**,
  which retires the old address at once. What the feed serves is always the
  member's selection **intersected with the calendars that are shared right
  now**, so un-sharing a calendar removes it from every member's feed on the
  next fetch without anybody re-saving anything; nothing but events is
  reachable through the token, the privacy rewriting above still applies
  (a birthday reads "Lena B."), and an unknown token is a bare 404 that cannot
  be told apart from an account with no feed. The document is RFC 5545, three
  months back to eighteen months ahead, `VALUE=DATE` for the whole-day and
  virtual events and UTC instants for timed ones, `CATEGORIES` naming the
  calendar an event came from. The per-calendar public ICS
  (`/api/public/calendar/{token}/ics`) is untouched; it cannot express virtual
  events, which is why the portal has its own builder
  (`ChurchCRM\Portal\PortalCalendarFeed`).
- Not in scope: volunteer schedule occurrences that are not linked to an event do not appear on
  calendars (they never have); "My Volunteer Schedule" is where those live.

**Scope split between this epic and the Volunteer v2 branch (added with MP5, #9866).** The
ministry-calendar half of this section needs `volunteer_ministry_vmin`, which the Volunteer v2
schema creates. The Member Portal epic lands first, so #9866 ships only what stands without that
table, and the rest is an MP7 follow-up on the volunteer branch:

| Piece | Where it lands |
|---|---|
| `calendars.ministry_id INT NULL` with an index, **no** foreign key | MP5 (#9866) |
| "Ministry Calendars" heading on the admin calendar page, shown only when some calendar has a ministry | MP5 (#9866) |
| "Church Calendars" relabel | MP5 (#9866) |
| `aPortalCalendars`, the Calendars tab, `/portal/calendar`, `GET /api/portal/calendar/events` | MP5 (#9866) |
| The foreign key to `volunteer_ministry_vmin`, `ON DELETE SET NULL` | Volunteer v2 schema, once its table exists |
| Creating, renaming and deleting a ministry's calendar with the ministry | Volunteer v2 (#9701) |
| The calendar middleware's "coordinator of the owning ministry may pin without Add Events" exception | Volunteer v2 (MP7 follow-up) |
| The event editor pre-pinning a ministry's calendar when the event has a ministry | Volunteer v2 (MP7 follow-up) |
| "A member who leads a team sees their ministry's calendar highlighted" | Volunteer v2 (MP7 follow-up), since it needs team leadership, which reaches self-service logins in MP6 |

Two details the implementation settled that this section left open. `aPortalCalendars` entries are
`{"type": "calendar"|"system", "id": <int>}` — the kind has to be in the entry because a church
calendar id and a system calendar id are both small integers from different spaces. And the events
endpoint takes plain `YYYY-MM-DD` days rather than instants, refuses a reversed range with a 400,
and clamps the window to 62 days (the system calendars expand a row per person per year), echoing
the window it actually answered.

### 5.4 Volunteering (`/portal/volunteer/schedule`, `/portal/volunteer/opportunities`)

The two pages move from `/volunteer/my-schedule` and `/volunteer/opportunities`. What changes and
what does not:

| Piece | Today | In the portal |
|---|---|---|
| Route file | `src/volunteer/routes/member.php` | `src/portal/routes/volunteer.php` |
| Page chrome | PHP views requiring `Include/Header.php` (admin shell) | Twig templates `volunteer/schedule.html.twig`, `volunteer/opportunities.html.twig` extending the portal layout, providing the same container ids |
| Rendering logic | `webpack/volunteer/my-schedule.ts`, `opportunities.ts`, `member-ui.ts` | **unchanged**; the templates load the same two bundles |
| API | `/api/ministries/me/*` | **unchanged** |
| Old URLs | — | 302 to the new ones for one release |
| Admin sidebar | "Volunteer" heading with the two items | **removed** (P16) |

The "Ministries looking for help" section and "I'd like to help" are unchanged.

**As built (MP6, #9867).** The nav carries one entry, **Volunteering**, pointing at the schedule
page; the two pages carry a secondary tab bar between them (*My schedule* / *Find something to do*),
because two sidebar entries do not survive the move to a six-item top nav. The entry and both
routes ask one predicate, `PortalNav::isVolunteeringVisible()` — `User::isVolunteerV2Enabled()` and
`bPortalShowVolunteer`, the latter read defensively because MP3 is what declares it — so the portal
never offers a page it would then refuse. The home page's "My volunteering" card is real: the
member's next live assignment and a count of the ones still waiting for an answer, read
client-side from `/api/ministries/me/assignments` by `portal.min.js`, best-effort and silent on
failure. The layout also gained `bootbox` (the volunteer pages' prompts and confirmations) and
`portal.min.js` now defines `window.CRM.escapeHtml` when the admin shell's `CRMJSOM.js` has not —
without it the reused bundles would insert unescaped names into the DOM.

### 5.5 My Teams (`/portal/teams`, `/portal/teams/{teamId}`, `/portal/teams/{teamId}/occurrences/{id}`)

For team leaders (and staff who lead a team, when they open the portal). The team page is the
ministry page's Positions, Volunteers, Schedules and Occurrences tabs narrowed to that team,
rendered in the portal layout by `webpack/portal/teams.ts`, which reuses the volunteer API client
and the components refactored out of `ministry.ts` into shared modules (qualification matrix,
staffing needs, occurrence list). Not shown: Teams card, Ministry Coordinators, Help Wanted,
Remove Volunteer, team delete/rename. Team leaders may create, edit and generate schedules for
their own team (P17; the volunteer route gate on schedule creation becomes team-scoped). The
occurrence page in the portal uses the occurrence components with the same authorization the admin
occurrence page applies per occurrence.

**As built (#9868).** Five things came out differently from the sketch above, and are recorded
here rather than left for the next reader to rediscover:

1. **There is no `/api/portal/teams/*` namespace.** The sketch assumed one. The index page turned
   out to need no API at all — it is a short list of plain reads, so it is server-rendered from
   `ChurchCRM\Portal\PortalTeams` — and everything the other two pages need is already a volunteer
   endpoint that authorizes per record. Adding a portal-shaped copy of fifteen of those would have
   been a second surface to keep in step with the first. Two **team-keyed twins** were added to
   `/api/ministries` instead, because only the ministry-keyed versions existed:
   `GET /volunteer/teams/{id}/qualification-matrix` and `GET /volunteer/teams/{id}/schedules`,
   both behind `VolunteerTeamMiddleware`.
2. **`AuthMiddleware::isLimitedAccessAllowedPath()` had to widen.** It confined a self-service
   login to `/portal`, the auth-flow pages and `/api/ministries/me/`; a member-login team leader
   now also reaches `/api/ministries/`. `/volunteer` — the admin MVC area — is deliberately still
   not on that list, so the admin shell stays shut to them, which is what P10 requires.
3. **`VolunteerCoordinatorRoleAuthMiddleware` had to widen too**, to
   `isVolunteerCoordinatorEnabled() || isVolunteerTeamLeaderEnabled()`. The User predicate itself
   is unchanged — it still returns false for a self-service login, so `Menu::buildMenuItems()`
   keeps the Ministries heading hidden, which is the reason the short-circuit exists.
4. **Nothing had to be extracted from `occurrence.ts`.** It was already page-agnostic: it names no
   ministry, reads its world from `window.CRM.volunteerOccurrence` and `/api/ministries/occurrences/*`,
   and addresses its markup by id. `teams/occurrence.html.twig` reproduces those ids and loads
   `volunteer-occurrence.min.js` unchanged — the same move P15 made for the two member pages. The
   one difference is that the linked calendar event is NAMED but not LINKED, because
   `/event/view/{id}` is an admin-shell page a member would be bounced away from.
5. **The nav entry is narrower than the routes.** `My Teams` is shown only to somebody with an
   explicit `team` grant (`isVolunteerTeamLeaderEnabled()`), while `/portal/teams` also admits a
   coordinator, manager or administrator who opens the portal as themselves. A coordinator is not
   a team leader (volunteer design §4.4) and their way into a team is the ministry page; claiming
   otherwise in their navigation would be wrong, and refusing them the page would be pointless.

The components the team page shares with the admin ministry page live in
`webpack/volunteer/components/` — `ui.ts` (the §5.8 state machine, the modal fade guard, the
DataTables and row-menu helpers), `positions-table.ts`, `qualification-matrix.ts`,
`schedules-table.ts`, `occurrences-table.ts` — and take a context object rather than reading module
state. Controls a caller does not want are omitted from ITS markup; every component looks its
controls up by id and is inert when one is absent.

Because the portal loads no part of the admin shell, `window.CRM.escapeHtml`, `escapeAttribute`
and `buildActionMenu` — `CRMJSOM.js`'s, which those components use — are installed by
`webpack/common/crm-helpers.ts` when absent, and DataTables is simply not there, so the portal's
tables are plain tables.

### 5.6 Directory (phase 2)

#8977 asks for member-scoped contact search. It needs a per-person opt-in and a rule for
families. Deferred; the nav slot is reserved.

### 5.7 Giving (future version, out of scope here)

The portal is where a member's contribution history and online giving will live. Neither is in
this version: the codebase has no payment gateway, no online-giving model and no payment flow, and
those need their own design (gateway modules under `Include/modules/gateways/`, provider
interface, webhook-to-payment recording, receipts, statements). The nav reserves the "Giving" slot;
nothing renders until that epic ships.

### 5.8 Email History (`/portal/email-history`, `/portal/email-history/{id}`)

What the church has emailed this member, newest first and paginated. Product-owner request,
2026-09-17: *"so the member can see a record of what the church emailed them, newest to oldest and
paginated."*

**Depends on two unreleased pieces**, and cannot ship before them: the email log #9877
(`email_log_eml`, `EmailLogService`) is where the rows come from, and the server-side composer send
#9876 is what puts most of them there. The portal epic's branch carries a merge of #9877, which
carries #9876.

- **Reached from the account menu, not the nav.** It is a record of the account rather than a place
  a member works, and the nav is already at its width on a phone. `PortalNav` is handed no active id
  for these two pages, which it renders as "nothing is active" — the behaviour an unknown id
  already had.
- **List** (`email/index.html.twig`): Date, Type (`EmailLogService::kindLabel()`), Subject (a link
  to the detail page; "(no subject)" when the send carried none) and Status. Sent is quiet; Failed
  and Skipped carry the palette's `--portal-danger` / `--portal-warning`, because those are the two
  a member needs to notice. `EmailLogService::DEFAULT_PAGE_SIZE` per page, Previous / Next and
  "Page X of Y" driven by `?page=`. Server-rendered from the service — no bundle, no fetch. Empty
  state: "The church has not emailed you yet." One `<table>` at every width, unrolled into stacked
  blocks on a phone by `_portal.scss` (the `data-label` pattern).
- **Detail** (`email/show.html.twig`): subject as the title, then date, type, status and the address
  it went to, then the message. **The stored body is rendered in an `<iframe sandbox srcdoc="…">`
  and nowhere else** — an empty `sandbox` is its own opaque origin with no scripts, no forms and no
  top-level navigation, and Twig's autoescaping is what fills the attribute safely. The frame keeps
  a fixed height and scrolls: an opaque-origin document cannot be measured from the page, and both
  ways to measure it (`allow-same-origin`, or a script inside the frame) are permissions this page
  must not grant. An account email, whose body is deliberately never stored, says "The content of
  this email was not kept."
- **API**: `GET /api/portal/me/emails?page=&limit=` and `GET /api/portal/me/emails/{id}`, session
  only, actor from the session like every other portal route (P11). A row that is not this person's
  and an id that does not exist are the same **404**, never a 403 — the treatment P12 already gives
  a person id outside the member's family, for the same reason: a member must not be able to learn
  that a record exists by asking for it.
- **Scope decision, open for the product owner.** Only rows whose `eml_per_ID` is this person are
  shown. Mail addressed to the **family's shared address** lands with `eml_per_ID` NULL and
  `eml_fam_ID` set, and is deliberately left out of this first version: showing it would put mail
  nobody was named on in front of every adult of the household, which is a privacy call rather than
  an implementation detail. `EmailLogService::getForFamily()` is what a later version would call if
  the answer is "show it" — the staff family page already does.
- Not in scope: re-sending, deleting, marking read, or any write at all. The page is a record.

---

## 6. Data changes

| Change | File |
|---|---|
| `calendars.ministry_id INT NULL` with an index (ministry calendars, §5.3). The FK → `volunteer_ministry_vmin` `ON DELETE SET NULL` is added by the Volunteer v2 schema, which creates that table | `7.8.0-member-portal-calendars.sql`, `Install.sql`, seed, `orm/schema.xml` |
| `aPortalCalendars` JSON config (portal-visible calendar and system-calendar ids) | `SystemConfig.php` |
| `user_usr.usr_LastPortalActivity DATETIME NULL` | `7.8.0-member-portal-activity.sql`, same set |
| `user_usr.usr_PortalCalendarToken VARCHAR(64) NULL` with a UNIQUE index — the bearer secret in a member's calendar feed URL; NULL means no feed (§5.3, "Subscribing") | `7.8.0-member-portal-activity.sql`, `Install.sql`, seed, `orm/schema.xml` |
| `user_usr.usr_PortalCalendarSelection TEXT NULL` — JSON array of the calendar ids the member ticked, always intersected with what is shared before a feed is built | same set |
| Config items in §4 (no System Settings category) | `SystemConfig.php` |
| `AppIntegrityService::isExcludedFromOrphanDetection` and `generate-signatures-node.js` gain `Include/themes/` (and `Include/modules/`) | core |
| `.gitignore`: `src/Include/themes/*` except `default` | core |

---

## 7. Impact on the Volunteer v2 epic

Because this epic lands first, the volunteer integration branch changes before its PRs open:

1. `src/volunteer/routes/member.php` and the two member views are removed; the pages live in the
   portal (§5.4). The volunteer entries in `isLimitedAccessAllowedPath()` go with them.
2. The admin sidebar's "Volunteer" heading is removed; "Ministries" stays (P16).
3. D14 revised (P17): `loadScopes()` drops its `isEditSelfExclusive()` short-circuit;
   `User::isVolunteerTeamLeaderEnabled()` is added; `isVolunteerCoordinatorEnabled()` keeps
   returning false for self-service accounts so the admin dashboard stays closed to them; the
   "silent grant" production-readiness note is deleted; §4.6 of the volunteer design gains the
   team-leader schedule row.
4. The qualification matrix, staffing-needs editor and occurrence list are refactored into shared
   modules so the portal team page can reuse them (no behaviour change on the admin page).
5. `/external/limited-access` is retired (route redirects to `/portal`); its template and the
   volunteer button on it are deleted.
6. `VolunteerSetupService::createMinistry()` also creates the ministry's calendar
   (`calendars.ministry_id`), renames and deletes it with the ministry, alongside the team and the
   pool Group it already creates; the volunteer design's D19 row gains the calendar (§5.3).
7. The UI review checklist and the docs issue for the volunteer epic are updated to the new URLs.

Everything else in the volunteer epic (admin ministry page, dashboard, occurrences, notifications,
Help wanted, recruiting, masquerade) is untouched.

---

## 8. Points to raise with the maintainer (on #8977)

1. Twig as a second page renderer next to `PhpRenderer`, limited to the portal, because it is the
   only way to let churches override templates safely; email already uses it.
2. `Include/themes/` as the update-safe home for church themes, with the orphan-scan exemption and
   the application-served asset route; and `Include/modules/<type>/` as the pattern for future
   church-supplied module types (payment gateways first).
3. A dedicated Admin → Member Portal page for its settings and statistics rather than new System
   Settings items.
4. Removing the admin sidebar's "Volunteer" heading once the portal exists (member functionality
   only in the portal).

---

## 9. Delivery plan

Child issues under the epic, in build order (each its own branch and PR from master, 7.8.0
migrations unregistered until the block opens, Cypress spec proven to fail first, docs sibling
issue for every user-visible piece):

| # | Issue | Depends on |
|---|---|---|
| MP1 | Design doc PR (this document into `.agents/skills/churchcrm/member-portal-design.md`) | — |
| MP2 | Module skeleton, `PortalAccessMiddleware`, Twig environment, `ThemeManager` + validator, asset route, default theme with layout/nav/home/error pages, landing rule, orphan-scan exemption, theme authoring guide | MP1 |
| MP3 | Admin → Member Portal page (settings, themes, statistics, `usr_LastPortalActivity`) | MP2 |
| MP4 | Profile and My Family (read, edit allow-lists, confirm-details flow, photo, portal-aware password/2FA pages) | MP2 |
| MP5 | Calendar: Calendars tab on the admin page (`aPortalCalendars`), ministry calendars (`calendars.ministry_id`, created with the ministry, coordinator pinning, "Ministry Calendars" heading, event editor pre-pin), "Church Calendars" relabel, portal page and API | MP2, MP3 |
| MP6 | Volunteer pages moved into the portal; admin "Volunteer" heading removed; D14 revision; team-leader flag | MP2 + volunteer integration branch |
| MP7 | My Teams (team-scoped management in the portal; shared components refactor; team-leader schedules) | MP6 |
| MP8 | Masquerade banner in the portal layout (the staff "viewing as yourself" bar it was to unify with is gone; the account menu's Admin Console replaced it); admin user-menu link; limited-access retirement; e2e, localization, responsive and production-readiness pass | MP2–MP7 |
| MP9 | UCCC theme (its own repository, not upstream): colours, fonts, imagery, home page override | MP2 |

MP2 is the largest (theming infrastructure); MP3–MP5 are each about the size of one volunteer child
issue; MP6–MP7 are mostly moves and refactors on existing code.

---

## Appendix A — Minimal church theme

```
Include/themes/uccc/
  theme.css        ← the token block from §3.5 and nothing else
```

That alone recolours and refonts every portal page. Adding `templates/home.html.twig` that
`{% extends "@default/home.html.twig" %}` and overrides `{% block hero %}` puts the church's
photo and welcome text on the home page with twelve lines of markup. Upload both over FTP, pick the
theme on Admin → Member Portal, reload.
