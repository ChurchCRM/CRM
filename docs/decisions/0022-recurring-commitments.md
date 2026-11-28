# RFC: Recurring commitments and support payments

> **BLUF**
> Churches make standing promises to pay — monthly support for missionaries is the classic case — and ChurchCRM has nowhere to track a promise, only (under RFC 0014) the payment once it happens. A commitment is not an expense: it has an amount and a due date for each period, it can be paid in part, and whatever is left unpaid for March must still show as outstanding for March after April begins. This RFC proposes commitment tracking in the finance module: a commitment (payee, amount, cadence, due day), one obligation per period, payments recorded against a period with receipts and history, and a per-period balance that never silently rolls forward — so a treasurer can always answer "who do we owe, for which month, and how much is left?"

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Audience:** Workflow feature — community feedback via its Feature Proposal discussion.
**Date:** 2026-11-22
**Owner:** @dawoudio
**Proposal issue:** [#11869](https://github.com/ChurchCRM/CRM/issues/11869) (expense recording request, Nov 22, 2026 — the commitments half) · **Related:** [RFC 0014](0014-expense-management.md) (expense management — the register this builds on), [RFC 0013](0013-external-resource-links.md) (external resource links — the receipt-attachment mechanism), [RFC 0004](0004-portal-giving-pledges.md) (pledges — the mirror image on the money-in side)
**Numbering:** `0022` is provisional. A maintainer assigns the final number on merge.

## Summary

- **Promises are the missing half of money-out.** RFC 0014 records spending after it happens. Nothing records what the church has *committed* to spend, so the finance module cannot show obligations, only history.
- **The driving workflow is missionary support.** A church commits R$ 1,500/month to a missionary. Some months it pays in full, some months R$ 1,000. The unpaid R$ 500 belongs to that month forever — it is not absorbed into next month's commitment, and it must stay visible until it is paid or formally forgiven.
- **A pledge, pointed the other way.** Members pledge money in (RFC 0004) with an amount, a schedule, payments against it, and a remaining balance. A commitment is the same shape with the church as the payer — the model and the UI patterns already exist in the codebase's vocabulary.
- **Payments land in the register.** When a commitment period is paid (in whole or part), that payment is an expense in the RFC 0014 register, linked back to the commitment period it settles. One money story, no double counting.
- **Core, not a plugin.** Commitments touch funds, the finance dashboard, permissions and reporting; and the churches asking for it (small churches supporting missionaries directly) are the least able to build or maintain a plugin.

## Use cases

1. **Missionary support (the requester's case).** The treasurer sets a monthly commitment per missionary — amount, due day. Each month she records what was actually paid and when, attaches the receipt, and sees each month's remaining balance separately: March still owes R$ 500 even though April's commitment has begun.
2. **Partial and late payments.** A lean month means half the rent support is paid on the due date and the rest two weeks later. Both payments record against the same period; the period shows paid-in-full only when its balance reaches zero.
3. **Board review.** The missions chair opens the commitments view: every active commitment, what's due this month, what's outstanding from earlier months, and the total obligation — without a spreadsheet.
4. **Commitment lifecycle.** A missionary returns from the field: the commitment ends (or is paused) from a chosen month. Past periods and their history are untouched; no new obligations generate after the end.

## Goals

- Create a commitment in under a minute: payee (person, family, or free-text organisation), amount, cadence (monthly at launch), due day of month, start month, optional end month, fund, notes.
- Each period of a commitment is its own obligation with its own state: upcoming / due / partially paid / paid / forgiven, and its own balance.
- Record a payment against a period in seconds: amount, date, method, receipt link (RFC 0013), notes. Multiple payments per period.
- Balances are per period and never roll forward automatically. Paying April does not touch March's outstanding R$ 500.
- A commitments view answers, at a glance: due this month, overdue, outstanding by payee, outstanding by period.
- Paying a commitment creates (or links) the matching expense in the RFC 0014 register, charged to the commitment's fund.
- Finance-role permissions govern who can create commitments, record payments, and forgive a balance — consistent with the rest of the finance module.
- History is immutable: every payment and state change on a period is listed on the payee's commitment page.

## Non-goals

- **Not the expense register.** One-off spending with no prior commitment is RFC 0014's scope; this RFC only adds the obligation layer above it.
- **Not accounts payable automation.** No invoice ingestion, no approval chains beyond the finance role, no payment scheduling with a bank, no actual money movement — ChurchCRM records payments, it does not make them.
- **Not payroll.** Staff salaries are recurring payments, but payroll carries tax and compliance obligations that are explicitly out of scope.
- **No automatic roll-forward or netting.** Balances never move between periods on their own; forgiving a balance is a deliberate, recorded action.
- **No architecture in this RFC.** Schema, generation mechanics (when period obligations are created) and workflow details are decided in the post-approval architecture doc, per the RFC process.

## Open questions

1. **Cadence at launch:** monthly only (the requester's case), or also quarterly/annual from day one? Monthly covers missionary support, rent and subscriptions; wider cadences widen the spec.
2. **Payee types:** must a payee be a Person/Family in the database (missionaries usually are), or is a free-text organisation payee (landlord, utility) a launch requirement?
3. **Forgiveness:** who may mark a period's balance forgiven, and does it need a reason note? (Proposed: finance admins, reason required — it is the one action that erases an obligation.)
4. **Reminders:** does the treasurer get a dashboard/notification nudge for due and overdue commitments, or is the commitments view enough at launch?
5. **Sequencing with RFC 0014:** can commitments ship before the expense register exists, with payments recorded standalone and linked later — or is 0014 a hard prerequisite?
