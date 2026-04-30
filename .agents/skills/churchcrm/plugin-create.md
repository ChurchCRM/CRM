---
title: "Plugin Create — Community Plugin Quickstart & Submission"
intent: "Step-by-step flow for building a new community plugin from scratch and getting it added to the approved allowlist"
tags: ["plugins","community","create","submission","security"]
prereqs: ["plugin-development.md","plugin-security-scan.md"]
complexity: "intermediate"
---

# Plugin Create — Community Plugin Quickstart <!-- learned: 2026-04-13 -->

> **Scope — community plugins only.** This skill is the create-from-scratch
> walkthrough for third-party plugins that will be installed into
> `src/plugins/community/` via the URL installer. If you are updating a
> plugin shipped in `src/plugins/core/`, read
> [`plugin-migration.md`](./plugin-migration.md) instead.

This document is the shortest path from "I want to build a ChurchCRM
community plugin" to "my plugin is in `approved-plugins.json` and end
users can install it." It hands off to deeper skill files at every
step rather than duplicating their content.

---

## 0. Read the contract before you write code

**Do not skip this.** Two documents define what a community plugin is
allowed to do. If you build around a different assumption, the review
will bounce your plugin:

1. [`plugin-development.md → What a Plugin Can and Cannot Do`](./plugin-development.md#what-a-plugin-can-and-cannot-do)
   — the full allow/deny list, with every allowed capability mapped
   to the tag you will declare in the approved registry.
2. [`plugin-security-scan.md`](./plugin-security-scan.md) — the review
   checklist every submission is measured against. Treat it as the
   spec, not a post-hoc audit.

Write down which capability tags from `KNOWN_PERMISSIONS` your plugin
will need **before** you start coding. If the answer includes
`hooks.financial` + `network.outbound`, or `fs.write` +
`network.outbound`, know upfront that your plugin is automatically
classified `high` and will need two maintainer reviews.

---

## 1. Scaffold the plugin directory

Use the scaffolder — it clones the reference plugin from
[ChurchCRM/community-plugin-hello-world](https://github.com/ChurchCRM/community-plugin-hello-world)
into `src/plugins/community/{id}/` and rewrites the namespace, id,
and class name for you:

```bash
php scripts/create-plugin.php my-plugin --author="Your Name"
```

Result:

```
src/plugins/community/my-plugin/
├── plugin.json
├── README.md
├── src/
│   └── MyPluginPlugin.php      # extends AbstractPlugin
└── locale/
    └── i18n/
        └── en_US.json          # plugin-local i18next resources
```

A full-featured plugin might also add:

- `routes/routes.php` — Slim routes under `/plugins/{id}/...`
- `views/*.php` — view templates
- `locale/textdomain/{locale}/LC_MESSAGES/{id}.mo` — compiled
  gettext (build with `xgettext --keyword=dgettext:2` + `msgfmt`)
- `help.json` — help content rendered in the settings modal

Keep it minimal. The fewer files you ship, the faster the review and
the smaller the attack surface.

> **Don't want to use the scaffolder?** Fork
> [ChurchCRM/community-plugin-hello-world](https://github.com/ChurchCRM/community-plugin-hello-world)
> directly and rename the identifiers. The scaffolder is a
> convenience, not a requirement.

---

## 2. Write `plugin.json`

The manifest is the single source of truth for your plugin's
identity. It must declare `type: "community"` — the installer refuses
anything else when installed by URL.

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "description": "One sentence describing what the plugin does.",
    "version": "1.0.0",
    "author": "Your Name",
    "authorUrl": "https://yoursite.example.org",
    "type": "community",
    "minimumCRMVersion": "7.1.0",
    "mainClass": "ChurchCRM\\Plugins\\MyPlugin\\MyPluginPlugin",
    "dependencies": [],
    "settings": [
        {
            "key": "apiKey",
            "label": "API Key",
            "type": "password",
            "required": true,
            "help": "Your upstream API key. Stored encrypted at rest."
        }
    ],
    "menuItems": [],
    "hooks": [],
    "hasTest": false
}
```

Rules:

- `id` must match the top-level directory name inside your zip.
- `version` must match the approved registry entry **exactly** — the
  installer cross-checks both fields and refuses mismatches.
- Password-type settings are automatically masked in the admin UI;
  never return raw passwords from `getClientConfig()`.
- `minimumCRMVersion` is enforced by the installer. Set it to the
  oldest ChurchCRM release you are willing to test against.

Full schema: [`plugin-development.md → plugin.json Manifest`](./plugin-development.md#pluginjson-manifest).

---

## 3. Write the main class

Extend `AbstractPlugin`. Keep the class small — behaviour lives in
service classes you register from `boot()`.

```php
<?php

namespace ChurchCRM\Plugins\MyPlugin;

use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;

class MyPluginPlugin extends AbstractPlugin
{
    public function getId(): string
    {
        return 'my-plugin';
    }

    public function boot(): void
    {
        HookManager::addAction(Hooks::PERSON_CREATED, [$this, 'onPersonCreated']);
    }

    public function isConfigured(): bool
    {
        return $this->getConfigValue('apiKey') !== '';
    }

    public function onPersonCreated($person): void
    {
        // Every outbound call must correspond to the `network.outbound`
        // tag in your approved registry entry. Keep a single, well-named
        // HTTP client — makes the security scan trivial.
    }
}
```

The rules you have to live by:

- Read/write only `plugin.{id}.*` config via `$this->getConfigValue()`
  and `$this->setConfigValue()`. Touching any other key is grounds for
  rejection.
- Never call `eval`, `create_function`, `assert` with string input,
  `passthru`, `shell_exec`, `proc_open`, `system`, `pcntl_exec`,
  `extract($_POST)`, `parse_str($_POST)`, or `unserialize` on
  attacker-reachable input. The security scan greps for every one of
  these.
- Never write files outside your plugin directory. No self-updaters,
  no core patches, no `/tmp` helpers.
- Never bundle `base64_decode(...)` of an embedded blob to
  reconstitute code at runtime.

Full allow/deny list: [`plugin-development.md → What a Plugin Can and Cannot Do`](./plugin-development.md#what-a-plugin-can-and-cannot-do).

---

## 4. Add routes (optional)

If your plugin needs HTTP endpoints, add `routes/routes.php` and
reference it from `plugin.json` via `routesFile`. The plugin entry
point loads the file only when the plugin is active, so you do not
need an `isActive` check of your own.

```php
<?php
// routes/routes.php
use ChurchCRM\Plugin\PluginManager;
use Slim\Routing\RouteCollectorProxy;

$plugin = PluginManager::getPlugin('my-plugin');
if ($plugin === null) { return; }

$app->group('/my-plugin/api', function (RouteCollectorProxy $group) use ($plugin): void {
    $group->get('/items', function ($request, $response) use ($plugin) {
        // Inherited middleware: Auth + ChurchInfoRequired + Version.
        // Do NOT add the stack again here.
    });
});
```

Full pattern: [`plugin-development.md → Plugin Routes`](./plugin-development.md#plugin-routes-routesroutesphp).

---

## 5. Ship translations (optional)

Community plugins **never** go through POeditor. Put your translations
inside the plugin directory:

- PHP: `locale/textdomain/{locale}/LC_MESSAGES/my-plugin.mo`, called
  from plugin code with `dgettext('my-plugin', '…')`. **Never** use
  plain `gettext()` or `_()` — those go to the core `messages` domain
  and your strings will not be found.
- JS: `locale/i18n/{locale}.json` as a **flat** `key => string` map.
  Nested JSON is silently dropped by the loader.
- Always ship `en_US` for both formats as a fallback.
- Extract with `xgettext --keyword=dgettext:2` — the default `xgettext`
  settings do not scan `dgettext()` calls.

Full guide: [`plugin-development.md → Plugin Localization`](./plugin-development.md#plugin-localization-independent-of-poeditor).

---

## 6. Run the self-scan against your own plugin

Use `scripts/plugin-scan.php` — it runs the same checklist a
ChurchCRM maintainer would run during the plugin-security-scan
review, against your plugin directory on disk:

```bash
php scripts/plugin-scan.php src/plugins/community/my-plugin
```

The scanner reports **errors** (blocking), **warnings** (need
justification in the PR), and **infos** (capability inventory you
should copy into the PR body). Exit code 0 means no errors.

For machine-readable output:

```bash
php scripts/plugin-scan.php --json src/plugins/community/my-plugin | jq
```

### What the scanner covers

- `plugin.json` exists, parses, has required fields, uses
  kebab-case id, declares `type: "community"`, and the declared
  `mainClass` + `routesFile` resolve on disk.
- Every file has an allowed extension. `.phar`, `.sh`, `.exe`,
  `.so`, `.dll`, `.phtml`, etc. are rejected.
- No hidden files other than `.editorconfig` / `.gitattributes`.
- PHP and JS sources are free of dangerous sinks (`eval`,
  `shell_exec`, `passthru`, `proc_open`, `system`, `pcntl_exec`,
  `extract($_POST)`, `parse_str($_POST)`, `preg_replace(.../e)`,
  `unserialize`, `base64_decode` of bundled blobs).
- Plugin code uses `dgettext()` for translations, not plain
  `gettext()` / `_()` — that would hit the core `messages` textdomain.
- Every outbound hostname is listed so you can confirm each one is
  named in your `riskSummary` and covered by `network.outbound`.
- Every literal `file_put_contents()` target is listed so you can
  confirm writes stay inside the plugin directory.

### Fix every hit before shipping

Every error must be fixed — an error means `PluginInstaller` or the
maintainer review will reject your plugin. Every warning must be
justified in the PR body: say why that sink exists and which input
it receives. If you can't explain it in one sentence, delete it.

### Beyond the scanner

The scanner covers the mechanical parts of the review. For the rest
— runtime smoke test, dependency graph, VDP, capability inventory —
follow [`plugin-security-scan.md`](./plugin-security-scan.md)
sections 5 and 6 by hand.

---

## 7. Build a reproducible release zip

The approved registry pins your zip by SHA-256. If the bytes change
between review and publish, the installer will refuse to install it.

```bash
# 1. Tag the release in your upstream repo
git tag v1.0.0
git push --tags

# 2. Build the zip deterministically
#    (use the GitHub release artifact if you use GitHub releases —
#     GitHub's tarball will be the immutable URL you pin)
cd /tmp
mkdir my-plugin-staging && cd my-plugin-staging
git clone --depth 1 --branch v1.0.0 https://github.com/you/my-plugin
mv my-plugin my-plugin-1.0.0-src
cd my-plugin-1.0.0-src
# strip anything that shouldn't ship: .git, CI config, dev-only deps
rm -rf .git .github tests docs-dev
cd ..
zip -r my-plugin-1.0.0.zip my-plugin-1.0.0-src
```

Your zip must have **exactly one top-level directory whose name is
your plugin id**. `PluginInstaller::assertSafeZipEntry()` refuses
anything else.

Compute the SHA-256 once:

```bash
shasum -a 256 my-plugin-1.0.0.zip
```

Upload to an immutable HTTPS URL (GitHub release asset, S3 object with
versioning, your own CDN). Do **not** use short URLs or pastebins —
the approved registry refuses non-HTTPS URLs and prefers URLs you
control.

---

## 8. Open the approved-plugins.json PR

Fork the ChurchCRM repo and add an entry to
`src/plugins/approved-plugins.json`:

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "downloadUrl": "https://github.com/you/my-plugin/releases/download/v1.0.0/my-plugin-1.0.0.zip",
    "sha256": "<the hex digest from step 7>",
    "risk": "medium",
    "riskSummary": "POSTs member email + name to api.example.org on Person create/update hooks. Stores the upstream API key in plugin config.",
    "permissions": [
        "network.outbound",
        "secrets.store",
        "hooks.person",
        "hooks.family"
    ],
    "minimumCRMVersion": "7.1.0",
    "author": "Your Name",
    "homepage": "https://github.com/you/my-plugin",
    "reviewedAt": "YYYY-MM-DD",
    "notes": "Outbound HTTPS only to api.example.org. No DB writes outside plugin config."
}
```

In the PR body, include:

1. **Capability inventory table** from
   [`plugin-security-scan.md → Permission / capability inventory`](./plugin-security-scan.md#4-permission--capability-inventory).
   Every cell with evidence.
2. **Static-analysis summary** — the output of the ripgrep/PHPStan/
   Psalm commands from step 6.
3. **Runtime smoke test results** — did you install, enable, and
   exercise every menu item / hook / route in a Docker CRM instance?
4. **VDP URL** — the Vulnerability Disclosure Policy on your
   plugin's homepage. Required for every community plugin.

A ChurchCRM maintainer will re-run the scan and either approve
(single reviewer for low/medium risk; two reviewers for high risk),
request changes, or reject. Expect the review to focus on the
behaviour-to-tag mapping and the risk summary wording — those are
the fields that end users see on the install screen.

---

## 9. Migrating an existing plugin into the approved flow

If you already have a plugin running in the wild that was installed
by hand into `src/plugins/community/` (pre-URL-installer), you do not
need to rewrite it — you need to bring it into compliance:

1. Run sections 0, 6, 7 of this skill against the current tree.
2. Fix anything the security scan surfaces, especially:
   - `gettext()` / `_()` calls that need to become `dgettext()`.
   - Strings that live in the core POeditor workflow — move them
     into `{plugin}/locale/textdomain/...` and rebuild with
     `xgettext --keyword=dgettext:2`.
   - Any write outside the plugin directory.
   - Any dangerous PHP sinks.
3. Cut a new tagged release.
4. Open the approved-plugins.json PR per section 8.

Once approved, users who installed the old hand-copied version must
uninstall (delete `src/plugins/community/{id}/`) and reinstall via
the URL installer. The installer refuses to overwrite an existing
directory, so the delete step is required.

---

## 10. Keeping a plugin approved

- **Every version bump is re-reviewed from scratch.** An older
  version's review does not carry forward.
- **Update the `sha256` in your PR** every release — it is the only
  way the installer knows which bytes you reviewed.
- **Drop the entry yourself** if a vulnerability is found in your
  plugin, and notify the forum. Do not wait for a maintainer to
  remove it.
- **Publish a `SECURITY.md`** on your plugin repo with a disclosure
  contact and response SLA. The 2026 EU plugin-security rules
  require one for commercial plugins and it is a strong signal of
  maintenance health for community plugins.

---

## 11. Where things live

- `src/plugins/community/` — runtime home for installed community
  plugins (never committed by you; the installer owns this path).
- `src/plugins/approved-plugins.json` — the allowlist your PR updates.
- `src/ChurchCRM/Plugin/PluginInstaller.php` — install-time enforcement.
- `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php` — loads and
  validates your entry.
- `src/ChurchCRM/Plugin/PluginLocalization.php` — loads plugin-local
  translations.

---

**Related skills:**

- [Plugin Development](./plugin-development.md) — full technical reference
- [Plugin System](./plugin-system.md) — runtime architecture
- [Plugin Security Scan](./plugin-security-scan.md) — the review checklist
- [Plugin Compliance (Admin Audit)](./plugin-compliance.md) — how site
  admins vet your plugin after release
- [Plugin Migration](./plugin-migration.md) — **core plugins only**,
  not relevant to community plugin authors
