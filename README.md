# ChurchCRM External Branch

This branch hosts files that ChurchCRM instances fetch at runtime without requiring a new release.

## approved-plugins.json

The authoritative registry of community plugins that ChurchCRM admins can install via the URL-based plugin installer.

Running instances fetch this file from:
```
https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json
```

The fetch happens once per admin login session, on the first visit to **Admin → Plugins**. No CRM release is needed to add or update a plugin entry.

### Adding a plugin

Open a pull request against this branch (`External`) that adds an entry to `approved-plugins.json`. Every entry must pass the full review checklist in [`.agents/skills/churchcrm/plugin-security-scan.md`](.agents/skills/churchcrm/plugin-security-scan.md) before merging.

Required fields per entry:

| Field | Description |
|-------|-------------|
| `id` | kebab-case, must match `plugin.json` id |
| `name` | Display name |
| `version` | semver, must match `plugin.json` version |
| `downloadUrl` | HTTPS only, must be an immutable release artifact |
| `sha256` | 64-hex SHA-256 of the exact zip bytes |
| `risk` | `low` \| `medium` \| `high` — see scan checklist § 4a |
| `riskSummary` | One sentence describing the worst capability the plugin exercises |
| `permissions` | Array of capability tags (see `ApprovedPluginRegistry::KNOWN_PERMISSIONS`) |

High-risk entries (`fs.write + network.outbound`, `hooks.financial + network.outbound`) require **two maintainer reviews** before merging.

### Updating a plugin version

Bump `version`, `downloadUrl`, and `sha256` in a new PR. Every version is reviewed fresh — the previous review does not carry over.

### Revoking a plugin

Remove the entry from `approved-plugins.json` in a PR. Instances will stop allowing new installs immediately. Admins with the plugin already installed will see it flagged as unverified on next page load.
