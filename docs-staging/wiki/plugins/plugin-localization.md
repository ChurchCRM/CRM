<!-- staged: 2026-04-13 — destined for docs.churchcrm.io wiki/plugins/plugin-localization.md -->

# Plugin Localization

This page is for **plugin developers** who want to ship translated
strings. It is not for site administrators.

ChurchCRM has a 45-language core translation workflow managed through
POeditor. **Community plugins do not participate in that workflow.**
Trying to add your plugin's strings to POeditor would force the
ChurchCRM maintainers to translate your plugin for every language they
support, which is not a burden we want to place on the volunteer
translation community.

Instead, community plugins ship their own translations inside the
plugin directory, and ChurchCRM loads them at boot. This page explains
exactly how that works.

---

## Directory layout

Add a `locale/` subdirectory to your plugin:

```
plugins/community/my-plugin/
├── plugin.json
├── src/
│   └── MyPluginPlugin.php
└── locale/
    ├── textdomain/                    # PHP gettext (.mo) files
    │   ├── en_US/LC_MESSAGES/my-plugin.mo
    │   ├── de_DE/LC_MESSAGES/my-plugin.mo
    │   └── es_ES/LC_MESSAGES/my-plugin.mo
    └── i18n/                          # JavaScript i18next (flat k/v JSON)
        ├── en_US.json
        ├── de_DE.json
        └── es_ES.json
```

Both subdirectories are optional. If your plugin only has PHP strings,
ship just `textdomain/`. If it only has JS strings, ship just `i18n/`.
If it has no strings at all, omit `locale/` entirely.

**Rule of thumb:** always ship an `en_US` file in each directory you
use. That gives users on unsupported locales a usable fallback.

---

## PHP strings (gettext)

At boot, ChurchCRM calls
`PluginLocalization::bindPhpDomains()` which walks every loaded plugin
and, for each one with a `locale/textdomain/` directory, calls:

```php
bindtextdomain('my-plugin', '{plugin-path}/locale/textdomain');
bind_textdomain_codeset('my-plugin', 'UTF-8');
```

The gettext textdomain name is your plugin id. This keeps your
strings fully isolated from the core `messages` domain and from every
other plugin.

**In your plugin code, always use `dgettext()`** — never plain
`gettext()` or `_()`, because those go to the core `messages` domain
and your strings will never be found:

```php
// ✅ CORRECT — translates from locale/textdomain/.../my-plugin.mo
echo dgettext('my-plugin', 'Welcome to My Plugin');

// ❌ WRONG — looks up the string in the core messages domain
echo gettext('Welcome to My Plugin');
```

### Building `.mo` files

Use the standard gettext toolchain. Your source `.po` file lives
wherever you want inside your plugin's source repo — only the
compiled `.mo` has to be shipped in the release zip.

Extract strings from your PHP source:

```bash
xgettext \
  --language=PHP \
  --keyword=dgettext:2 \
  --from-code=UTF-8 \
  --output=locale/my-plugin.pot \
  src/**/*.php
```

Compile a translated `.po` to `.mo`:

```bash
msgfmt -o locale/textdomain/de_DE/LC_MESSAGES/my-plugin.mo locale/de_DE.po
```

> **Note the `--keyword=dgettext:2`.** The default `xgettext` settings
> extract `gettext()` / `_()` calls. Plugin strings use `dgettext()`,
> which `xgettext` doesn't scan unless you tell it to.

---

## JavaScript strings (i18next, no core changes needed)

Ship a flat `key → string` JSON file per locale at
`locale/i18n/{locale}.json`. The loader does three things:

1. Rejects files larger than 512 KB.
2. Falls back to `en_US.json` when the user's locale file is missing.
3. Embeds the resulting map in `window.CRM.plugins.{id}.i18n`.

Example file:

```json
{
  "myplugin.welcome": "Willkommen bei My Plugin",
  "myplugin.save": "Speichern",
  "myplugin.cancel": "Abbrechen"
}
```

### Consuming the strings

Option 1 — plain lookup (simplest):

```js
const t = (key) =>
  window.CRM?.plugins?.["my-plugin"]?.i18n?.[key] ?? key;

document.getElementById("title").textContent = t("myplugin.welcome");
```

Option 2 — register with i18next as a namespace (if your page already
uses i18next):

```js
i18next.addResourceBundle(
  window.CRM.shortLocale,
  "my-plugin",
  window.CRM.plugins["my-plugin"].i18n || {},
  true,
  true
);

i18next.t("myplugin.welcome", { ns: "my-plugin" });
```

---

## Rules the loader enforces

- **Flat maps only.** Nested JSON objects are silently dropped. Use
  prefixed keys (`myplugin.welcome`) instead of namespacing through
  nested structures.
- **512 KB cap.** Keep individual locale files small; split them if
  needed. A warning is logged if a file is skipped.
- **UTF-8.** Every `.mo` must be UTF-8; every JSON file must be UTF-8
  without BOM.
- **Namespace your keys.** Prefix every key with your plugin id so
  you never collide with another plugin or with core strings.
- **Plugin ids are kebab-case.** The loader refuses to bind a
  textdomain for any id that isn't kebab-case (matching
  `^[a-z0-9][a-z0-9-]*$`) or that equals the reserved name
  `messages`.

---

## Testing your translations

1. Install your plugin into a local CRM (`POST
   /plugins/api/plugins/install`), enable it, and switch the admin
   user's UI locale to a language you shipped a translation for.
2. Load a page that uses a translated string. Confirm the translation
   renders.
3. Switch to a language you did **not** translate. Confirm the
   `en_US` fallback renders (this proves your fallback file is
   correct).
4. Temporarily rename your `locale/i18n/en_US.json` to confirm that
   the loader then shows the raw key — which is what your users will
   see if you ship a broken release.

---

## FAQ

**Can I use POeditor for my plugin anyway?**
No — POeditor only covers the core `messages` textdomain and the core
`locale/i18n/*.json` files. Community plugins live in a different
textdomain, so strings submitted to POeditor would never be loaded at
runtime.

**Do I have to translate into every language ChurchCRM supports?**
No. Ship `en_US` plus whatever you want to ship. Missing locales fall
back to `en_US`, and users on unsupported locales still see a working
interface.

**My JSON file has nested objects and everything disappeared. Why?**
The loader intentionally rejects nested structures — only flat
`string → string` maps are accepted. Flatten your keys
(`group.section.key`) and re-ship.

**Where is the code that loads these files?**

- [`src/ChurchCRM/Plugin/PluginLocalization.php`](https://github.com/ChurchCRM/CRM/blob/main/src/ChurchCRM/Plugin/PluginLocalization.php)
  — the loader itself.
- [`src/ChurchCRM/Plugin/PluginManager.php`](https://github.com/ChurchCRM/CRM/blob/main/src/ChurchCRM/Plugin/PluginManager.php)
  — calls `bindPhpDomains()` in `loadActivePlugins()` and embeds JS
  resources in `getPluginsClientConfig()`.
- [`.agents/skills/churchcrm/plugin-development.md → Plugin Localization`](https://github.com/ChurchCRM/CRM/blob/main/.agents/skills/churchcrm/plugin-development.md#plugin-localization-independent-of-poeditor)
  — the canonical developer skill.
