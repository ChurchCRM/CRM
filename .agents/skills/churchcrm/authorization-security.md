---
title: Authorization & Security
intent: Role checks. Read the middleware classes, do not copy the matrix.
---

# Authorization

Middleware: `src/ChurchCRM/Slim/Middleware/Request/Auth/`.
Session user: `AuthenticationManager`.
Page-level patterns: existing Slim routes under `src/admin/routes/`, `src/api/routes/`, `src/finance/`.

- Every new protected route gets the same role middleware as its neighbors
- Finance writes need finance + the matching records permission
- Deletes need the delete-records (and finance, when it is money) checks plus CSRF — `security-best-practices.md`
- Do not invent a new permission flag unless a maintainer asked for it
