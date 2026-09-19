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
| `footer` | The church contact line and the church's social links | `partials/footer.html.twig` |
| `scripts_extra` | Extra `<script>` for this page — with `nonce="{{ nonce() }}"` | empty |

### Partials

Each is a separate file, so a theme can replace one without touching the others:

| Partial | Renders |
|---|---|
| `partials/header.html.twig` | Church logo and name, and the account menu ("Hello &lt;first name&gt;" → Email History, Change Password, Admin Console for staff, Sign out) |
| `partials/nav.html.twig` | The `nav` entries, and the toggle target `#portal-nav` |
| `partials/footer.html.twig` | Church contact details on the leading edge, `church.socialLinks` as icon links on the trailing edge |
| `partials/flash.html.twig` | The fixed notice container, holding this request's `flash` messages. **A theme that overrides `layout.html.twig` must keep this include** — without it there is no container, and every message the portal raises goes unseen. |

### What the layout already loads

You do not need to add any of it, and you should not load a second copy:

- ChurchCRM's core CSS and JS bundle (Tabler, Bootstrap 5, jQuery), the RTL
  variant when the locale is right-to-left
- `moment`
- `portal.min.css`, then the active theme's `theme.css` when it has one
- `bootbox` (confirmations and prompts), `i18next` and the locale loader, then
  `portal.min.js`, then the active theme's `theme.js` when it has one
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
| `.portal-subnav` | A page's own secondary tab bar, e.g. the two volunteering pages |
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
| `.portal-breadcrumb` | The single "← back" link above a nested page's title (My Teams) |
| `.portal-team-card` / `.portal-team-ministry` | One team on **My Teams**, and the ministry line under a team's name |
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
| `portal-team`, `portal-team-tabs`, `nav-item-positions` / `-volunteers` / `-schedules` / `-occurrences` | `teams/team.html.twig` |

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
| `socialLinks` | list | The church's social accounts (#9907), already filtered to the ones that are set and ordered X, YouTube, Facebook, Instagram. Each entry is `{id, label, url, icon}` — `icon` is a Font Awesome Free brand class such as `fa-brands fa-facebook`. Empty when the church has configured none, so `{% if church.socialLinks is not empty %}` is the whole guard you need. |

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
| `isTeamLeader` | bool | `true` when this person leads at least one volunteer team. Also `true` on a self-service login — that is the point of it |
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
| `home.html.twig` | `GET /portal` | `pageTitle`, `upcomingEvents`, `hasVisibleCalendars`, `showVolunteering`, `familySummary`, `profile` |
| `calendar/index.html.twig` | `GET /portal/calendar` | `pageTitle`, `calendars`, `hasCalendars`, `calendarConfigJson` |
| `volunteer/schedule.html.twig` | `GET /portal/volunteer/schedule` | `pageTitle`, `activeTab` |
| `volunteer/opportunities.html.twig` | `GET /portal/volunteer/opportunities` | `pageTitle`, `activeTab` |
| `volunteer/partials/tabs.html.twig` | included by both volunteering pages | `activeTab` |
| `profile/index.html.twig` | `GET /portal/profile` | `pageTitle`, `profile` |
| `profile/edit.html.twig` | `GET /portal/profile/edit` | `pageTitle`, `profile` |
| `profile/password.html.twig` | `GET/POST /portal/profile/password` (every role); also `GET/POST /v2/user/current/changepassword` for a self-service session | `pageTitle`, `formAction`, `minPasswordLength`, `oldPasswordError`, `newPasswordError` |
| `profile/password-changed.html.twig` | Either of those routes, after a successful change | `pageTitle` |
| `profile/two-factor.html.twig` | `GET /portal/profile/two-factor` (every role); also `GET /v2/user/current/manage2fa` for a self-service session | `pageTitle` |
| `family/index.html.twig` | `GET /portal/family` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm`, `familyRoles`, `defaultNewMemberRoleId` |
| `family/edit.html.twig` | `GET /portal/family/edit` | `pageTitle`, `family`, `members`, `canEdit`, `countries` |
| `family/confirm.html.twig` | `GET /portal/family/confirm` | `pageTitle`, `family`, `members`, `canEdit`, `canConfirm` |
| `teams/index.html.twig` | `GET /portal/teams` | `pageTitle`, `teams` |
| `teams/team.html.twig` | `GET /portal/teams/{teamId}` | `pageTitle`, `team` |
| `teams/occurrence.html.twig` | `GET /portal/teams/{teamId}/occurrences/{occurrenceId}` | `pageTitle`, `team`, `occurrence` |
| `family/none.html.twig` | All three family URLs, for a member with no family | `pageTitle`, `officeEmail`, `officePhone`, `officePhoneHref` |
| `email/index.html.twig` | `GET /portal/email-history` | `pageTitle`, `emails`, `page`, `pages`, `total`, `previousUrl`, `nextUrl` |
| `email/show.html.twig` | `GET /portal/email-history/{id}` | `pageTitle`, `email`, `body`, `hasBody` |
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

### `teams`, `team` and `occurrence` (My Teams)

`teams` is the list **My Teams** renders, one entry per team this member runs,
ordered by ministry then team.

| Field | Type | What it is |
|---|---|---|
| `id` | int | The team |
| `name` | string | The team's name |
| `ministryId` / `ministryName` | int / string | The ministry above it |
| `active` | bool | False for a deactivated team; the card says so |
| `positionCount` | int | How many positions the team owns, active or not |
| `nextOccurrence` | object | `{id, date}` for the soonest scheduled date from today, or `null` |
| `url` | string | The team's page, root-path aware |

`team` on the two nested pages is `{id, name, description, active, ministryId,
ministryName}` — `description` is `''` when the team has none.

`occurrence` is `{id, scheduleName, status, date, start, end, eventId,
eventTitle, eventLocation}`. `status` is `'scheduled'` or `'cancelled'`; `start`
is `YYYY-MM-DD HH:MM` and `end` is `HH:MM`, both `''` for an occurrence with no
resolved window; `eventId` is `0` when the date is not linked to a calendar
event, and the two event fields are `''` then.

The two theme-failure pages are always rendered from the **system** theme, so a
broken theme cannot break the page that reports it. A theme may still override
them — its version is used everywhere except when that theme is the one that
failed.

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

### The volunteering pages

`showVolunteering` is `true` when the installation offers the volunteering pages;
the home page renders its "My volunteering" card only then. `activeTab` is
`'schedule'` or `'opportunities'` and is what the tab partial highlights.

**The two volunteering pages are container markup and nothing else.** Their ids —
`#volunteer-my-schedule`, `#assignments-loading` / `-error` / `-empty` / `-content`,
`#substitute-modal` and friends on one page; `#volunteer-opportunities`,
`#help-wanted-section` / `-content`, `#opportunities-loading` / `-error` / `-empty` /
`-content` on the other — are read by `skin/v2/portal-volunteer-schedule.min.js` and
`skin/v2/portal-volunteer-opportunities.min.js`, which fill them from
`/api/ministries/me/*`. A theme overriding either template must keep every id, or
the page renders empty. Override the wrapper, the headings and the surrounding
layout freely.

**The team and occurrence pages are container markup too.** `teams/team.html.twig`
is filled by `skin/v2/portal-teams.min.js` and
`teams/occurrence.html.twig` by `skin/v2/ministries-occurrence.min.js` — the
admin occurrence page's bundle, unchanged. Both address their markup by id, and
those ids are deliberately the same ones the admin ministry and occurrence views
carry, so the same components can draw either. A theme overriding one of these
templates must keep every id it finds there: `volunteerPositionsTable`,
`volunteerQualificationsTable`, `volunteerSchedulesTable`,
`volunteerOccurrencesTable` and their `-loading` / `-error` / `-empty` /
`-table-wrapper` state blocks; `positionModal` and `scheduleModal` with their
`*-form-*` fields; and on the occurrence page `requirements-*`, `swaps-*`,
`volunteer-assign-modal` and `volunteer-needs-modal`. Override the wrapper, the
headings, the tab strip and the surrounding layout freely.

A control a theme **removes** is not an error: every one of those bundles looks
its controls up by id and does nothing when one is absent. That is how the
portal's own templates leave out the things a team leader may not do.

Every page the epic promised is in this table now.
### Email History

`email/index.html.twig` is a paginated list of what the church has emailed this
member — reached from the header's account menu, not from the main navigation,
so **no nav entry is active while it is open**.

`emails` is the page's rows, newest first. Every field is a finished string;
nothing here needs formatting or reasoning about in a template:

| Field | Type | Notes |
|---|---|---|
| `id` | int | Use it to build the detail URL |
| `subject` | string | Empty for an email that was sent without one — print `(no subject)` |
| `kindLabel` | string | The kind in words: `Message`, `Birthday greeting`, `Password reset link`, … |
| `status` | string | `sent`, `failed` or `skipped` — for a CSS class, not for reading |
| `statusLabel` | string | The same, in words, already translated |
| `address` | string | The address the message went to |
| `dateSent` | string | Formatted in the installation's date format, with the time |
| `hasBody` | bool | False for the account emails, whose content is never stored |

`page`, `pages` and `total` drive "Page X of Y"; `previousUrl` and `nextUrl` are
query strings (`?page=2`) and are empty strings at the ends of the list, which is
how the template knows not to draw that link. The list is server-rendered — there
is no bundle on this page — but the same rows are available to a theme that would
rather fetch them, at `GET /api/portal/me/emails?page=&limit=`.

The list is one `<table class="portal-table">` at every width. On a phone
`_portal.scss` unrolls it into a stack of blocks, taking each cell's column name
from its `data-label` attribute, so an override that keeps the table markup gets
the phone layout for free.

`email/show.html.twig` is one email: `email` is a single row in the shape above,
`hasBody` says whether there is anything to show, and `body` is **the stored HTML
of the message**.

> **`body` goes in exactly one place: the `srcdoc` of an iframe with an empty
> `sandbox` attribute.** It is markup somebody else wrote, kept as it was sent.
> Twig's autoescaping is what makes the attribute safe; `|raw` on it, or printing
> it anywhere in the document, hands a member's browser foreign markup with the
> portal's own origin behind it. The frame does not grow to fit its content — an
> empty `sandbox` puts the document in its own opaque origin, so its height
> cannot be measured from the page, and the two ways to measure it
> (`allow-same-origin`, or a script inside the frame) are the two permissions
> this page must not grant. It scrolls instead.

Scope: the page shows the rows addressed to this person. Email sent to the
family's shared address is not included — see
`src/api/routes/portal/portal-emails.php`.

---

## Compatibility

Core may **add** a block, a variable or a global at any time; that never breaks a
theme. Renaming or removing one is called out in the release notes, and the old
name keeps working for one release.

The validator's "this version of ChurchCRM does not use a template at this path"
warning is how a theme finds out that a template it overrode has moved — run
**Check** on **Admin → Member Portal** after upgrading.
