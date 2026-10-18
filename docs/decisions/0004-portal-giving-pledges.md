# RFC: Member Portal giving pledges

> **BLUF**
> Members record pledges in the Member Portal: a fund, an amount per gift, a schedule and a start date. They can edit or end a pledge and see it beside what they have given. Each pledge is an ordinary `pledge_plg` row of type `Pledge`, so the pledge pages and reports staff use today show it. No money moves: no gateway, deposit, fee, receipt or tax statement is involved. Per-person credit (#9313) and four shared finance fixes come first and are specified here. Online payments are [RFC 0005](0005-online-payments.md), which builds on this RFC. Five decisions are open for maintainers (below).

**Status:** Proposed, open for review; no release commitment  
**Date:** 2026-10-17  
**Owner:** @cwhorton  
**Proposal issue:** [#10033](https://github.com/ChurchCRM/CRM/issues/10033) · **Related:** [#9313](https://github.com/ChurchCRM/CRM/issues/9313) (per-person gifts), [#8977](https://github.com/ChurchCRM/CRM/issues/8977) (Member Portal), [#10550](https://github.com/ChurchCRM/CRM/issues/10550)  
**Numbering:** `0004` is provisional; a maintainer assigns the final number on merge.

## Summary
- A **Giving** page in the Member Portal with one view, **Pledges**. A member adds a pledge, edits it, ends it, and sees pledged, given and remaining for each one.
- A portal pledge is one `pledge_plg` row: one fund, one fiscal year, type `Pledge`. `plg_amount` holds the fiscal-year total, as office pledges do today. One new column, `plg_EndDate`, records an early end.
- "Given" is the recorded payments in the same fund and fiscal year that are credited to the pledge's owner. Today they come from office entry. Online gifts from RFC 0005 count the same way.
- A fund appears in the portal only after staff tick "Offer in the Member Portal" (`fun_OnlineGiving`).
- Who sees what follows D3: the head of household sees the family's pledges; everyone else sees only their own.
- Staff see progress per family and per fund in the pages they use today, and per person in a new report.
- Pledges never reach a deposit, a tax statement or a payment report. §6 records what today's code does and the guards this RFC adds.
- Prerequisites: anonymous gifts stored as NULL, a finance audit log, one `PledgeWriter`, per-person credit, "Given by" in office entry, and one statement service (GIV-02 to GIV-07, §8).

## Questions for the maintainer
1. Specify the prerequisites GIV-02 to GIV-07 here, and file them, with GIV-43, as sub-issues of the existing epic #9313 (§8)?
2. An opt-in flag per fund (`fun_OnlineGiving`, off by default), rather than every active fund (D14)?
3. One fund and one fiscal year per portal pledge, with the fiscal-year total in `plg_amount` and one new column `plg_EndDate` (D13, §3)?
4. May members change pledges the office entered for them (D17)?
5. The portal Giving History tab and statement downloads need no gateway either. Keep them in RFC 0005 (GIV-30), or move them here?

**Status**
- Proposed. No code yet. The child issues in §8 are opened only if this direction is accepted (D12).
- Builds on the Member Portal on master (`src/portal/`, epic #8977) and on the [member sign-in](https://github.com/ChurchCRM/CRM/blob/feature/member-sign-in/docs/design/member-sign-in.md) design accepted for 7.9.0 (#10464). Members sign in with no user account, and portal pages decide access with its portal permissions model (A41).
- RFC 0005 depends on this RFC. This RFC does not depend on RFC 0005.
- Code is cited by symbol on upstream `master` as of 2026-10-17.

**How to read this**
- Ids are shared with RFC 0005, so earlier review threads still match: **D** product choice, **A** technical choice, **F** flow, **I** invariant, **T** threat, **R** requirement (Appendix A), **GIV-nn** a proposed child PR (§8), not filed. Items that moved here from RFC 0005 keep their numbers (D3, D11, D12, A10, A14, A21, A31, A32, I5, I6, F8, GIV-02 to GIV-07, GIV-43). New items continue the numbering.
- HoH = head of household, anyone in `Family::getHeadPeople()`. FY = fiscal year, starting in `iFYMonth`. Owner = the person, or the household, a pledge or gift is credited to.

---

## 1. Goals and non-goals

**Goals**
1. A member records, edits and ends pledges in the Member Portal, for the funds the church offers.
2. Each pledge shows pledged, given and remaining.
3. Staff see pledge progress per fund, per family and per person.
4. Gifts and pledges can be credited to a person as well as a family (#9313), in office entry too.
5. Totals stay right: anonymous gifts stored one way, each gift on exactly one tax statement, pledges never counted as money.

**Non-goals**
- Moving money: the Give tab, recurring charges, receipts, deposits and refunds are RFC 0005.
- The portal Giving History tab and statement downloads (RFC 0005, GIV-30; question 5).
- Pledge reminder emails, campaigns with goals, and pledges that span several fiscal years (D19).
- Changes to how staff enter pledges, beyond "Given by" (F8).

---

## Proposed product choices

| # | Choice |
|---|---|
| D3 | Anyone who is not HoH, **spouse included**, sees only the pledges and gifts credited to them. The HoH sees the whole family, including pledges and gifts recorded to the family as a whole. |
| D11 | **Adult child who starts a family:** gifts credited to them while in the parents' family stay in the parents' history; nothing is rewritten. The parents' HoH still sees them, the child sees them through the person credit, and past statements stay consistent. When staff change a person's family they get an optional, audited "Move this year's gifts to the new family" for the current calendar year, which has no statement yet. The same prompt moves the person's own pledges for the current fiscal year. Household pledges stay with the old family. |
| D12 | This design is posted for feedback only, with one proposal issue. No epic or child issues are filed until the maintainer accepts the direction. |
| D13 | **A portal pledge is for one fund and one fiscal year.** A member who pledges to two funds adds two pledges. Each owner has at most one pledge per fund and year; the page offers Edit instead of a second one. Reason: each pledge is edited or ended on its own, and every pledge report already groups by fund and fiscal year. |
| D14 | **Funds are offered one by one.** Staff tick "Offer in the Member Portal" (`fun_OnlineGiving`, off by default) on `/finance/funds`. Inactive funds never show. Why not every active fund: an upgrade would publish every active fund to every member at once, including restricted, pass-through and internal funds. The church chooses, one tick per fund. RFC 0005 uses the same flag for online gifts, so members see one list. |
| D15 | **Schedule:** Once, Weekly, Monthly or Quarterly, the existing `plg_schedule` values (Other stays office-only). The member enters the amount per gift and a start date in the current or the next fiscal year. The page shows a sentence and the year total: "$50 monthly from Mar 1 · $500 this fiscal year". |
| D16 | **A pledge is the member's editable intent.** Edit changes the amount, schedule, start or end and recomputes the total. **End** sets the end date (today by default): what was given stays, and the total shrinks to the gifts scheduled up to the end. **Delete** is offered only while nothing has been given toward the pledge. Every change is kept in the finance audit log, and staff see it on the pledge. |
| D17 | **Members may change pledges the office entered for them**, because the pledge is theirs. The audit log shows who changed what. When the office entered several rows for one owner, fund and year, the card shows their total and says "Contact the church office to change this pledge". (Question 4.) |
| D18 | **Only the HoH sees "Pledge as: Me / Our household".** Others pledge as themselves. A member changes only pledges they own: their own, and household pledges if they are HoH. The HoH sees a spouse's pledges read-only. |
| D19 | **A pledge covers one fiscal year.** In the last month of the year the page offers "Pledge again for {next year}", which copies the fund, amount and schedule. Nothing renews by itself. |
| D20 | **A pledge is not a gift.** It never reaches a gateway, a deposit, a receipt or a tax statement. |
| D21 | **Given toward a pledge** = recorded payments in the same fund and fiscal year, credited to the pledge's owner. A person's pledge counts payments credited to that person. A household pledge counts the family's other payments: family-level ones, and those of members who have no pledge of their own for that fund and year. Each payment counts toward one pledge at most. RFC 0005 adds a direct link that overrides this rule. |

## Proposed technical choices

| # | Choice | Reason |
|---|---|---|
| A10 | HoH: `plg_FamID = :fam OR plg_PerID = :actor`; others: `plg_PerID = :actor`. The same scope reads pledges and payments. | D3. |
| A14 | Core append-only `finance_audit_faud`. | Payment edits delete and re-insert rows, and app logs keep 3 days (`LoggerUtils::LOG_RETENTION_DAYS`). |
| A21 | `GIFT_RECORDED` fires once per new payment group from `PledgeWriter`; `DONATION_RECEIVED` stays per row. | Office and online gifts look alike to plugins. |
| A31 | Attribution is set when a row is recorded and changes only through the audited attribution service (F8). | History never shifts silently. |
| A32 | One `GivingStatementService` for admin statements and, later, portal downloads: visibility follows D3, placement follows the statement mode (§5). | Each gift on exactly one tax statement. |
| A33 | Portal pledges are written by `PledgeWriter` (GIV-04) through a `PortalPledgeService`. The row: type `Pledge`; `plg_date` = start date; `plg_FYID` from `FiscalYearUtils::getFiscalYearIdForDate()`; `plg_EndDate`; `plg_schedule`; `plg_amount` = amount per gift × gifts scheduled from the start to the end date or the FY end; `plg_method` NULL; `plg_depID` NULL; `plg_NonDeductible` 0; `plg_EditedBy` = the member's person id; its own GroupKey. | The pledge pages and reports read exactly these columns today. |
| A34 | **Pledge rows keep their id.** A portal edit updates its row in place. An office edit of a pledge group updates its rows in place by fund, inserts added funds and deletes removed ones (`PledgeWriter::replaceGroup()`), instead of today's delete and re-insert. | One audit trail per pledge. RFC 0005 links payments to a pledge id. |
| A35 | `PledgeWriter` stores no deposit on a `Pledge` row and answers 400 when a request sends one. | Closes the API gap in §6. |
| A36 | Every query that reads a deposit's rows also filters `plg_PledgeOrPayment = 'Payment'` (list in §6), and so does the payment search. | Defence in depth: today these queries rely on pledges having no deposit. |
| A37 | One `PledgeProgressService` computes D21 for the portal and for the staff pages, per owner, fund and fiscal year. | Portal and staff totals agree. |
| A38 | `PledgeWriter` makes GroupKeys unique across both types. | `FunctionsUtils::genGroupKey()` checks only `Payment` rows, so two pledges for one family, fund and date get the same key, and an edit of one (`FinancialService::updatePledgeOrPayment()` deletes by GroupKey) rewrites both. |
| A41 | **Portal permissions** use member sign-in's model (§8 there). `portal.giving.pledges` (no scope) comes from the everyone rule while `bEnabledFinance` and `bPortalShowGiving` are on; it guards the nav item, the page and the API (`RequirePortalCapability`). `portal.giving.household` (family scope) comes from a head-of-household rule: anyone in `Family::getHeadPeople()`. It shows household pledges and "Pledge as: Our household" (`portal_can()` in templates). It is deliberately not member sign-in's adult rule, because D3 keeps a spouse's view to their own pledges. | One check for every portal page and for plugins; whole grants; revoked by the next request. |
| A39 | The amount per gift is derived: total ÷ gifts scheduled between `plg_date` and `plg_EndDate` (or the FY end). It is exact because A33 writes the total that way. When the office sets a total that does not divide, or the schedule is Other, the card shows only the total. | No second amount column to keep in step. |

---

## 2. Pages and API

### 2.1 Member Portal Giving page
- **Nav:** `PortalNav::GIVING` "Giving", `fa-solid fa-hand-holding-heart`, between My Family and Profile in `PortalNav::build()`. It names `portal.giving.pledges` (A41), so it shows when `bEnabledFinance` and the new `bPortalShowGiving` (Admin → Member Portal, default off) are on.
- **Route:** `GET /portal/giving`, one view (Pledges), in `src/portal/routes/giving.php`, inside the portal group behind the portal session middleware and `CSRFMiddleware` like the other portal pages, with `RequirePortalCapability` for `portal.giving.pledges`. Template `giving/pledges.html.twig` with partials `pledge-card` and `pledge-form`; overridable like every portal template.
- **Cards:** one per owner, fund and fiscal year visible under D3, current year first. Each shows the fund, the owner ("You", "Smith household", or a spouse's name, read-only), the schedule sentence, pledged, given, remaining and a progress bar. **Show gifts** lists the payments counted toward it (date, amount, method), office-entered today. Edit, End and Delete (D16) appear only on pledges the actor owns (D18).
- **Add a pledge:** fund (offered funds only: `fun_Active = 'true' AND fun_OnlineGiving = 1`, in `fun_Order`, with `fun_Description` as help), Pledge as (HoH only), amount per gift, schedule, start date, optional end date, with a live sentence and total. If the owner already has a pledge for that fund and year, the form offers to edit it (D13).
- **Pledge again** appears in the last month of the fiscal year (D19).
- **Empty states:** "No pledges yet" with Add. When no fund is offered, the page shows existing pledges and "Contact the church office to make a pledge".
- **Non-HoH note:** "Pledges you make are also visible to your head of household."
- The Member Portal design (`.agents/skills/churchcrm/member-portal-design.md`, P14) says the nav holds no Giving page until a payment-gateway design exists. GIV-83 rewrites P14 to point at this RFC.

### 2.2 Portal API
`src/api/routes/portal/portal-giving.php`, behind `PortalApiMiddleware`, `CSRFMiddleware` and `RequirePortalCapability` (A41), with the rules of `portal-me.php`: the actor is the session's portal principal, no person or family id is accepted, and an API key is refused.

| Route | Does |
|---|---|
| `GET /api/portal/giving/pledges` | The actor's cards (D3), with given and remaining |
| `POST /api/portal/giving/pledges` | Add: fund, owner (`me` or `household`), amount per gift, schedule, `start`, `end` (optional) |
| `PUT /api/portal/giving/pledges/{id}` | Edit |
| `POST /api/portal/giving/pledges/{id}/end` | End, on `date` (default today) |
| `DELETE /api/portal/giving/pledges/{id}` | Delete, while nothing has been given |
| `GET /api/portal/giving/pledges/{id}/gifts` | The payments counted toward it |

- An `{id}` outside the actor's scope answers 404. One the actor can see but not change (a spouse's pledge, a card of several office rows) answers 403. Owner `household` from a non-HoH answers 403.
- A second pledge for the same owner, fund and year answers 409 "You already have a pledge for this fund this year".
- Amounts: 0.01 to 999,999.99 per gift and for the year total (`plg_amount` is `decimal(8,2)`).
- Dates (`start`, `end`, `date`, and every date in a response) are ISO `Y-m-d` calendar dates in the church's time zone, never locale-formatted. Anything else answers 400. The page formats them for display.

### 2.3 Staff pages: progress per fund, family and person
Reuse first. The family and fund views exist; they gain person lines and LEFT JOINs.

| View | Today | Change |
|---|---|---|
| Pledge Dashboard `/finance/pledge/dashboard` and Family Pledge Summary `/finance/pledge/family-summary` | `FamilyPledgeSummaryService::getFamilyPledgesByFiscalYear()`: pledges against payments per family and fund | LEFT JOIN family (GIV-02); a line per credited person under the family (GIV-84) |
| Fund contributors `/finance/fund/{fundId}/contributors` | `FundContributorsService::getFundContributors()` | the same |
| Pledge Family Summary PDF (`Reports/FamilyPledgeSummary.php`) and Pledge Reminders (`Reports/ReminderReport.php`) | family-level, by `plg_FYID` | stay family-level; a person's pledge counts in their family; the report help says so |
| Family view, pledges and payments table | family-level | "Given by" column (GIV-06) |
| Person view | nothing | Finance-only Giving card with the person's gifts (GIV-06) and pledges with progress (GIV-84) |
| Pledge detail `/finance/pledge/{groupKey}` | the rows | change history from `faud` (GIV-84) |
| New: Pledge progress by person | nothing | #9313's "Building Fund Progress by Member", for any fund: person, pledged, given, remaining (GIV-43) |

All of them use `PledgeProgressService` (A37). Family totals do not change: the sum over a family's owners equals the family's pledges and payments. Rows with no family appear under "No family".

---

## 3. Data model

**Migrations:** `src/mysql/upgrade/<next>-*.sql`, named for the release a maintainer schedules them into, mirrored by hand in `Install.sql`, `orm/schema.xml` and `cypress/data/seed.sql`, and registered in `upgrade.json` only then. No column-level `IF NOT EXISTS`.

| Table | Change | Migration / issue |
|---|---|---|
| `pledge_plg` | Data `plg_FamID = 0 → NULL`; anonymous = both ids NULL | `<next>-giving-anonymous-normalize.sql` / GIV-02 |
| `pledge_plg` | `plg_PerID mediumint(9) unsigned NULL` + index; `idx_plg_GroupKey`. No DB FK (stock style); cleared in code on person delete | `<next>-giving-person-attribution.sql` / GIV-05 |
| `pledge_plg` | `plg_EndDate date NULL`: last date of a scheduled pledge; NULL = through the FY end. Written on `Pledge` rows only | `<next>-pledge-end-date.sql` / GIV-82 |
| `donationfund_fun` | `fun_OnlineGiving tinyint(1) NOT NULL DEFAULT 0` (D14) | `<next>-fund-online-giving.sql` / GIV-80 |
| new `finance_audit_faud` | Append-only, no update or delete API: `faud_ID bigint`, date, `faud_ActorPerId` (signed), `faud_ImpersonatorPerId` (the administrator's person during Login as User, member sign-in §6.5; else NULL), `faud_Action`, entity type and id, `faud_Before`/`faud_After` JSON (secrets excluded). RFC 0005 adds `faud_IpHash` once its HMAC key exists | `<next>-finance-audit.sql` / GIV-03 |

**Why `plg_EndDate`:** without it an ended pledge cannot show "Ended Jun 30", and A39 cannot recover the amount per gift after an end. Nothing else is added. The start date is `plg_date`, the fiscal year is `plg_FYID`, the schedule is `plg_schedule` (no enum change), the amount per gift is derived (A39), and who entered a pledge is in the audit log.

**Portal form to columns**

| Form | Column |
|---|---|
| Fund | `plg_fundID` |
| Pledge as Me / Our household | `plg_PerID` = actor or NULL; `plg_FamID` = `PledgeWriter::familyOf(actor)` |
| Amount per gift × gifts in the period | `plg_amount` (fiscal-year total) |
| Schedule | `plg_schedule` |
| Start date | `plg_date`, and `plg_FYID` from it |
| End date | `plg_EndDate` |
| (fixed) | `plg_PledgeOrPayment` 'Pledge', `plg_method` NULL, `plg_depID` NULL, `plg_NonDeductible` 0, `plg_EditedBy` = actor's person id, `plg_DateLastEdited`, GroupKey (A38) |

**Propel.** The existing `plg_EditedBy` FK in `orm/schema.xml` is unnamed; a second FK to `person_per` would rename the generated methods to `…RelatedBy…`. GIV-05 names both: the existing one `phpName="Person"`, `refPhpName="Pledge"`, so its callers stay unchanged (`FinancialService::getPayments()`, the family list route in `finance-payments.php`, and the `leftJoinWithPerson()` calls in `PledgeQuery::filterForTaxReport()` and `filterForAdvancedDeposit()`); the new one `phpName="Donor"`, `refPhpName="DonatedPledge"`.

**Family lookup.** Every place that copies "the person's family" into `plg_FamID` calls `PledgeWriter::familyOf(Person)` (GIV-04). It maps `per_fam_ID = 0` to NULL: the column is `NOT NULL default '0'`, and unlinking members on family delete sets 0 (`DELETE /api/family/{familyId}`). Without it, people with no family would write 0 and break I6.

**Invariants** (enforced in code; RFC 0005's nightly check also runs I6, I12 and I13)
- **I5** (a write-time rule: ChurchCRM keeps no family-membership history) When a row credited to a person is written, `plg_FamID` = `PledgeWriter::familyOf(person)`, NULL if the person has no family. Afterwards it changes only through the attribution service: re-attribution, the D11 move, or the family donation move (F8), each audited.
- **I6** Anonymous = both ids NULL; `plg_FamID` is never 0.
- **I12** A `Pledge` row has `plg_depID` NULL and appears on no deposit, receipt, tax statement or payment report.
- **I13** Each payment counts toward one pledge at most (D21).

**Settings** (category-less core `ConfigItem`s; the System Settings page is frozen)

| Key | Default | Meaning |
|---|---|---|
| `bPortalShowGiving` | 0 | The portal Giving page; set on Admin → Member Portal (GIV-83) |
| `bFinanceShowGivenBy` | 1 | "Given by" in office entry, #9313's "optional" (GIV-06); data unaffected |
| `sGivingStatementMode` | household | household, individual or both (§5, GIV-07) |

---

## 4. Flows

**F8. Admin entry** (`src/finance/views/pledges/editor.php`, for pledges and payments)
- **Given by** (when `bFinanceShowGivenBy`): Family (the existing family TomSelect on `/api/families/search`, plus "Credit to: Whole household | {member}"), Person (family filled from the person), or Anonymous. Hint: "Only the head of household and the credited person see this in the Member Portal."
- The editor's payload adds `PersonID`. The rule and the 999,999.99 cap live in `PledgeWriter`, so all three write routes share them: `POST /api/payments/`, `POST /api/payments/pledges` and `PUT /api/payments/{groupKey}`. Person → PerID P, FamID = `familyOf(P)`; Family → NULL, F; Anonymous → NULL, NULL. Both sent and disagreeing → 400. No `PersonID` on a new row → as today (family only). No `PersonID` on an edit → every row keeps the PerID it had, so fixing a check number or date never turns a person's gift into a family or anonymous one (D3, I5, I6); only an explicit Person, Family or Anonymous changes it. This covers `bFinanceShowGivenBy` = 0, where the editor never sends the field, and API clients that predate it.
- **Attribution service** (GIV-05) is the only path that changes ids on existing rows. It updates every row of a group in place, writes one `faud` row per group, and leaves `plg_EditedBy` alone, so a system id survives. It serves `PATCH /api/payments/{groupKey}/attribution` (Finance), the D11 move and the family donation move. RFC 0005 extends it to gateway groups and their ledger rows.
- **Family donation move:** the family-delete page (`SelectDelete.php`, "Move Donations to Selected Family") calls `POST /api/family/{familyId}/donations/move`. Today it rewrites `plg_FamID` on every row of the family, all years, sets `plg_EditedBy` to the current user, and writes no audit. GIV-05 reimplements it on the attribution service. Person-credited rows move too, listed in the audit rows, because the source family is being merged away. All years move: this is a family merge, not the D11 case.
- **Family change (D11):** when `per_fam_ID` changes (`PersonEditor.php`, `FamilyEditor.php`, `ConvertIndividualToFamily.php`, and the family member routes in `people-family.php`), the result offers "Move this year's gifts to the new family": payment rows with PerID = person and FamID = old family in the current calendar year, and the person's own pledge rows for the current fiscal year, via the attribution service. Earlier years never move.

**F15. Add a pledge**
1. `GET /portal/giving` with the actor from `PortalAccessMiddleware`. The page loads the cards and the offered funds.
2. `POST /api/portal/giving/pledges` checks that the fund is active and offered, the owner rule (D18), one pledge per owner, fund and year (409), a start date in the current or next fiscal year, an end date on or after the start and no later than that year's end, and the amounts (§2.2).
3. `PortalPledgeService` computes the total (A33). `PledgeWriter` writes the row and a `faud` row (`pledge.create`, before NULL, after the row), in one transaction. Profile and family writes in the portal add a timeline note "edited via the Member Portal"; pledge writes do not, so finance data stays out of the person timeline that non-finance staff read.
4. The card appears with given = what D21 already counts.

**F16. Edit, end, delete**
- **Edit:** owner check; the same validation as F15; the row is updated in place (A34) and the total recomputed; one `faud` row with before and after.
- **End:** sets `plg_EndDate` (default today, never before the start); the total becomes the gifts scheduled up to the end; audited. An ended pledge stays on the page, marked "Ended {date}".
- **Delete:** only while D21 counts nothing toward the pledge; the row is deleted and the `faud` row keeps its last state.
- Staff edits in the office editor follow A34 and are audited the same way.

**F17. Progress** (`PledgeProgressService`, A37)
- Per owner, fund and fiscal year: pledged = Σ `plg_amount` of `Pledge` rows; given = Σ `plg_amount` of `Payment` rows with the same `plg_fundID` and `plg_FYID`, credited by D21; remaining = pledged − given, never below 0.
- A person's pledge: payments with `plg_PerID` = person. A household pledge: payments with `plg_FamID` = family, minus those credited to a person who has their own pledge for that fund and year.
- RFC 0005 adds: a payment that links to a pledge counts toward that pledge only, and gifts still settling are left out.

---

## 5. Statements (`GivingStatementService`, GIV-07)
`Reports/TaxReport.php` and the emailed statement `Reports/FamilyTaxReportEmail.php` both build letters from `FinancialService::getTaxReportData()`. GIV-07 moves the letter rules into one service; RFC 0005 reuses it for portal downloads.

- **Household** (default): all rows with `plg_FamID` = family, with a Given by column. Rows with FamID NULL and PerID set (no family) get individual letters.
- **Individual:** each person's PerID rows, addressed to them (`per_Envelope`). Family-level rows (PerID NULL) go on the letter of the head with the lowest `per_ID` among `getHeadPeople()`, which returns heads in no set order, or on a household letter as today when the family has no head.
- **Both:** gifts credited to any non-HoH person, spouse included, go only on that person's statement; family-level and the HoH's own gifts go on the household statement. Each gift is on exactly one statement.
- Anonymous rows are left out. Pledges are never read (I12). The minimum amount applies to the donor total, not per row (today `getTaxReportData()` filters each row). NonDeductible handling is unchanged. Refunds (negative rows) and non-cash gifts come with RFC 0005.
- Add a tax statements page on the service: the `/finance/reports/tax-statements` route and its view `reports/tax-statements.php`.
- **Head lists:** the family filter on the Financial Reports page (`FinancialReports.php`) splits `sDirRoleHead` on commas but `intval()`s `sDirRoleSpouse`, and joins spouses for "John & Jane". Make the spouse role list-aware (`Family::getAdults()` semantics). `FamilyEditor.php` casts `sDirRoleHead` to int for the quick-entry default role; take the first id.

**Report fixes that come with the prerequisites**
- "Anonymous" (gettext) replaces "Unassigned" in `TaxReport.php`, `FamilyTaxReportEmail.php` and `AdvancedDeposit.php` (GIV-02).
- `FamilyPledgeSummaryService::getFamilyPledgesByFiscalYear()` and `FundContributorsService::getFundContributors()` get LEFT JOINs and drop `filterByAmount(0, Criteria::GREATER_THAN)`, so anonymous and family-less rows appear and negative rows net (GIV-02). Office entry stays positive-only (`FinancialService::validateFund()`, `insertPledgeorPayment()` and the editor's amount check); `PledgeWriter` allows negative rows only internally.
- Donor count: `FinancialService::getYtdDonorFamilyCount()` already skips NULL through `COUNT(DISTINCT plg_FamID)`. Its only defect, anonymous rows stored as 0, goes with GIV-02. After GIV-05 it counts distinct families, or persons where the family is NULL.
- Zero Givers, Pledge Reminders and Voting Members stay family-level; their help says so.
- Family delete already refuses a family with pledges or payments (`DELETE /api/family/{familyId}`). Staff move them first with the family donation move (F8).

---

## 6. Pledges, deposits and tax statements
The review asked how tax statements and the Advanced Deposit Report treat `Pledge` rows. Findings on master:

**Filtered by type, so pledges never appear**
- Tax statements: `TaxReport.php` and `FamilyTaxReportEmail.php` read `FinancialService::getTaxReportData()`, which uses `PledgeQuery::filterForTaxReport()`: `Payment` rows only.
- Advanced Deposit Report: `FinancialService::getAdvancedDepositReportData()` uses `PledgeQuery::filterForAdvancedDeposit()`: `Payment` rows only.
- Zero Givers (`PledgeQuery::filterForZeroGivers()`), Voting Members (`Reports/VotingMembers.php`), the finance dashboard (`FinancialService::getYtdPaymentTotal()`, `getYtdPledgeTotal()`, `getYtdPaymentCount()`, `getYtdDonorFamilyCount()`) `DepositService::getDepositTotal()` and the deposit CSV export (`DepositService::getDepositsForExport()`) all filter by type. `DepositService::getDepositItemsByType()` takes the type as an argument.

**Not filtered by type, correct only because pledges carry no deposit**
- The deposit list total: `DepositQuery::preSelect()` sums every row joined to the deposit.
- `Deposit::getTotalChecks()`, `getTotalCash()`, `getCountChecks()`, `getCountCash()`, `getFundTotals()`, the deposit PDF (`Deposit::getPDF()`, including the QuickBooks slip), `Deposit::getOFX()`, and `Deposit::preDelete()`, which deletes every row on the deposit.
- The "No Payments on this Deposit" checks in `Reports/PrintDeposit.php` and `GET /api/deposits/{id}/pdf`.
- The payment search (`FinancePaymentSearchResultProvider`) lists every group, pledges included, as "Payment on Deposit".

**The gap.** The office editor shows no deposit field for a pledge, so office pledges have `plg_depID` NULL. But `FinancialService::insertPledgeorPayment()` copies `DepositID` for either type. A call to `POST /api/payments/` or `POST /api/payments/pledges` with `type` = `Pledge` and a `DepositID` puts a pledge on that deposit: it then counts in the deposit total, the fund totals, the PDF and the OFX file, and is deleted with the deposit. It still never reaches a tax statement, the deposit CSV or the Advanced Deposit Report.

**What this RFC guards** (GIV-81, before any portal pledge exists)
1. No deposit on a pledge: `insertPledgeorPayment()` now, and `PledgeWriter` after GIV-04, answer 400 to a `DepositID` on a `Pledge` row (A35).
2. Every deposit-scoped query above, and the payment search, filters `Payment` (A36).
3. `GivingStatementService` (GIV-07) keeps the `Payment` filter of `filterForTaxReport()`.
4. Tests on seed data: a pledge, including one forced onto a deposit, is absent from the deposit total, fund totals, PDF, OFX, CSV, search, tax statement and Advanced Deposit Report.
5. GroupKeys are unique across both types (A38).

Pledge rows that already carry a deposit (only the API could write them) stop counting toward that deposit once A36 lands, which is the correct total; a closed deposit's printed total can then differ from a new print. The change note gives the query that lists them: `SELECT plg_depID, COUNT(*) FROM pledge_plg WHERE plg_PledgeOrPayment = 'Pledge' AND plg_depID IS NOT NULL GROUP BY plg_depID`.

---

## 7. Security and privacy
No money moves, so there is no PCI scope, gateway secret, webhook or fee. What remains:

| # | Threat | Control | Child PR (§8) |
|---|---|---|---|
| T5 | IDOR | No person or family id accepted; pledge ids checked against the actor's scope (404 outside it, 403 read-only); actor-scoped queries (A10) | GIV-83 |
| T11 | Insider edits | Attribution only through the audited service; every pledge change and family move in `faud` | GIV-03, 05, 82 |
| T13 | Wrong year | FY computed on the server from the date (`FiscalYearUtils::getFiscalYearIdForDate()`) | GIV-04 |
| T19 | A pledge counted as money | A35, A36, I12, tests in §6 | GIV-81 |
| T20 | A member sees someone else's pledges | D3 scope tests: HoH, spouse, child, person with no family, two heads | GIV-07, 83 |
| T21 | Login as User | Returns with member sign-in (§6.5 there: administrators only, off by default, read-only by default). Pledges show only when the administrator has Finance. Pledge writes answer 409 during Login as User, even when the church allows changes, and the audit records the administrator | GIV-83 |
| T22 | Abuse | Session only, CSRF, one pledge per owner, fund and year, amount cap | GIV-83 |

**Person delete** (`Person::preDelete()`) clears `plg_PerID` on the person's rows; the family keeps them; audited. The person's pledges with `plg_FamID` NULL are deleted with them, because a pledge is not a gift. Their payments with `plg_FamID` NULL block the delete: clearing the id would make a named gift anonymous (I6), so Finance re-attributes them first. RFC 0005 extends the rule to its own person ids and to recurring gifts.

---

## 8. Proposed breakdown
Nothing here is filed yet (D12). One concern and one PR per child; each user-visible child opens a user-manual docs issue. Each issue will carry its own scope and acceptance criteria.

**Filing:** GIV-02 to GIV-07 and GIV-43 are filed as sub-issues of #9313, which is already the epic for per-person giving; #9313 closes when they do. GIV-80 to GIV-84 go under a new epic for portal pledges, which depends on #9313's GIV-04, GIV-05 and GIV-07 (question 1).

**Sequencing:** GIV-81, GIV-02, GIV-03 and GIV-80 can start at once. Office-side work is visible as soon as it merges. The portal page waits for the scope and progress services.

| Id | Title | Design | Depends on |
|---|---|---|---|
| **A** | **Finance foundations** (#9313) | | |
| GIV-02 | Anonymous = NULL family; "Anonymous" label; LEFT JOINs and refund netting in summaries | §3, §5 | – |
| GIV-03 | Append-only finance audit log | §3 `faud` | – |
| GIV-04 | `PledgeWriter`: session-less, server FY, PDO GroupKeys unique across both types, `familyOf()`, pledge rows edited in place, no deposit on pledges; `GIFT_RECORDED` | A21, A33–A35, A38 | 03, 81 |
| GIV-05 | Per-person attribution (#9313): `plg_PerID`; `PersonID` rule on all write routes; attribution service and PATCH; family donation move; person delete rule; donor count | §3, F8, §5, §7 | 02, 04 |
| GIV-06 | "Given by" in the editor, person and family views; D11 move | F8 | 05 |
| GIV-07 | `GivingStatementService`, `GivingScope` (A10), tax-statements route and view, head-list fixes | §5 | 05 |
| GIV-43 | Per-person reports from #9313: Contributions by Person, Fund Giving by Person, Top Contributors by Fund, Pledge progress by person (#9313's "Building Fund Progress by Member", any fund) | §2.3 | 07, 82 |
| **P** | **Portal pledges** | | |
| GIV-80 | Offer a fund in the Member Portal: `fun_OnlineGiving` and its toggle on `/finance/funds` | D14 | – |
| GIV-81 | Keep pledges off deposits and payment search: refuse a deposit on a pledge, filter deposit queries and search, tests | §6, A35, A36, I12 | – |
| GIV-82 | `plg_EndDate`; schedule helper (gifts between two dates); `PledgeProgressService`; `PortalPledgeService` | §3, A33, A37, A39, F15–F17 | 04, 05 |
| GIV-83 | Portal Giving page, Pledges view; portal API; `bPortalShowGiving`; nav entry; Member Portal design P14 | §2.1, §2.2 | 07, 80, 82 |
| GIV-84 | Pledge progress in the staff pages: person lines in the Pledge Dashboard, family summary and fund contributors; person view pledges; change history on pledge detail | §2.3 | 06, 82 |
| GIV-70 | Docs, E2E, i18n (rolling, shared with RFC 0005) | – | each item after the issue it covers |

---

## 9. Open questions: product defaults

| # | Question | Recommended default |
|---|---|---|
| 1 | Who can pledge for the household? | The HoH only; spouses pledge as themselves (D18). |
| 2 | Statement mode default? | Household. |
| 3 | A family with no HoH, or several heads? | Everyone in `getHeadPeople()` is HoH for D3. |
| 4 | Nav position? | Between My Family and Profile. |
| 5 | Start date range? | The current or the next fiscal year. |
| 6 | Renewal? | A "Pledge again" offer in the last month; no automatic renewal (D19). |
| 7 | Delete? | Only while nothing has been given; otherwise End (D16). |
| 8 | Tell staff when a member changes a pledge? | No email. The change shows on the pledge and in the audit log. |
| 9 | Show "expected by today" to members? | No. Pledged, given and remaining only. |

The five questions for the maintainer are at the top of this document.

---

## Appendix A. Requirements this design covers

| # | Requirement | Where | Notes |
|---|---|---|---|
| R2 | Anonymous giving, admin entry | F8, GIV-02/06 | I6 |
| R4 | Giving by persons, admin entry | F8, GIV-05/06 | Optional; `bFinanceShowGivenBy` |
| R5 | Giving by families, admin entry | F8 | Whole household or member |
| R42 | Admin reports updated similarly | §2.3, §5, GIV-07/43, A32 | #9313 reports |
| R54 | A member chooses a fund and records a pledge: amount, schedule, start date | D13–D15, F15, §2.1 | One fund per pledge |
| R55 | The member edits or ends it | D16, D17, F16 | In place, audited |
| R56 | The member sees it beside what has been given | D21, F17, §2.1 | Office-entered gifts today |
| R57 | Staff see pledge progress per fund | §2.3 | Fund contributors |
| R58 | Staff see pledge progress per family | §2.3 | Pledge Dashboard, family summary, reports |
| R59 | Staff see pledge progress per person | §2.3, GIV-43, GIV-84 | New report, person view |
| R60 | No money moves | D20, §6, I12 | Guards in GIV-81 |
| R61 | The church chooses which funds appear | D14, GIV-80 | Off by default |
| D3 | HoH / non-HoH visibility | A10, A41, §2.1, §5 | |
| D11 | Moving families | A31, F8, GIV-06 | Pledges move with the person |
| D12 | Proposal first; issues only after the direction is accepted | Status, §8 | |
| D13–D21 | Pledge choices | §2, §3, F15–F17 | |
