<!-- staged: 2026-04-14 — destined for docs.churchcrm.io wiki/plugins/installing-community-plugins.md -->

# Installing Community Plugins

Community plugins extend ChurchCRM with third-party integrations. Every
community plugin has been **reviewed by the ChurchCRM maintainers** and
is listed in a checksum-pinned allowlist — ChurchCRM refuses to install anything
that isn't on that list. This page walks you through the install flow
and explains what each safety check is doing for you.

> **Before you install anything new,** read
> [Plugin Security & Compliance](./plugin-security-and-compliance.md) so
> you know how to interpret the risk level you'll see on the install
> screen.

---

## Who can install plugins?

Only ChurchCRM users with the **Admin role** can install, enable, or
configure plugins. The installer calls the admin-only API routes under
`/plugins/api/…`, which are guarded by
`AdminRoleAuthMiddleware` at the server level.

---

## Step 1 — Review the approved list

From **Admin → Plugins → Browse community plugins**, or directly via
the API:

```bash
curl -s -H "x-api-key: $ADMIN_API_KEY" \
  https://your-crm.example.org/plugins/api/approved | jq
```

Each entry shows:

- **Name** and **version**
- **Risk level** — `low`, `medium`, or `high`
- **Risk summary** — one sentence explaining the worst thing the plugin
  is allowed to do
- **Permissions** — the capability tags the plugin declared
  (`network.outbound`, `secrets.store`, `hooks.person`, etc.)
- **Minimum ChurchCRM version**
- **Reviewed-at date**
- **Homepage**, **author**, and optional notes

If the risk level or risk summary make you uncomfortable, **don't
install the plugin**. You can always file a question with the
maintainers first.

---

## Step 2 — Install

From **Admin → Plugins**, click **Install** next to a plugin in the
approved list, or POST to the install API directly:

```bash
curl -s -X POST \
  -H "x-api-key: $ADMIN_API_KEY" \
  -H "content-type: application/json" \
  -d '{"downloadUrl":"https://example.org/releases/example-plugin-1.0.0.zip"}' \
  https://your-crm.example.org/plugins/api/plugins/install
```

ChurchCRM will now run a pipeline of safety checks. Every one of them
has to pass, in order:

1. **Allowlist check.** The `downloadUrl` must match an entry in
   `src/plugins/approved-plugins.json` exactly. Anything else is
   refused.
2. **Version check.** If the entry declares `minimumCRMVersion`, your
   installed ChurchCRM version has to meet it.
3. **Duplicate check.** If `src/plugins/community/{id}/` already
   exists, the install is refused — community plugins are never
   overwritten, only installed fresh. To upgrade, disable and remove
   the current copy first.
4. **HTTPS download.** ChurchCRM downloads the zip using cURL with TLS
   verification, follows at most four redirects (HTTPS only), and
   aborts if the transfer exceeds 20 MB.
5. **Checksum verification.** The downloaded bytes' SHA-256 is
   compared to the `sha256` in the registry. If they don't match
   byte-for-byte, the install is refused before the archive is even
   opened. This protects you against a compromised mirror or a
   man-in-the-middle attack.
6. **Archive validation.** The zip is walked entry by entry, refusing
   anything that looks like a ZIP Slip attempt (`..`, absolute paths,
   drive letters), anything with an unsafe extension (`.phar`,
   `.phtml`, `.sh`, `.exe`, `.so`, `.dll`), any symlink, and any
   hidden file other than `.editorconfig` / `.gitattributes`. The
   archive must contain exactly one top-level directory, named for
   the plugin id.
7. **Size sanity.** Total uncompressed size is capped at 80 MB and
   total entry count at 2000 to block zip bombs.
8. **Manifest cross-check.** After extraction, `plugin.json` must
   declare the same `id`, `version`, and `type: "community"` as the
   registry entry — if any of those have drifted, the install is
   refused.

Only once all of those checks pass does the new directory appear under
`src/plugins/community/{id}/`. If any check fails, ChurchCRM logs it
and returns a short, actionable error message.

---

## Step 3 — Review the extracted files

The installer does **not** auto-enable the plugin. That is
deliberate — it gives you one more chance to look at what was
extracted before it starts running on your server.

From the command line:

```bash
ls src/plugins/community/{id}
cat src/plugins/community/{id}/plugin.json
```

Look for:

- A `plugin.json` that matches what you saw in the registry entry.
- A `src/` directory with a single main class file.
- Optional `routes/`, `views/`, `locale/`, and `help.json` files.
- **Nothing else** — no stray config files, no credentials, no
  embedded binaries.

---

## Step 4 — Enable

From **Admin → Plugins**, click **Enable** on the newly installed
plugin. ChurchCRM will:

1. Re-check that the plugin's `dependencies` are met.
2. Re-check the ChurchCRM version.
3. Call the plugin's `activate()` hook.
4. Start loading its routes on subsequent requests.

If the plugin exposes settings (API keys, default values), fill them
in now. Password fields are masked — entering nothing leaves the
stored value in place.

---

## Step 5 — Test

Every plugin with a `hasTest: true` manifest has a **Test Connection**
button on its settings page. Click it before relying on the plugin in
production — it calls `testWithSettings()` with the current form
values and either confirms success or returns the upstream error
message.

---

## Installing a plugin that isn't on the approved list

Two buttons live next to the community plugin header: **Browse
Approved** (the vetted list) and **Install from URL**. Use "Install
from URL" when:

- You are the plugin's own developer and want to test a build before
  submitting it to the approved list.
- You are running a private plugin that will never be published
  publicly (custom integrations for one parish).
- You need to run an experimental plugin that isn't ready for
  allowlisting yet.

The **Install from URL** modal requires three things:

1. **Plugin zip URL** — HTTPS only, ideally an immutable release
   artifact (GitHub release asset, versioned S3 object).
2. **SHA-256** — the plugin author must publish this alongside the
   release. ChurchCRM refuses the install if the downloaded bytes
   don't match byte-for-byte.
3. **Plugin id** — must match the top-level directory in the zip
   and the `id` field in `plugin.json`.

The installer runs the same hardening pipeline as the verified path
(TLS, 20 MB cap, ZIP Slip checks, extension allowlist, manifest
cross-check) — the **only** difference is that the URL does not
have to be on the approved list.

The resulting plugin is flagged as **unverified** everywhere in the
admin UI. Its card shows a yellow banner, there is no
risk/permissions panel, and the plugin is clearly distinguished
from verified plugins on the list. You can still enable it, but you
accept responsibility for whatever it does.

> **If you install an unverified plugin and the URL later appears
> in the approved list** (same URL, same SHA-256), the installer
> silently short-circuits into the verified path on the next
> install attempt. Your running plugin stays as-is until you
> reinstall.

## Deleting a community plugin

Click the trash icon on any community plugin card to uninstall it.
This is the only way to upgrade a community plugin — the installer
refuses to overwrite an existing directory.

The uninstall flow:

1. Refuses core plugins (they can only be disabled).
2. Calls the plugin's `deactivate()` and `uninstall()` lifecycle
   hooks so it can clean up webhooks, scheduled jobs, etc.
3. Deletes `src/plugins/community/{id}/` from disk.
4. Clears every `plugin.{id}.*` row from the config table,
   including any stored credentials.

Nothing in SystemConfig survives an uninstall — re-installing the
same plugin is a clean-slate install, not an upgrade.

## If a plugin is quarantined

If a plugin throws while loading or booting, ChurchCRM automatically
**quarantines** it:

- Its card shows a yellow "Quarantined" badge and border.
- It stops running immediately — even with `enabled=1` it will not
  load on subsequent requests.
- The card banner shows the exception message so you can see what
  went wrong.
- You cannot re-enable it until you explicitly clear the quarantine.

Typical causes: a missing PHP extension, a syntax error in the
plugin after an upgrade, or the registry entry being revoked
upstream. Fix the underlying issue, then click the shield icon on
the plugin card to clear the quarantine. You will still have to
click **Enable** afterwards.

## If something goes wrong

**Install was refused with a checksum error.**
Someone tampered with the download mirror, or the upstream maintainer
republished the release artifact without bumping the version. Report
it to the ChurchCRM forum and do not retry.

**Install was refused because the plugin is "already installed".**
Disable the current copy from **Admin → Plugins**, delete the
`src/plugins/community/{id}/` directory, and re-run the install. This
is the official upgrade path.

**The plugin installed but Enable fails with a dependency error.**
Check the plugin's `dependencies` list — another plugin needs to be
installed and enabled first.

**A plugin is quietly misbehaving.**
Go read [Plugin Security & Compliance → Compliance scan](./plugin-security-and-compliance.md#compliance-scan)
for the audit commands you can run against an already-installed
plugin.

**A plugin disappeared from the approved list.**
That means the ChurchCRM maintainers revoked it, usually because of a
disclosed vulnerability. Disable it immediately from **Admin →
Plugins** and wait for a fixed version to re-appear.
