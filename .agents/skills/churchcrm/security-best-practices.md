---
title: Security Best Practices
intent: ChurchCRM security rules. Read the named source files for APIs.
---

# Security Best Practices

Implementations: `src/ChurchCRM/Utils/InputUtils.php`, `src/ChurchCRM/Utils/CSRFUtils.php`, `src/ChurchCRM/Utils/RedirectUtils.php`, `src/ChurchCRM/Slim/Middleware/CSRFMiddleware.php`.
AuthZ: `authorization-security.md`.
Incoming vuln report: `security-report-triage.md`.

## Always

- Escape on output: `InputUtils::escapeHTML()` / `escapeAttribute()`. For PDF/template rendering, verify the actual renderer configuration and follow `reports.md`; template interpolation alone does not establish escaping.
- Sanitize on input: `sanitizeText()` (no HTML) or `sanitizeHTML()` (Quill / rich text)
- JSON in `<script>`: `InputUtils::jsonEncodeForScript()`
- CSP is enforced by default (`src/Include/Header-Security.php`; the `bEnforceCSP` feature toggle drops it to report-only as an escape hatch). Our code never relies on report-only. No inline event attributes (`onclick=`, `onchange=`), no `javascript:` URLs, no `eval` / `new Function` in our code (`'unsafe-eval'` is there only for heic2any): bind events in the page's `webpack/` entry or with the `data-auto-submit` / `data-print` hooks in `Footer.js`. An inline `<script>` carries `nonce="<?= SystemURLs::getCSPNonce() ?>"`. A new third-party origin is added to that file's directive list. Cypress strips CSP headers, so check script changes in a real browser console for CSP violations
- DB: ORM with bound values
- IDs: `(int)` or `InputUtils::filterInt()`
- Redirects: `RedirectUtils::*`
- TLS verify on outbound HTTPS
- `target="_blank"` → `rel="noopener noreferrer"`
- State-changing routes: CSRF. Prefer one `CSRFMiddleware` on a Slim **group** (after body parsing). App-level CSRF runs before body parsing and 403s every POST
- Deletes: POST + confirm
- Destructive IDs: `$_GET` on GET, `$_POST` on POST
- Role checks on the same page as the write

## Review

Security findings are a hard block (`maintainer-review-gates.md`). Fix in the same PR.
