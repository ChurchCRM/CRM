# Notifications — legacy hosted config

This is an **orphan hosting branch**, not application code. The live host is the `External` branch.

## Who reads it

ChurchCRM **7.2.2 and older** fetch `notifications.json` from this branch at login (`sNotificationsURL`, default `https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/notifications.json`).

ChurchCRM **7.3.0 and newer** (released 2026-04-25) read `External` and never touch this branch.

`notifications.json` is the only file. Keep it identical to `External/notifications.json`. The plugin registry (`approved-plugins.json`) lives on `External` only.

## Keeping it current

Not edited on its own. When a notice is added to or removed from `External/notifications.json`, copy the file here in the same change so old installs see it too.

## Do not delete

Deleting it makes old installs log `Failed to fetch remote notifications` and show no broadcast notices. Nothing else breaks, but this branch is the only way to reach those installs.

GitHub ruleset **Protect External and Notifications** (id `23858586`) blocks deletion and force-push. Do not treat the branch as stale because it has no open PR.

Changes: open a PR with **base `Notifications`**.
