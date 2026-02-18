---
title: "API Compatibility & Deprecation"
intent: "Patterns for maintaining backward compatibility, adding shims, and deprecation timelines"
tags: ["api","compatibility","deprecation","shims"]
prereqs: ["api-development.md","routing-architecture.md"]
complexity: "intermediate"
---

# API Compatibility & Deprecation

Purpose: Provide a safe strategy to introduce new endpoints or move existing ones while preserving compatibility for external clients and plugins.

Key patterns:
- **Compatibility shims:** Keep original `/api` endpoints and forward to new service implementations. Add `Deprecation` or custom header `X-Deprecated-Resource: groups/<app>`.
- **Versioning:** Use `/v2/` or `/api/v2/` for breaking changes; prefer non-breaking additive changes when possible.
- **Deprecation timeline:** Announce deprecation in-code (header), update docs, and provide a 3-release overlap before removing old endpoints.
- **Client notices:** Return `Warning` or `Deprecation` headers with migration suggestions.

Implementation checklist:
- Add forwarding stubs in `src/api/routes/` that call new `GroupService` methods.
- Log usage of deprecated endpoints via `LoggerUtils::getAppLogger()` for migration metrics.
- Add automated integration tests that assert shim behavior matches legacy responses.
- Provide a sample migration guide snippet for plugin authors.

Risks & mitigations:
- **Broken clients:** Keep shims for at least 3 releases; communicate in release notes.
- **Security drift:** Ensure middleware and permission checks are identical in shims.
