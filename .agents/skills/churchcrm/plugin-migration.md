---
title: "Plugin Migration Guidelines"
intent: "Checklist and patterns to migrate or update plugins for route and API changes"
tags: ["plugins","migration","compatibility"]
prereqs: ["plugin-development.md","api-compatibility-and-deprecation.md"]
complexity: "intermediate"
---

# Plugin Migration Guidelines

Checklist for plugin authors when core routes or APIs move:
- Update `plugin.json` manifest if new entry points are required.
- Avoid relying on hardcoded route paths; use `SystemURLs::getRootPath()` + configured endpoints.
- If core provides shims, prefer calling official service methods rather than directly querying DB.

Compatibility:
- Document breaking changes and provide examples for plugin updates.
- Add tests that run plugin code against a staging instance with new routes.

Security:
- Ensure plugin route middleware enforces the same auth checks as the core route it replaces.
