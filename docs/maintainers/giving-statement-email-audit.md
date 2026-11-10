# Giving-statement email audit

Audit for #10988, part of #11253. Scope: the named statement route, email classes,
recipient method, fiscal-year helper and statement service. Proposals below are
design recommendations, not implemented behaviour.

## 1. Current behaviour

- **Who can send:** `src/ChurchCRM/Service/GivingStatementService.php`,
  `paymentsByFamily()`, documents Finance routes as behind
  `FinanceRoleAuthMiddleware`. Actual middleware wiring and the exact user/role
  eligibility are **not determined** within the permitted read scope.
  `src/finance/routes/reports.php`, `POST /giving-statements/email` handler,
  validates a positive family ID, year and one-time `tax_email_{year}` CSRF token.
- **Recipients:** `src/ChurchCRM/model/ChurchCRM/Family.php`,
  `Family::getEmails()`, iterates `getPeopleSorted()`, collecting each person's
  non-empty `getEmail()` and `getWorkEmail()`; it returns
  `array_values(array_unique($emails))`. It adds no family-level address and
  applies no adult, head/spouse, child, consent or active-status filter itself.
  Thus children's and other members' personal/work addresses can be included
  when returned by `getPeopleSorted()`; that helper's membership filtering is
  **not determined** by the scoped method.
- **One family, immediate:** `src/finance/routes/reports.php`,
  `POST /giving-statements/email` handler, loads one `familyId`, passes it to
  `GivingStatementService::criteriaFromQuery()`, and calls
  `GivingStatementEmail::send()` in the request. Empty recipients redirect with
  `NoEmail`; empty payment groups with `NoData`; send results with
  `TaxEmailSent={year}` or `SendFailed`. This handler has no bulk loop, enqueue
  call, `setQueuedLogRow()` or `setLogContext()`.
- **Message:** `src/finance/routes/reports.php`, the same handler, renders
  `tax-report` through `MpdfRenderer::renderToString()` and passes PDF bytes
  and a generated filename to `GivingStatementEmail::__construct()` in
  `src/ChurchCRM/Emails/GivingStatementEmail.php`. That constructor sets
  plain text (`isHTML(false)`) and calls `addStringAttachment()`;
  `getTokens()` returns an empty array. `src/ChurchCRM/Emails/BaseEmail.php`,
  `__construct()`, adds every recipient as a To address;
  `addStringAttachment()` delegates the in-memory attachment to PHPMailer.
- **History:** `src/ChurchCRM/Emails/BaseEmail.php`, `send()`, calls
  `logSend()` with skipped when email is disabled, otherwise sent/failed after
  the SMTP attempt. `logSend()` delegates to `EmailLogService::logSend()`;
  its persistence/error behaviour is **not determined** without reading that
  service. `getLogKind()` derives `givingstatement`; `logsBody()` is false.
  `setLogContext()` documents address-matching fallback when context is absent.
  Durable row contents, per-recipient persistence, PDF retention and history UI
  presentation are **not determined** here; no explicit family, sender or year
  context is supplied by the handler cited above.

## 2. Year semantics

- `src/finance/routes/reports.php`, `POST /giving-statements/email` handler,
  accepts body `year` as an integer from 1991 through the current fiscal
  end-year. It passes that same integer to criteria, filename and email.
- `src/ChurchCRM/Utils/FiscalYearUtils.php`,
  `getFiscalYearDatesForCalendarYear()` → `getFiscalYearIdForCalendarYear()`
  → `getFiscalYearDatesById()`, interprets it as the fiscal **end-year**.
  With `iFYMonth=1`, 2026 means January–December 2026, label `2026`;
  with `iFYMonth=7`, it means July 2025–June 2026, label `2025/26`.
  `src/ChurchCRM/Service/GivingStatementService.php`,
  `criteriaFromQuery()`, uses those start/end dates for the PDF.
- `src/ChurchCRM/Emails/GivingStatementEmail.php`, `__construct()`,
  derives both subject and body year label from
  `FiscalYearUtils::getFiscalYearDatesForCalendarYear($year)['label']`.
  Email label and PDF period therefore use the same fiscal-year interpretation.
- Against the requested US calendar-year acknowledgment benchmark, the period
  matches January–December only when `iFYMonth=1` (helper cited above).
  US tax requirements and donor-specific treatment are **not determined** from
  these application files; this audit makes no legal-compliance finding.

## 3. Company matching

Pending completion in the next audit push.

## 4. Misleading copy

Pending completion in the next audit push.

## 5. Decisions and follow-ups

Pending completion in the next audit push.
