# Member Portal — the template contract

Everything a Member Portal template may rely on. Read this next to
[`portal-themes.md`](./portal-themes.md), which explains how a theme is put
together; this file is the reference for what is inside a template.

Templates are [Twig 3](https://twig.symfony.com/). They are rendered with
`autoescape: 'html'` — every variable you print is escaped for you — and with
`strict_variables: false`, so reading a variable that does not exist yields
nothing rather than an error.

## Where templates are resolved from

The loader looks in two places, in order:

1. `Include/themes/<active theme>/templates/`
2. `Include/themes/default/templates/`

The first hit wins, so a theme file replaces the core file at the same relative
path. The namespace `@default` always addresses the second list, which is how an
override extends the very template it replaces:

```twig
{% extends "@default/home.html.twig" %}
```

---

## The page shell

Every page extends `layout.html.twig`, which a theme may also override.

### Blocks

| Block | What goes in it | Filled by default |
|---|---|---|
| `head_extra` | Extra `<link>` / `<meta>` / `<style>` for this page | empty |
| `header` | The church identity bar | `partials/header.html.twig` |
| `nav` | The portal navigation | `partials/nav.html.twig` |
| `hero` | A full-width band under the navigation | empty |
| `content` | **The page itself.** Every page template fills this. | empty |
| `sidebar` | An optional column beside the content | empty |
| `footer` | The church contact line and the ChurchCRM credit | `partials/footer.html.twig` |
| `scripts_extra` | Extra `<script>` for this page — with `nonce="{{ nonce() }}"` | empty |

### Partials

Each is a separate file, so a theme can replace one without touching the others:

| Partial | Renders |
|---|---|
| `partials/header.html.twig` | Church logo and name, the member's name, Sign out |
| `partials/nav.html.twig` | The `nav` entries, and the toggle target `#portal-nav` |
| `partials/footer.html.twig` | Church contact details, ChurchCRM credit |
| `partials/flash.html.twig` | The `flash` messages for this request |

### What the layout already loads

You do not need to add any of it, and you should not load a second copy:

- ChurchCRM's core CSS and JS bundle (Tabler, Bootstrap 5, jQuery), the RTL
  variant when the locale is right-to-left
- `moment`
- `portal.min.css`, then the active theme's `theme.css` when it has one
- `i18next` and the locale loader, then `portal.min.js`, then the active theme's
  `theme.js` when it has one
- whatever `<head>` and footer HTML the enabled plugins inject

### Page IDs and classes worth knowing

| Selector | What it is |
|---|---|
| `.portal-body` | `<body>`; also `.portal-body-with-bar` when the staff bar is showing |
| `.portal-staff-bar` | The fixed "You are viewing the Member Portal as yourself." bar |
| `.portal-shell` | The column that holds header, nav, main and footer |
| `.portal-container` | The width-limited wrapper used by every band |
| `#portal-nav` / `#portal-nav-toggle` | The navigation and the button that opens it on a phone |
| `#portal-main` | The `<main>` element |
| `.portal-card` | The standard content card |
| `.portal-page-title` | The `<h1>` at the top of a page's content |
| `.portal-detail-list` | A `<dl>` of label/value pairs — one column on a phone, two from the tablet breakpoint up |
| `.portal-form` / `.portal-field` / `.portal-input` | A self-service form, one of its fields, and the control inside it |
| `.portal-field-error` | The inline message for a field; it is hidden while empty |
| `.portal-button` / `.portal-button-quiet` | The primary and secondary action buttons |
| `.portal-avatar` / `.portal-avatar-initials` | A member's photo, and the initials shown when there is none |
| `.portal-member-list` / `.portal-member` | The family members list; the member's own row also carries `.is-self` |
| `.portal-dialog` | A `<dialog>` in the portal's own chrome |

### Hooks the page bundles look for

`portal-profile.min.js` and `portal-family.min.js` bind to these ids, so a theme
that overrides one of those pages must keep them if it wants the page to work:

| Id | On |
|---|---|
| `portal-profile-form`, `portal-profile-save` | `profile/edit.html.twig` |
| `portal-photo-input`, `portal-photo-preview`, `portal-photo-error` | `profile/edit.html.twig` |
| `portal-family-form`, `portal-family-save` | `family/edit.html.twig` |
| `portal-confirm-form`, `portal-confirm-submit`, `portal-confirm-comment`, `portal-confirm-comment-field` | `family/confirm.html.twig` |
| `portal-add-member-dialog`, `portal-add-member-form`, `portal-add-member-open`, `portal-add-member-submit`, `portal-add-member-cancel` | `family/index.html.twig` |

A field's inline message is the element with `data-error-for="<field name>"`
inside the form; a read-only value the bundle refreshes after a save is the
element with `data-field="<field name>"`.

---

## Functions

These, and only these, are callable from a portal template. There is no PHP, no
filesystem access, no database access and no session.

| Function | Returns |
|---|---|
| `url(path)` | A path inside this installation, with the install's root path in front: `url('/portal/profile')` |
| `asset(path)` | A core ChurchCRM asset URL, cache-busted: `asset('/skin/v2/portal.min.js')` |
| `theme_asset(path)` | A file from the active theme, cache-busted: `theme_asset('images/hero.jpg')`. If the active theme does not carry the file but the system theme does, you get the system theme's copy. |
| `csrf_field()` | The hidden input every portal form must contain. Safe HTML — do not escape it. |
| `nonce()` | This request's Content-Security-Policy nonce. Every inline `<script>` needs `nonce="{{ nonce() }}"`. |
| `gettext(text)` | The translation of `text` |
| `ngettext(singular, plural, count)` | The translation of `singular`/`plural` for `count` |

---

## Globals

Available in every template, including yours.

### `church`

The church's identity, from **Admin → Church Information**.

| Field | Type | Notes |
|---|---|---|
| `name` | string | May be empty on a brand-new install |
| `address`, `city`, `state`, `zip` | string | |
| `phone`, `email` | string | |
| `website` | string | |
| `logoUrl` | string | The uploaded church logo, or ChurchCRM's stock image when none is set |

### `member`

The signed-in person. Always the session's own person — no template, and no
portal route, ever addresses somebody else.

| Field | Type | Notes |
|---|---|---|
| `id` | int | The person id |
| `firstName`, `lastName` | string | |
| `fullName` | string | Formatted per the installation's name format |
| `familyName` | string | The family's surname; empty when the person has no family |
| `email` | string | |
| `avatarUrl` | string | `/api/portal/me/photo`, cache-busted; **empty when no photo has been uploaded** — render initials instead |
| `familyId` | int | `0` when the person has no family |
| `isTeamLeader` | bool | Reserved; always `false` until the volunteer pages move into the portal |
| `isStaff` | bool | `true` for a login that also has the admin shell — the layout shows the "viewing as yourself" bar for it |

### `nav`

An ordered list of entries. Render all of them; the list is already filtered to
what this member may see.

| Field | Type | Notes |
|---|---|---|
| `id` | string | Stable identifier, e.g. `home` |
| `label` | string | Already translated |
| `url` | string | Ready to use in `href` |
| `icon` | string | A Font Awesome class, e.g. `fa-solid fa-house` |
| `active` | bool | `true` for the page being rendered |
| `badge` | string | A short count or flag; empty when there is nothing to show |

### `flash`

One-shot messages queued by the previous request. Reading them empties the list,
so a reload never repeats them.

| Field | Type | Notes |
|---|---|---|
| `type` | string | `info`, `success`, `warning` or `danger` |
| `message` | string | Already translated |

### `portal`

Facts about this request and this installation.

| Field | Type | Notes |
|---|---|---|
| `rootPath` | string | The install's root path — `''` at a domain root, `/churchcrm` in a subdirectory |
| `themeName` | string | The active theme's folder name |
| `locale` | string | e.g. `en_US` |
| `isRTL` | bool | Right-to-left locale |
| `colorMode` | string | `auto`, `light` or `dark` — the member's own choice |
| `impersonating` | bool | `true` while an administrator is signed in as this member |
| `developerMode` | bool | `true` when the template cache is off |
| `hasThemeCss` | bool | Whether a `theme.css` is available to link |
| `hasThemeJs` | bool | Whether a `theme.js` is available to load |
| `pluginHead` | HTML | The `<head>` content enabled plugins inject — print it verbatim |
| `pluginFooter` | HTML | The footer content enabled plugins inject — print it verbatim |
| `bootstrapJson` | JSON | The `window.CRM` seed the core bundles expect |
| `localeConfigJson` | JSON | The argument for `window.CRM.loadLocaleFiles(…)` |

The last four are pre-rendered fragments the layout emits as-is. Print them
without `|escape` and without `|raw` — they are already marked safe. If you
override `layout.html.twig`, keep emitting all four, or plugins and translations
will stop working on your pages.

---

## Page templates

| Template | Rendered for | Its own variables |
|---|---|---|
| `home.html.twig` | `GET /portal` | `pageTitle`, `upcomingEvents`, `hasVisibleCalendars`, `familySummary` |
| `calendar/index.html.twig` | `GET /portal/calendar` | `pageTitle`, `calendars`, `hasCalendars`, `calendarConfigJson` |
| `profile/index.html.twig` | `GET /portal/profile` | `pageTitle`, `profile` |
| `profile/edit.html.twig` | `GET /portal/profile/edit` | `pageTitle`, `profile` |
| `profile/password.html.twig` | `GET/POST /v2/user/current/changepassword`, self-service session | `pageTitle`, `minPasswordLength`, `oldPasswordError`, `newPasswordError` |
| `profile/password-changed.html.twig` | The same route, after a successful change | `pageTitle` |
| `profile/two-factor.html.twig` | `GET /v2/user/current/manage2fa`, self-service session | `pageTitle` |
| `family/index.html.twig` | `GET /portal/family` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm`, `familyRoles`, `defaultNewMemberRoleId` |
| `family/edit.html.twig` | `GET /portal/family/edit` | `pageTitle`, `family`, `members`, `canEdit`, `countries` |
| `family/confirm.html.twig` | `GET /portal/family/confirm` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm` |
| `errors/403.html.twig` | A page this member may not open | `pageTitle` |
| `errors/404.html.twig` | An unknown portal URL (and 405) | `pageTitle` |
| `errors/500.html.twig` | An unexpected failure | `pageTitle` |
| `errors/unavailable.html.twig` | Shown to **members** when the active theme fails to render | none |
| `errors/theme-error.html.twig` | Shown to **administrators** when the active theme fails to render | `themeName`, `file`, `line`, `message` |

`pageTitle` is the page's own title; the layout puts the church's name after it.

### `profile`

The signed-in member's own person record, as `GET /api/portal/me` returns it.

| Field | Type | Notes |
|---|---|---|
| `id` | int | The person id |
| `title`, `firstName`, `middleName`, `lastName`, `suffix` | string | |
| `email`, `workEmail` | string | |
| `homePhone`, `cellPhone`, `workPhone` | string | |
| `birthday` | string | ISO `YYYY-MM-DD`, empty when unknown |
| `fullName` | string | Formatted per the installation's name format |
| `familyRole` | string | e.g. `Spouse`; `Unassigned` when the person has no role |
| `familyId` | int | `0` when the person has no family |
| `familyName` | string | The family's surname |
| `photoUrl` | string | `/api/portal/me/photo`, cache-busted; **empty when no photo has been uploaded** — render initials instead |
| `hasPhoto` | bool | Whether a photo has been uploaded |
| `canEditBirthday` | bool | Mirrors `bPortalAllowBirthdayEdit`; when false the birthday is neither shown nor accepted |

### `family`

The member's own family. Never another family: the routes take no family id.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `name` | string | The family's surname |
| `address1`, `address2`, `city`, `state`, `zip`, `country` | string | |
| `homePhone`, `email` | string | |
| `weddingDate` | string | ISO `YYYY-MM-DD`, empty when unset |

### `members`

Everyone in the member's family, adults first, as `Family::getPeopleSorted()`
orders them.

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `fullName` | string | |
| `role` | string | The family-role name |
| `email`, `cellPhone` | string | |
| `photoUrl` | string | `/api/portal/family/members/{id}/photo` (or `/api/portal/me/photo` for the member's own row), cache-busted; empty when that member has no photo |
| `initials` | string | The two-letter stand-in for a missing photo |
| `isSelf` | bool | `true` for the signed-in member's own row |
| `isAdult` | bool | `true` for a head or spouse — the roles `sDirRoleHead` and `sDirRoleSpouse` name |

### `canEdit`, `canConfirm`, `countries`, `familyRoles`, `familySummary`

| Variable | Type | Notes |
|---|---|---|
| `canEdit` | bool | Whether this member may change the family's details — true only for an adult of the family |
| `canConfirm` | bool | Whether the "Confirm your family details" card is offered |
| `countries` | map | Country code → country name, for the address form's `<select>` |
| `familyRoles` | list | `{id, name}` for each family role, for the "add a family member" form |
| `defaultNewMemberRoleId` | int | The role that form starts on — the configured child role, not head of household |
| `familySummary` | object | On the home page only: `{name, memberCount}`, or `null` when the member has no family |

The two theme-failure pages are always rendered from the **system** theme, so a
broken theme cannot break the page that reports it. A theme may still override
them — its version is used everywhere except when that theme is the one that
failed.

More pages arrive with the rest of the epic: volunteering (MP6), teams (MP7).
Each one adds a row to this table.

### The home page's calendar card

`upcomingEvents` is the next three events from the calendars the church shares,
already formatted for printing — `{title, when, calendarName}`, where `when` is
a date string in the installation's own format. It is empty when nothing is
coming up, and `hasVisibleCalendars` is `false` when the church has shared no
calendar at all; the two cases read differently to a member, so the system
theme says "Nothing is on the calendar just now." for the first and "No calendar
has been shared with members yet." for the second.

The card is only rendered when `portal.showCalendar` is on.

### The calendar page

`calendars` is the legend: `{name, color}` for each calendar an administrator
switched on, in the order Admin → Member Portal → Calendars lists them, with
`color` a CSS colour ready for a swatch. `hasCalendars` is `false` when nothing
is shared, and the page then says so instead of drawing a grid.

The events are **not** in the template. FullCalendar fetches
`GET /api/portal/calendar/events?from=…&to=…` for the window it is showing, so
paging through months costs one small request each. An override must therefore
keep three things, which the page's bundle looks for:

| Keep | Why |
|---|---|
| `<div id="portal-calendar">` | Where FullCalendar renders |
| `<section id="portal-calendar-detail">` and its `portal-calendar-detail-*` ids | The panel an event click fills in — title, when, where, calendar, details |
| `window.CRM.portalCalendar = {{ calendarConfigJson }}` plus `asset('/skin/v2/portal-calendar.min.js')` | The endpoint, the church's timezone and the window cap |

`calendarConfigJson` is a pre-rendered fragment like the four above: print it
inside a `<script nonce="{{ nonce() }}">` without `|escape` and without `|raw`.
Link `asset('/skin/v2/portal-calendar.min.css')` from `head_extra` — that is
FullCalendar's own stylesheet, not portal styling.

A theme that would rather draw its own calendar can drop all of this and call
the same endpoint itself; it is session-gated and needs no token.

---

## Compatibility

Core may **add** a block, a variable or a global at any time; that never breaks a
theme. Renaming or removing one is called out in the release notes, and the old
name keeps working for one release.

The validator's "this version of ChurchCRM does not use a template at this path"
warning is how a theme finds out that a template it overrode has moved — run
**Check** on **Admin → Member Portal** after upgrading.
