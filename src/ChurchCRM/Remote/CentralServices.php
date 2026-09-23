<?php

namespace ChurchCRM\Remote;

/**
 * Hardcoded URLs for ChurchCRM maintainer-managed remote services.
 *
 * These are NOT user-configurable — they are set by the ChurchCRM
 * maintainers and shipped with the software.
 *
 * Broadcast notifications live on the `Notifications` orphan branch so
 * maintainers can push updates without requiring an install upgrade.
 * The approved plugin registry is also fetched from that branch when
 * present; the product ships `src/plugins/approved-plugins.json` as a
 * fallback when the remote file is missing (the old `External` branch
 * was deleted).
 *
 * ── HOW TO UPDATE THE HOSTED FILES ───────────────────────────────────
 *
 * 1. Check out the Notifications branch as a worktree:
 *
 *      git fetch origin Notifications
 *      git worktree add /tmp/crm-notifications Notifications
 *      cd /tmp/crm-notifications
 *
 * 2. Edit notifications.json  OR  approved-plugins.json
 *
 * 3. Commit and push:
 *
 *      git add notifications.json approved-plugins.json
 *      git commit -m "chore(external): describe your change"
 *      git push origin Notifications
 *
 * Changes go live to ALL installs on the next user login — no deploy needed.
 * The notifications TTL (default 300 s) controls how long previous fetch
 * results are cached in each session.
 *
 * See .agents/skills/churchcrm/plugin-security-scan.md for the full review
 * checklist required before adding a plugin to approved-plugins.json.
 * ─────────────────────────────────────────────────────────────────────
 */
class CentralServices
{
    /**
     * Maintainer broadcast messages shown to all installs.
     *
     * JSON schema:
     * {
     *   "TTL": 300,
     *   "messages": [
     *     {
     *       "id":                   "unique-never-reuse",
     *       "title":                "Short title",
     *       "message":              "Body text",
     *       "type":                 "info|warning|danger|success",
     *       "icon":                 "tabler icon name, e.g. info-circle",
     *       "link":                 "https://...",
     *       "targetVersionPattern": "7.2.*  (or * for all versions)",
     *       "adminOnly":            false
     *     }
     *   ]
     * }
     */
    public const NOTIFICATIONS_URL = 'https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/notifications.json';

    /**
     * Registry of community plugins approved for URL-based install.
     * Adding an entry requires a maintainer-reviewed PR — see approved-plugins.json schema.
     * Hosted next to notifications.json; ApprovedPluginRegistry falls back to the
     * copy shipped in src/plugins/approved-plugins.json when this URL 404s.
     */
    public const PLUGIN_REGISTRY_URL = 'https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/approved-plugins.json';
}
