---
title: Security Best Practices
intent: ChurchCRM security rules. Read the named source files for APIs.
---

# Security Best Practices

Implementations: `src/ChurchCRM/Utils/InputUtils.php`, `src/ChurchCRM/Utils/CSRFUtils.php`, `src/ChurchCRM/Utils/RedirectUtils.php`, `src/ChurchCRM/Slim/Middleware/CSRFMiddleware.php`.
AuthZ: `authorization-security.md`.
Incoming vuln report: `security-report-triage.md`.

## Always

- Escape on output: `InputUtils::escapeHTML()` / `escapeAttribute()`
- Sanitize on input: `sanitizeText()` (no HTML) or `sanitizeHTML()` (Quill / rich text)
- JSON in `<script>`: `InputUtils::jsonEncodeForScript()` — not raw `json_encode()`
- DB: ORM + bound values. No new `RunQuery()` or string-built SQL
- IDs: `(int)` or `InputUtils::filterInt()`
- Redirects: `RedirectUtils::*` — not raw `header('Location')`
- TLS verify on outbound HTTPS
- `target="_blank"` → `rel="noopener noreferrer"`
- State-changing routes: CSRF. Prefer one `CSRFMiddleware` on a Slim **group** (after body parsing). App-level CSRF runs too early and 403s every POST
- Deletes: POST + confirm. Never delete on GET
- Destructive IDs: `$_GET` on GET, `$_POST` on POST — not `$_REQUEST`
- Role checks on the same page as the write

## Review

Security findings are a hard block (`maintainer-review-gates.md`). Fix in the same PR.
