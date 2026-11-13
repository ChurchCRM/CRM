# RFC: Expense management as a core finance feature

> **BLUF**
> ChurchCRM tracks money in — giving, pledges, deposits — and has no way at all to track money out. There is no expense register, no category or budget for spending, and nowhere to attach a receipt; churches run their outgoings in spreadsheets and lose the connection between a fund's income and what it was spent on. This RFC proposes expense management as a **core** finance feature: an expense register (payee, date, amount, category, fund), receipts attached to each expense (external links first, per the link-system direction in RFC 0013), budget-vs-actual reporting per category and fund, and a simple reimbursement path for staff and volunteers who spend on the church's behalf — so the finance module tells the whole money story, in and out.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issue:** [#4658](https://github.com/ChurchCRM/CRM/issues/4658) (reopened Nov 5, 2026) · **Related:** [RFC 0013](0013-external-resource-links.md) (external resource links — the receipt-attachment mechanism), [RFC 0004](0004-portal-giving-pledges.md) / epic [#9313](https://github.com/ChurchCRM/CRM/issues/9313) (giving side), [#1761](https://github.com/ChurchCRM/CRM/issues/1761) (deposit dashboard)
**Numbering:** `0014` is provisional. A maintainer assigns the final number on merge.

## Summary

- **Half the ledger is missing.** Funds receive donations and pledges, but nothing records what a fund spent; fund reporting can never show a true balance or burn rate.
- **Receipts are the audit trail.** A treasurer's first question about any expense is "where's the receipt?" — today the answer lives in email and shoeboxes. Each expense carries its receipt as an external link (RFC 0013 system), keeping ChurchCRM out of the file-hosting business per the #350 direction.
- **Budgets make it useful, not just archival.** Churches budget by ministry/category annually; budget-vs-actual per category and fund is the report boards actually ask for.
- **Reimbursement is the daily workflow.** Volunteers buy supplies and submit for repayment; a submit → approve → paid flow with the receipt attached replaces the paper form.
- **Core, not a plugin.** Spending touches funds, permissions (Finance role), reports and dashboards; it belongs in the core finance module next to deposits and pledges.

## Use cases

1. **Monthly close.** The treasurer records the utility bill, the curriculum order and the plumber against their categories/funds, attaches receipts, and the month's outgoings are visible beside deposits.
2. **Board report.** The finance chair pulls budget-vs-actual by category for the quarter and sees the building fund's remaining balance after expenses, not just its donations.
3. **Volunteer reimbursement.** A youth leader buys event supplies, submits the expense with a photo receipt link from their phone, the approver approves, and the payment is recorded as paid.
4. **Audit time.** The auditor samples ten expenses; each has payee, amount, category, approver and receipt in one place.

## Goals

- Record an expense in under a minute: date, payee, amount, category, fund, payment method, notes, receipt link.
- Categories are church-defined (with sensible defaults), and expenses can be budgeted per category/fund per fiscal year.
- Budget-vs-actual and fund-balance-after-expenses reporting uses the same numbers everywhere (register, reports, dashboard).
- Reimbursement has a visible state (submitted / approved / paid / rejected) with an approver distinct from the submitter.
- Finance-role permissions govern who can enter, approve and see expenses, consistent with the rest of the finance module.
- Export (CSV at minimum) for the church's accountant.

## Non-goals

- **Not full accounting.** No double-entry general ledger, accounts payable automation, payroll, or bank reconciliation in this proposal — ChurchCRM remains a church management system, not an accounting package.
- **No file hosting.** Receipts attach via the RFC 0013 external-link system; uploading/hosting files is that RFC's separate question.
- **No corporate cards / bank feeds.** Manual entry and CSV export only.
- **No architecture in this RFC.** Schema, category model and workflow mechanics are decided in the post-approval architecture doc, per the RFC process.

## Open questions (maintainer decisions)

1. **Category model.** Flat church-defined categories, or a chart of accounts with hierarchy? How do categories relate to donation funds — an expense charges a fund, a category, or both?
2. **Budget grain.** Budgets per category, per fund, or per category×fund? Annual only, or monthly phasing?
3. **Approval depth.** Single approver, or thresholds (amount-based second approval)? Who may approve their own expenses — never, or with a flag?
4. **Receipts.** Link-only in v1 (RFC 0013), or is upload required for the reimbursement-from-phone workflow to be real?
5. **Starting balances.** Fund balances after expenses need an opening position — how do existing churches initialise fund balances at adoption?

## Linked issues

Proposal: #4658 (2019; its individual-contribution thread is separately tracked on epic #9313 and is not part of this RFC). Consumers to design with: fund and deposit reporting (#1761), giving statements are out of scope but the finance dashboard should eventually show in/out together.
