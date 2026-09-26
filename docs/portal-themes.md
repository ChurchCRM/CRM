# Member Portal themes — authoring guide

The Member Portal (`/portal`) is the member-facing area of ChurchCRM: what a
congregation member lands in after signing in. A **theme** is how your church
makes it look like your church.

A theme is a folder. Nothing is registered, compiled or installed — you upload
files and pick the theme on **Admin → Member Portal**.

> **Themes are provided by your church, not by ChurchCRM.** Unlike community
> plugins, themes are not reviewed, signed or verified by anyone. A theme's
> files are trusted exactly as far as the administrator who uploaded them.

---

## Where a theme lives

```
src/Include/themes/<your-theme>/
```

`Include/` is the one directory ChurchCRM promises to leave alone: it holds
`Config.php`, the upgrader never overwrites it, and the orphan-file scan ignores
it. Your theme survives every ChurchCRM update.

The folder name **is** the theme: it is the id in the configuration and the name
shown on the admin page, spelled exactly as you spelled it.

> **Never edit `Include/themes/default/`.** That is the system theme; it ships
> with the release and is overwritten on every upgrade. Copy it, rename the
> copy, and edit that.

---

## What a theme can contain

Every file is optional. A theme with only `theme.css` is a complete theme.

| Path | What it does |
|---|---|
| `theme.css` | Loaded after the portal's own stylesheet. Redefine the design tokens below, or restyle anything. |
| `theme.js` | Loaded last, after the translations are ready. |
| `images/`, `fonts/` | Referenced from CSS with a relative `url("images/hero.jpg")`, or from a template with `{{ theme_asset('images/hero.jpg') }}`. |
| `templates/**` | Any file with the same relative path as one in `default/templates/` replaces it. You may also add partials of your own and include them from your overrides. |
| `theme.json` | Optional `{"name": "…", "author": "…", "description": "…"}` for the admin page. Nothing functional. |

**A theme never contains PHP.** Templates can only call what the portal exposes
(see [`portal-templates.md`](./portal-templates.md)), which is what makes an
uploaded theme safe to render. There is no PHP extension point, now or later.

### The smallest possible theme

```
src/Include/themes/uccc/
  theme.css
```

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

That alone recolours and refonts every portal page.

### Design tokens

| Token | Used for |
|---|---|
| `--portal-primary` | Header background, active navigation, buttons |
| `--portal-primary-contrast` | Text drawn on `--portal-primary` |
| `--portal-accent` | Badges and the masquerade banner |
| `--portal-surface` | Cards, navigation bar, footer |
| `--portal-surface-alt` | Page background |
| `--portal-text` | Body text |
| `--portal-muted` | Secondary text |
| `--portal-border` | Card and divider borders |
| `--portal-font-body` | Body font stack |
| `--portal-font-heading` | Headings and the church name |
| `--portal-radius` | Corner radius |
| `--portal-header-bg`, `--portal-header-text` | Header, when you want it different from `--portal-primary` |
| `--portal-hero-image` | The `hero` block's background image |
| `--portal-content-width` | Maximum content width |
| `--portal-gap` | The spacing rhythm |
| `--portal-success`, `--portal-success-contrast` | A "saved" notice — green by default |
| `--portal-warning`, `--portal-warning-contrast` | A "have a look at this" notice |
| `--portal-danger`, `--portal-danger-contrast` | A "that did not work" notice |
| `--portal-info`, `--portal-info-contrast` | A plain notice; follows `--portal-primary` |
| `--portal-toast-width` | How wide the notice stack is, off a phone |

**Notices.** Every message the portal shows — "Your details have been saved.",
"Nothing was saved." — is a toast in a fixed container in the top-right corner
(the whole width, with a margin, on a phone). It is always out of the document
flow, so a notice never moves the page. The four pairs of tokens above are the
whole palette; the markup is `.portal-toasts > .portal-flash.portal-flash-<type>`.

**Dark mode.** Members choose light, dark or "follow my device". In dark mode
the portal carries `data-bs-theme="dark"` on `<html>`, so redefine whichever
tokens need it:

```css
[data-bs-theme="dark"] {
  --portal-surface: #1a1d21;
  --portal-surface-alt: #22262b;
  --portal-text: #f1f3f5;
}
```

If your theme deliberately supports only one look, leave the block out — the
portal's own defaults still apply.

**Right-to-left.** Portal CSS uses logical properties only
(`margin-inline-start`, not `margin-left`), so it is correct in both directions
without a flipped second stylesheet. Write your theme the same way and Arabic and
Hebrew installs get your design for free.

---

## Overriding a template

Copy the template you want to change out of `Include/themes/default/templates/`
into your theme at the **same relative path**, and edit it:

```
src/Include/themes/uccc/templates/home.html.twig
```

Usually you do not want to rewrite the page — you want to change one part of it.
Extend the core template through the `@default` namespace and override a single
block:

```twig
{% extends "@default/home.html.twig" %}

{% block hero %}
<section class="portal-hero uccc-hero">
    <img src="{{ theme_asset('images/welcome.jpg') }}" alt="">
    <h1>{{ gettext('Welcome home') }}</h1>
</section>
{% endblock %}
```

`@default/...` always addresses the template that ships with ChurchCRM, even
from the file that replaces it — no infinite loop.

The blocks every page offers, and every variable a template can read, are
documented in [`portal-templates.md`](./portal-templates.md).

---

## Live editing

Templates are live: upload a changed file over FTP and reload the page. Twig
recompiles a template whose file changed, so nothing has to be cleared. The same
goes for `theme.css` and `theme.js` — their URLs carry the file's modification
time, so a changed file is a different URL and the browser fetches it.

---

## Activating a theme

**Admin → Member Portal → Settings** lists every folder under
`Include/themes/`, the system theme first. Pick yours and save.

Activation compiles **every** template in the theme first. If any of them does
not compile, activation is refused and the page lists the file, the line and the
message. Warnings — an override at a path this version of ChurchCRM does not
render, an image a template asks for that is not in the theme — are shown but do
not block activation.

---

## When a theme is broken

A theme fails loudly. There is no silent fall-back to the system theme, because
that would hide the problem from the person who can fix it.

| What happened | An administrator sees | A member sees | Where it is recorded |
|---|---|---|---|
| Activation of a theme whose template does not compile | The admin page refuses and lists file, line and message | nothing changes | the application log |
| The active theme fails while rendering a page | The theme error page: theme, file, line, message | "The Member Portal is temporarily unavailable. The church office has been notified." | the application log (**Admin → System → Logs**) *and* the web server's error log |
| The active theme's folder is gone | The same error page, saying the folder was not found | the same page | the same two logs |

So: after uploading a change, load a portal page yourself while signed in as an
administrator. If something is wrong, you — not your members — will see exactly
what.

---

## Assets and the rules they follow

Theme files are never served straight off disk: `Include/` is closed to the web
on purpose. They come out through the application at

```
/portal/theme/<theme>/<path>
```

which serves **only** these extensions:

```
css js png jpg jpeg gif svg webp ico woff woff2 ttf
```

Anything else — `theme.json`, a template, a stray `.php`, a path containing
`..` — is a 404. Served files carry an `ETag` and a one-year cache lifetime, and
their URLs carry the file's modification time, so a changed file is picked up at
once and an unchanged one is never re-downloaded.

### Content Security Policy

The portal runs under ChurchCRM's CSP, and your theme runs inside it:

- **Fonts and stylesheets** may come from this installation, from
  `fonts.googleapis.com` and from `fonts.gstatic.com`. Nothing else. Self-hosting
  a font file in your theme's `fonts/` folder always works.
- **Scripts** must be your theme's own `theme.js`, or inline with the
  request's nonce: `<script nonce="{{ nonce() }}">`. A script from another
  origin is blocked.
- **Images** may come from this installation or be `data:` URIs.

### Writing a safe template

- Never use Twig's `|raw` filter on anything a member typed. Twig escapes
  everything by default; `|raw` turns that off, and that is how a name becomes
  a script tag.
- Do not try to reach ChurchCRM internals from a template. Templates can call
  only the handful of functions listed in
  [`portal-templates.md`](./portal-templates.md) — no PHP, no filesystem, no
  database, no session.
- Wrap text you add in `{{ gettext('…') }}` if your church uses more than one
  language.

---

## Distributing a theme

`Include/themes/*` is ignored by ChurchCRM's git repository (except the system
theme), so keep your theme in its own repository and deploy it with your own
script or over FTP. Nothing in ChurchCRM's release will ever overwrite it.

---

## Admin → Member Portal

Everything about the portal that an administrator can change lives on one page:
**Admin → Member Portal** (`/admin/member-portal`). It is administrators only,
and it has three tabs.

### Settings

**Portal theme** lists every folder found under `Include/themes/`, with the
system theme first as *System default*. Each entry carries the result of the
last validation: *Valid*, *Warnings* or *Errors*.

- **Check** runs the validator against the theme you have selected and lists
  what it found — file, line and message — without changing anything. Use it
  after uploading a change, before making the theme live.
- **Activate** makes the selected theme the one members see, on their next
  request. Activation validates first: a theme with error-level findings is
  **refused**, the findings are listed, and the portal keeps the theme it
  already had. Warnings are shown and allowed.

**Developer mode** is for whoever is writing the theme:

- the Twig compile cache is turned off entirely, so nothing can go stale while
  you edit (normal operation already recompiles a changed file on the next
  request — this simply removes the cache from the picture);
- every portal page starts with a comment naming the template that produced it:

  ```html
  <!-- portal template: home.html.twig -->
  ```

  which tells you exactly which file to copy into your theme to override it.

Leave Developer mode **off** in normal use: without the cache, every page
recompiles its templates on every request.

The remaining switches decide what the portal offers members: whether the church
calendar is shown, whether the volunteering and team pages are shown, and whether
members may change birthdays on their own and their family's records.

### Themes

A table of every theme folder on the server: its display name (from
`theme.json` when present, otherwise the folder name), the folder itself, the
author and description from `theme.json`, how many templates it overrides, and
its last validation result — expand a row to read the individual findings.
Each row can be checked or activated from its action menu.

> **Themes are provided by your church, not by ChurchCRM, and are not verified
> by the ChurchCRM project.** The page says so too.

### Statistics

How much the portal is being used, counting **self-service accounts only** —
people whose login reaches the portal and nothing else:

| Number | Where it comes from |
|---|---|
| Active now | `usr_LastPortalActivity` within the last 15 minutes |
| Last 24 hours / 7 days / 30 days | `usr_LastLogin` |
| Self-service accounts | every account with Edit Self and no Admin flag |
| Never signed in | `usr_LoginCount` is zero |
| Ten most recent sign-ins | newest `usr_LastLogin` first, with the member's name and when they were last seen in the portal |

`usr_LastPortalActivity` is stamped when a member opens a portal page, at most
once every five minutes, so "active now" is honest without costing a write per
page view.
