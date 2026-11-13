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
  validates a positive family ID, year and a `tax_email_{year}` CSRF token.
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
  The `send()` docblock promises one `email_log_eml` row per recipient.
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

- **Recording a match:** **not determined**. The allowed statement path reads
  payments; it does not show payment entry, payer attribution or a company-match
  field. `src/ChurchCRM/Service/GivingStatementService.php`,
  `paymentsByFamily()`, delegates eligibility to
  `PledgeQuery::filterForTaxReport()`, whose implementation is outside scope.
- **Conditional inclusion:** if a match reaches the employee family's returned
  payment group, its amount contributes to that family's total.
  `src/ChurchCRM/Service/GivingStatementService.php`,
  `paymentsByFamily()`, groups returned payments by `getFamId()`;
  `totals()` sums `getAmount()` and `getNondeductible()`; `families()`
  exposes deductible as total minus non-deductible. Those methods contain no
  employer-match exclusion or separate payer/employee attribution.
- Therefore match money **can contribute arithmetically** when returned under
  that family with a deductible portion; whether current entry/query rules
  actually admit such a payment is **not determined**.
  `src/ChurchCRM/Service/GivingStatementService.php`,
  `paymentsByFamily()`, `totals()`, `families()`.
  The email handler adds no match-specific check before rendering
  (`src/finance/routes/reports.php`, `POST /giving-statements/email`).
  This is a conditional data-flow finding, not a tax-treatment decision.

## 4. Misleading copy

**Not determined** for dashboard, settings and page copy: their source text is
outside the permitted read scope. No before/after wording or copy fix is claimed.
The inspected `src/finance/routes/reports.php`,
`POST /giving-statements/email` handler, contains redirects rather than that UI
copy; `src/ChurchCRM/Emails/GivingStatementEmail.php`, `__construct()`, says
“Please find your %s giving statement from %s attached to this email.”
That attachment claim matches its `addStringAttachment()` call. No factually
wrong user-facing wording was established in the scoped code.

A copy follow-up needs permission to inspect the actual dashboard/settings/page
files before quoting or changing them; the issue description is not source proof.

## 5. Decisions and follow-ups

Proposed issues only; product decisions remain with maintainers.
### Choose explicit recipients for family giving statements

Decide whether statements go to a designated recipient, selected adults or a
reviewed address list, including treatment of children, work addresses and
consent. Require finance to understand recipients before sending. Motivation:
`src/ChurchCRM/model/ChurchCRM/Family.php`, `Family::getEmails()`, collects
members' personal/work addresses without its own role/age/consent selection.
### Decide calendar-year donor acknowledgment versus fiscal reporting

Agree a distinct donor acknowledgment period and label, including non-January
fiscal configurations, before changing sending. Keep label, payment range and
filename understandable together. Motivation: `src/ChurchCRM/Utils/FiscalYearUtils.php`,
`getFiscalYearDatesForCalendarYear()`, and
`src/ChurchCRM/Service/GivingStatementService.php`, `criteriaFromQuery()`,
currently use the fiscal end-year; verify US requirements separately.
### Define employer-match attribution and statement treatment

Inspect payment entry and `filterForTaxReport()` in a separately scoped audit,
then decide how payer versus employee attribution and deductible treatment should
be represented. Add acceptance cases for employee gifts and company matches.
Motivation: `src/ChurchCRM/Service/GivingStatementService.php`, `families()`
and `totals()`, calculate deductible from returned amounts without a match
distinction; this audit cannot establish upstream safeguards.
### Decide bulk statement delivery and audit queue/history support

First decide whether bulk sending is wanted. If approved, investigate queue PDF
attachment support, recipient selection, families with giving, missing addresses,
retries, duplicate prevention and family/period/sender history. Queue attachment
support and actual history persistence are **not determined** here.
Motivation: `src/finance/routes/reports.php`,
`POST /giving-statements/email`, sends immediately; `src/ChurchCRM/Emails/BaseEmail.php`,
`send()`, `logSend()` and `setQueuedLogRow()`, expose logging/queue hooks,
but do not establish queue storage capabilities.
### Verify statement action copy against available workflows

Inspect dashboard, report index, family page and settings text under expanded
read scope; quote exact source and correct any bulk-email promise before a bulk
feature exists. Motivation: the inspected `src/finance/routes/reports.php`,
`POST /giving-statements/email`, processes one family. Dashboard/page wording
and the year choices offered in the UI remain **not determined** in this audit.
