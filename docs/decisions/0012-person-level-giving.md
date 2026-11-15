# RFC: Person-level giving — donations tracked to a person, not only a family

> **BLUF**
> In ChurchCRM today, giving is recorded against a **family**. A person can only be a donor by creating a family for them — including the artificial one-person family — and envelopes, pledges, statements and giving history all inherit that family grain. Churches keep asking for the alternative: two giving units in one household, an adult child giving independently from home, a single member whose giving should be *theirs*. The asks are old and recurring (#4133 "Assign donations per individual", #2972 "Donations by Individual"), and the family requirement leaks into unrelated flows (anonymous deposit payments forced to pick a family, #9615). This RFC proposes deciding the donor model for ChurchCRM giving. It is a **full finance impact** decision — deposits, pledges, envelopes, statements, reports, dashboards and portal giving all consume the donor grain — so it is decided here, once, rather than patched flow by flow.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issues:** [#4133](https://github.com/ChurchCRM/CRM/issues/4133) (anchor, reopened Nov 5, 2026), [#2972](https://github.com/ChurchCRM/CRM/issues/2972) (folded in) · **Friction evidence:** [#9615](https://github.com/ChurchCRM/CRM/issues/9615) (deposit flow requires a family even for anonymous donors), [#367](https://github.com/ChurchCRM/CRM/issues/367) (deposit entry is family-grained) · **Related:** [RFC 0004](0004-portal-giving-pledges.md) (portal giving & pledges), [RFC 0010](0010-organisations-as-contacts.md) (organisations — a third donor kind if adopted), [#2398](https://github.com/ChurchCRM/CRM/issues/2398) (pledge reporting & staff workflows), [#2933](https://github.com/ChurchCRM/CRM/issues/2933) (envelope reports)
**Numbering:** `0012` is provisional. A maintainer assigns the final number on merge.

## Summary

- **The donor grain is family, everywhere.** Payments and pledges carry a family; envelopes are assigned per family; giving statements and the giving-history UI live on the family; the deposit workflow thinks in families.
- **The workaround distorts data.** One-person families created only to hold giving pollute the directory, family statistics and mail merges, and two real giving units in one household cannot be separated at all.
- **The need is separable giving within a household, and personal giving without a household fiction.** Not "abolish family giving" — most household giving is genuinely joint.
- **Statements are the sharp edge.** Tax statements are issued per giving unit; whatever model is chosen decides whose name is on the statement, whether spouses can split or combine, and how a mid-year change is handled.
- **Portal giving already implies a person.** RFC 0004's self-service giving is a person acting; the donor model here must reconcile with it instead of creating two grains.

## Use cases

1. **Two giving units, one roof.** Spouses who tithe separately (or an adult child / parent at home) each get their own envelope, pledge and statement, while sharing one family record and address.
2. **The single member.** A member who lives alone gives without staff inventing a family wrapper; their person page shows their giving directly.
3. **Anonymous and guest giving.** A deposit entry for a one-off/anonymous gift completes without forcing a family selection (#9615), under a donor model that has a defined place for it.
4. **Year-end statements.** The office issues statements per person where giving is personal, per household where it is joint — and can combine a household view on request without re-entering data.

## Goals

- A payment or pledge can be attributed to a person directly; family attribution remains fully supported for joint giving.
- Envelopes can be assigned at the same grain as giving (per person where giving is personal).
- Giving history is visible on the person as well as the family, each showing the truth at its grain.
- Statements follow the giving grain, with a defined household-combined option.
- Existing family-attributed history keeps its meaning: no silent re-attribution, no double counting in any report or dashboard total.
- The deposit-entry workflow works for person donors, family donors and anonymous gifts without workarounds.

## Non-goals

- **No forced migration.** Churches that give purely by family keep exactly today's behaviour unless they opt into person-level records.
- **Not an organisations RFC.** If RFC 0010 adopts organisations as contacts, organisational giving joins this donor model as a later step; it is not decided here.
- **No architecture in this RFC.** Schema shape (donor reference design, migration of historical rows) is decided in the post-approval architecture doc, per the RFC process.

## Open questions (maintainer decisions)

1. **Donor model.** (a) Donor is *either* a person *or* a family on each record; (b) a distinct "giving unit" entity that wraps a person or a family; (c) giving always lands on a person, with family giving as a roll-up view. Which grain owns the record?
2. **Statements.** Per-person statements only at the person's grain, or can a household elect one combined statement covering both grains? Whose name(s) print?
3. **Envelopes.** Do envelope assignments follow the donor model per record, or stay family-level with person-level as an override?
4. **Household totals.** Where family totals appear today (dashboards, reports, family page), do they sum both grains, and is a person-grain gift to "the family fund" counted once, visibly?
5. **Portal reconciliation.** Under RFC 0004, a signed-in member's online gift attributes to — the person, their family, or their choice at gift time?

## Linked issues

Recurring asks consolidated for this decision: #4133 (anchor), #2972 (folded). Friction at the current grain: #9615, #367. Consumers that must be designed with the grain, not after it: envelope reports (#2933), pledge reporting and staff workflows (#2398), portal giving (RFC 0004).
