# ChurchCRM Development Memory

Durable operational rules for maintaining ChurchCRM.

---

## Product claim verification

The product codebase is the source of truth for what ChurchCRM does. Verify a feature claim before it appears in marketing copy, blog posts, the README or documentation.

1. `orm/schema.xml`: the claimed fields and tables exist.
2. `src/plugins/core/`: the plugin exposes the claimed capability.
3. `.github/workflows/` and `cypress/`: tests cover the feature.
4. docs.churchcrm.io: the capability is documented for users.

When the claim and the product differ, document the gap, reword the claim to match the code, and update the docs if the feature exists but is underdocumented. Verify again after refactors and after schema or plugin changes.

---

## Hosted remote config

`CentralServices` fetches two files from the `External` branch:

- `https://raw.githubusercontent.com/ChurchCRM/CRM/External/approved-plugins.json`
- `https://raw.githubusercontent.com/ChurchCRM/CRM/External/notifications.json`

`External` is an orphan hosting branch holding root JSON only; it has no PR into `master`.

- Keep the `External` and `Notifications` branches; the ruleset ([23858586](https://github.com/ChurchCRM/CRM/rules/23858586)) blocks their deletion and force-push, and a 422 on a delete attempt means the ruleset is working.
- Change the registry with a PR whose base is `External`, editing root `approved-plugins.json`.
- Keep `hello-world` on the allowlist while `community-plugin-lifecycle.spec.js` installs it.

Agent cards: `.agents/skills/churchcrm/hosted-remote-config.md`, `plugin-registry.md`, `repo-health.md`.

---

## Release notes and publishing

The how-to is in `.agents/skills/churchcrm/release-notes.md`; the runbook is `docs/release-process.md`.

- Edit a draft release with `--tag`: `gh release edit <tag> --tag <version> --notes-file …`. A draft has no git tag yet, and installs read the version from `tag_name` (`ChurchCRMRelease.php`). Check `gh release view <version> --json tagName,isDraft` before publishing.
- To repair a release stuck on a placeholder `untagged-<hash>` tag: create the real tag on the release commit, move the release with `gh release edit untagged-… --tag <version>`, verify, then delete the stray tag. Re-run `release-bookkeeping.yml` with `tag=<version>`.
- Merge the `Start <next> release` PR before publishing; bookkeeping reads the next version from `master`'s `package.json`.
- Apply notes from the approved branch: `git show origin/<branch>:changelog/<tag>.md`.
- Weight notes by reach: a change seen on every screen is a feature; a capability few use yet gets one line.
- Mention work in other repos only when it directly helps users of the release (artwork shipped in the app, a docs guide for a shipped feature), and add one "Documentation Caught Up" line when the docs were updated.
- Refresh screenshots and videos after publishing so they match the downloadable version.
- Set `DOCS_RELEASE_TOKEN` so Actions can read `ChurchCRM/marketing` and write docs milestones; run the context script locally to include marketing contributors.

---

## Related skills

- [Plugin System](.agents/skills/churchcrm/plugin-system.md): how plugins define features
- [Database Operations](.agents/skills/churchcrm/database-operations.md): ORM schema queries
- [API Development](.agents/skills/churchcrm/api-development.md): feature exposure through APIs
