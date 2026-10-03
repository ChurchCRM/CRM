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

## Admin masquerade ("Login as User", #9843)

`ImpersonationService` bypasses every credential check. Keep these invariants:

- Session payload only through `AuthenticationManager::establishSessionAsUser()` (shared with login). No `session_regenerate_id()` on start/exit (in-flight XHRs would 401 to the login page), no last-login/login-count stamps, no login hooks
- `$_SESSION['impersonator']` is the single source of truth; both `Include/Header.php` and `Include/HeaderNotLoggedIn.php` read it for the exit banner, and `/user/impersonate/exit` stays in `AuthMiddleware::isAuthFlowExemptPath()`
- Routes: `SessionOnlyMiddleware` (API keys are accepted on MVC routes otherwise); `NoActiveMasqueradeMiddleware` must run before `AdminRoleAuthMiddleware` (Slim runs `->add()` in reverse)
- Never impersonate another administrator
- Every identity change writes an auth-log line naming both user ids (`Masquerade started/ended/aborted`)

## Review

Security findings are a hard block (`maintainer-review-gates.md`). Fix in the same PR.
