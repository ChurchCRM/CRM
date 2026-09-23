# External — hosted remote config

This is an **orphan hosting branch**, not application code.

Shipped ChurchCRM reads these files at login / first plugin-page visit:

| File | Consumed by |
|------|-------------|
| `approved-plugins.json` | `CentralServices::PLUGIN_REGISTRY_URL` → `ApprovedPluginRegistry` |
| `notifications.json` | `CentralServices::NOTIFICATIONS_URL` |

Raw URLs:

- https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json
- https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json

## Do not delete this branch

Deleting it 404s every install and turns UI shard 2 red (`community-plugin-lifecycle.spec.js`). That happened on 21 Sep 2026 when a PR-less prune removed the ref (#9961, #9969).

GitHub ruleset **Protect External and Notifications** (id `23858586`) blocks deletion and force-push. If a delete returns 422, leave the branch alone.

`Notifications` is the older broadcast-only host. Keep it too. Do not treat either ref as stale just because it has no open PR.

## How to change the registry

1. Open a PR with **base `External`**.
2. Edit root `approved-plugins.json` (not `src/plugins/…` on master).
3. Required entry fields: `id`, `name`, `version`, `downloadUrl` (HTTPS), `sha256` (64 hex), `risk` (`low`\|`medium`\|`high`), `riskSummary`.
4. Follow `.agents/skills/churchcrm/plugin-security-scan.md` on master.

Keep **hello-world** first unless `cypress/e2e/ui/plugins/community-plugin-lifecycle.spec.js` is retargeted. That spec installs by `data-plugin-id="hello-world"`.

Current allowlist: hello-world 1.0.1, meeting-outlines 1.0.2.
