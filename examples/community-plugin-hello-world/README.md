# Hello World — Community Plugin Example

This is a **reference plugin**. It lives under `examples/` and is
intentionally not loaded at runtime — ChurchCRM only discovers plugins
under `src/plugins/core/` and `src/plugins/community/`. Use
[`scripts/create-plugin.php`](../../scripts/create-plugin.php) to
scaffold a fresh plugin that is based on this template:

```bash
php scripts/create-plugin.php my-plugin
```

That command will copy this directory into
`src/plugins/community/my-plugin/`, rewrite the plugin id, namespace,
and class name, and leave you with a runnable starting point.

## What this example demonstrates

- **Minimal `plugin.json`** with a single text setting and one hook.
- **`AbstractPlugin` subclass** that subscribes to
  `Hooks::PERSON_CREATED` in `boot()`.
- **Plugin-local gettext** via `dgettext('hello-world', '…')`. Never
  call plain `gettext()` or `_()` from a plugin — those go to the core
  `messages` textdomain and your translations will not resolve.
- **Plugin-local i18next JSON** at `locale/i18n/en_US.json` — a flat
  `key → string` map that the frontend reads from
  `window.CRM.plugins['hello-world'].i18n`.
- **Sandboxed config access** through `$this->getConfigValue()` /
  `$this->setConfigValue()`. The base class enforces that plugins can
  only read or write `plugin.hello-world.*` keys.

## Directory layout

```
examples/community-plugin-hello-world/
├── plugin.json
├── src/
│   └── HelloWorldPlugin.php
└── locale/
    └── i18n/
        └── en_US.json
```

A real plugin would additionally ship:

- `routes/routes.php` — Slim routes under `/plugins/{id}/...`
- `views/*.php` — view templates
- `locale/textdomain/{locale}/LC_MESSAGES/{id}.mo` — compiled gettext
  files (build these with `xgettext --keyword=dgettext:2` + `msgfmt`)
- `help.json` — user-facing help content rendered in the settings
  modal

## Related skills

- [`plugin-development.md`](../../.agents/skills/churchcrm/plugin-development.md)
  — full technical reference and the allow/forbid capability contract.
- [`plugin-create.md`](../../.agents/skills/churchcrm/plugin-create.md)
  — community plugin quickstart + submission flow.
- [`plugin-security-scan.md`](../../.agents/skills/churchcrm/plugin-security-scan.md)
  — the review checklist every approved plugin must pass. Run it
  against your own plugin before opening a PR.
