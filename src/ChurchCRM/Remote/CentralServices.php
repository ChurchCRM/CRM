<?php

namespace ChurchCRM\Remote;

/**
 * Hardcoded URLs for ChurchCRM maintainer-managed remote services.
 *
 * These are NOT user-configurable — they are set by the ChurchCRM
 * maintainers and shipped with the software. All URLs point to files
 * on the `External` orphan branch of ChurchCRM/CRM so maintainers
 * can push updates without requiring an install upgrade.
 *
 * ── HOW TO UPDATE THE HOSTED FILES ───────────────────────────────────
 *
 * 1. Check out the External branch as a worktree:
 *
 *      git fetch origin External
 *      git worktree add /tmp/crm-external External
 *      cd /tmp/crm-external
 *
 * 2. Edit notifications.json  OR  approved-plugins.json
 *
 * 3. Commit and push:
 *
 *      git add notifications.json approved-plugins.json
 *      git commit -m "chore(external): describe your change"
 *      git push origin External
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
    public const NOTIFICATIONS_URL = 'https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json';

    /**
     * Registry of community plugins approved for URL-based install.
     * Adding an entry requires a maintainer-reviewed PR — see approved-plugins.json schema.
     */
    public const PLUGIN_REGISTRY_URL = 'https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json';
}
