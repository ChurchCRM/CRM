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
| `partials/header.html.twig` | Church logo and name, and the account menu ("Hello &lt;first name&gt;" → Change Password, Admin Console for staff, Sign out) |
| `partials/nav.html.twig` | The `nav` entries, and the toggle target `#portal-nav` |
| `partials/footer.html.twig` | Church contact details, ChurchCRM credit |
| `partials/flash.html.twig` | The fixed notice container, holding this request's `flash` messages. **A theme that overrides `layout.html.twig` must keep this include** — without it there is no container, and every message the portal raises goes unseen. |

### What the layout already loads

You do not need to add any of it, and you should not load a second copy:

- ChurchCRM's core CSS and JS bundle (Tabler, Bootstrap 5, jQuery), the RTL
  variant when the locale is right-to-left
- `moment`
- `portal.min.css`, then the active theme's `theme.css` when it has one
- `i18next` and the locale loader, then `portal.min.js`, then the active theme's
  `theme.js` when it has one
- whatever `<head>` and footer HTML the enabled plugins inject

`portal.min.js` also publishes `window.CRM.portalToast(message, type)` — `type`
being `success`, `warning`, `danger` or `info` (the default). Call it from a
theme's `theme.js` to show a message the way the portal shows all of them: a
toast in the fixed top-right stack that fades away on its own.

### Page IDs and classes worth knowing

| Selector | What it is |
|---|---|
| `.portal-body` | `<body>`; also `.portal-body-with-bar` when a bar is fixed to the top of the viewport — the masquerade banner is the only one |
| `.portal-shell` | The column that holds header, nav, main and footer |
| `.portal-container` | The width-limited wrapper used by every band |
| `#portal-nav` / `#portal-nav-toggle` | The navigation and the button that opens it on a phone |
| `#portal-account` / `#portal-account-toggle` / `#portal-account-menu` | The header's account menu: its wrapper, the "Hello &lt;first name&gt;" button and the `role="menu"` dropdown. `portal.min.js` binds to these ids, so a theme that overrides `partials/header.html.twig` must keep them |
| `#portal-main` | The `<main>` element |
| `.portal-card` | The standard content card |
| `.portal-page-title` | The `<h1>` at the top of a page's content |
| `.portal-detail-list` | A `<dl>` of label/value pairs — one column on a phone, two from the tablet breakpoint up |
| `.portal-card-details` | The same `<dl>` inside a card: one column at every width, because a card is a column and a long email address has nowhere to wrap |
| `.portal-contact-list` / `.portal-contact-item` | The church office's contact lines on `family/none.html.twig` — an icon and a `mailto:` or `tel:` link |
| `.portal-form` / `.portal-field` / `.portal-input` | A self-service form, one of its fields, and the control inside it |
| `.portal-field-error` | The inline message for a field; it is hidden while empty |
| `.portal-button` / `.portal-button-quiet` | The primary and secondary action buttons |
| `.portal-avatar` / `.portal-avatar-initials` | A member's photo, and the initials shown when there is none |
| `.portal-member-list` / `.portal-member` | The family members list; the member's own row also carries `.is-self` |
| `.portal-dialog` | A `<dialog>` in the portal's own chrome |
| `#portal-toasts` / `.portal-flash` | The fixed notice container, and one notice in it. `position: fixed`, so a notice never moves the page; see [`portal-themes.md`](./portal-themes.md) for its colour tokens |

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
| `isStaff` | bool | `true` for a login that also has the admin shell — the account menu offers it "Admin Console", unless a masquerade is in progress |

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
| `home.html.twig` | `GET /portal` | `pageTitle`, `upcomingEvents`, `hasVisibleCalendars`, `familySummary`, `profile` |
| `calendar/index.html.twig` | `GET /portal/calendar` | `pageTitle`, `calendars`, `hasCalendars`, `calendarConfigJson` |
| `profile/index.html.twig` | `GET /portal/profile` | `pageTitle`, `profile` |
| `profile/edit.html.twig` | `GET /portal/profile/edit` | `pageTitle`, `profile` |
| `profile/password.html.twig` | `GET/POST /portal/profile/password` (every role); also `GET/POST /v2/user/current/changepassword` for a self-service session | `pageTitle`, `formAction`, `minPasswordLength`, `oldPasswordError`, `newPasswordError` |
| `profile/password-changed.html.twig` | Either of those routes, after a successful change | `pageTitle` |
| `profile/two-factor.html.twig` | `GET /portal/profile/two-factor` (every role); also `GET /v2/user/current/manage2fa` for a self-service session | `pageTitle` |
| `family/index.html.twig` | `GET /portal/family` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm`, `familyRoles`, `defaultNewMemberRoleId` |
| `family/edit.html.twig` | `GET /portal/family/edit` | `pageTitle`, `family`, `members`, `canEdit`, `countries` |
| `family/confirm.html.twig` | `GET /portal/family/confirm` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm` |
| `family/none.html.twig` | All three family URLs, for a member with no family | `pageTitle`, `officeEmail`, `officePhone`, `officePhoneHref` |
| `errors/403.html.twig` | A page this member may not open | `pageTitle` |
| `errors/404.html.twig` | An unknown portal URL (and 405) | `pageTitle` |
| `errors/500.html.twig` | An unexpected failure | `pageTitle` |
| `errors/unavailable.html.twig` | Shown to **members** when the active theme fails to render | none |
| `errors/theme-error.html.twig` | Shown to **administrators** when the active theme fails to render | `themeName`, `file`, `line`, `message` |

`pageTitle` is the page's own title; the layout puts the church's name after it.

`formAction` on `profile/password.html.twig` is the URL that page's form posts
back to. It exists because the same template serves two routes: the portal's own
page, and the forced first-login change at `/v2/user/current/changepassword`,
which is pinned to its own URL until it completes. An override must post to
`formAction`, not to a hard-coded path, or a forced password change will loop.

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
| `profile` | object | On the home page too: the same `profile` the Profile page gets, or `null` for an account with no person record |

### A member with no family

`/portal/family`, `/portal/family/edit` and `/portal/family/confirm` all render
`family/none.html.twig` when the acting member has no family record — and when
the account has no person record at all. It is an ordinary portal page: HTTP
200, the usual chrome, "My Family" still the active navigation entry. Only URLs
that really do not exist get `errors/404.html.twig`.

| Variable | Type | Notes |
|---|---|---|
| `officeEmail` | string | **Admin → Church Information**'s email. Empty when unset — leave the line out rather than linking to nowhere |
| `officePhone` | string | The church's phone, exactly as it was typed. Print this |
| `officePhoneHref` | string | The same number reduced to what a `tel:` link can dial — digits, plus a leading `+` for an international number. Empty when the church configured no phone, or typed one with no digits in it |

With neither configured the system theme drops both lines and says only
"Please contact the church office."

### The home page's Profile card

The card prints the member's own details rather than a sentence describing them.
It reads them out of `profile` — the very view-model `profile/index.html.twig`
renders, so the two pages can never disagree — and skips every empty field: with
nothing on file it says "No contact details on file yet." The birthday follows
the Profile page and appears only while `profile.canEditBirthday` is true, and
the family role is shown only when `profile.familyId` is non-zero. Values sit in
`<dd data-field="…">` inside `#portal-home-profile-details`, with the same field
names the Profile page uses.

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
| `window.CRM.portalCalendar = {{ calendarConfigJson }}` plus `asset('/skin/v2/portal-calendar.min.js')` | The endpoint, the church's timezone, the window cap, and the two subscription endpoints |

### Subscribing to the calendar

The **Subscribe** button opens a dialog where a member ticks the calendars they
want in their own calendar app and gets one address back. The bundle draws the
checkboxes and fills in the address from
`GET /api/portal/calendar/subscription`, so none of it is server-rendered: the
feed address is a bearer secret and is fetched only when the dialog opens.

A theme that keeps the feature must keep these ids:

| Keep | What it is |
|---|---|
| `#portal-calendar-subscribe` | The button that opens the dialog |
| `#portal-calendar-subscribe-dialog` | The dialog itself |
| `#portal-calendar-subscribe-form` | A `<form>` carrying `{{ csrf_field() }}` — the bundle reads the token out of it |
| `#portal-calendar-subscribe-choices` | The empty container the checkboxes are drawn into |
| `#portal-calendar-subscribe-error` | Where a refused save is reported |
| `#portal-calendar-subscribe-save`, `#portal-calendar-subscribe-close` | Save, and dismiss |
| `#portal-calendar-subscribe-result` | The address block, `hidden` until there is an address |
| `#portal-calendar-subscribe-url` | A read-only `<input>` holding the feed address |
| `#portal-calendar-subscribe-copy` | Copy to clipboard (falls back to selecting the text) |
| `#portal-calendar-subscribe-open-row` | The paragraph holding the link below. Starts `hidden`; the bundle reveals it **only when the feed address is `https`** |
| `#portal-calendar-subscribe-open` | An `<a>` whose `href` the bundle sets to the `webcal://` address |
| `#portal-calendar-subscribe-manual-hint` | The sentence shown instead of that link when the site is on plain `http` — Apple's calendar clients rewrite `webcal://` to `https://` and never fall back, so the one-tap link cannot work there |
| `#portal-calendar-subscribe-reset` | Opens the confirmation below |
| `#portal-calendar-reset-dialog` with `#portal-calendar-reset-confirm` / `#portal-calendar-reset-cancel` | "Get a new calendar address?" |

A theme that wants none of this drops the button and both dialogs together; the
bundle does nothing when the button is absent.

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
