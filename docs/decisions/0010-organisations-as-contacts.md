# RFC: Organisations as contacts

> **BLUF**
> Churches relate to organisations — businesses that sponsor or supply them, partner churches, mission agencies, denominations — as contacts in their own right, but ChurchCRM's only contact entities are persons and families, so organisations are shoehorned into family records and inherit household semantics everywhere (head/spouse roles, directory presentation, one-family-per-person). The existing workaround, tagging with classifications, does not hold: classification is a single-value membership axis on persons, not an entity type. This RFC proposes deciding how organisations become first-class contacts. The candidate direction from the #2478 discussion is a **typed family** — a type flag (Household / Organisation) on the family record that reuses the family machinery for address, directory and giving while presenting as an organisation — weighed against a fully separate organisation entity. Giving attribution, including soft credits for people connected to an organisation's gifts, is part of the question.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issue:** [#2478](https://github.com/ChurchCRM/CRM/issues/2478) · **Related:** [#11164](https://github.com/ChurchCRM/CRM/issues/11164) (messaging audiences), [RFC 0004](0004-portal-giving-pledges.md) (portal giving)
**Numbering:** `0010` is provisional. A maintainer assigns the final number on merge.

## Summary

- **Organisations are contacts, not households.** They need their own name, address, phone/email, notes and giving record, presented as an organisation everywhere the system shows them.
- **Classifications cannot carry this.** Classification is one value per person expressing membership status; it cannot give an organisation an identity, and spending it on "Organisation" breaks the membership reporting it exists for.
- **People relate to organisations.** A person is an organisation's contact, employee, or owner; that relationship must not consume the person's one household membership.
- **Giving needs an answer.** Organisations give. Whether a connected person receives a soft credit for an organisation's gift (the CiviCRM model discussed in #2478) is in scope for the decision.
- **Existing org-as-family records must migrate cleanly** into whichever model is chosen; churches have years of them.

## Use cases

1. **Partner and vendor records.** The office keeps the church's landlord, its mission partners and its suppliers as contacts with addresses and key people, without them appearing as households in the member directory.
2. **Sponsorships and grants.** A business sponsors the youth trip; the gift is recorded to the organisation, and the business owner (a member) is visibly connected to it.
3. **Denominational relationships.** The church's parent denomination and sister churches are contacts the pastor's team references and communicates with.
4. **Communication.** Staff email or text an organisation's contact people (ties into the messaging audiences of RFC 0009) without emailing a fake family's "members".

## Goals

- An organisation can be created, found, edited and deleted as itself — never as a pretend household.
- A person can be linked to any number of organisations with a named relationship, independent of their household.
- Organisation giving is recorded and reported as organisational giving; the soft-credit behaviour is decided explicitly, one way or the other.
- Directory, search, cart and messaging treat organisations according to their type (excluded from the member directory by default; includable in their own right where staff choose).
- No data loss or manual re-entry for churches that modelled organisations as families.

## Non-goals

- **Not a CRM/ERP.** No invoicing, contracts, or vendor management beyond contact and giving records.
- **No multi-church tenancy.** Organisations here are external contacts of one church, not linked installs (that is the #1992 territory, tracked with RBAC/portal work).
- **No architecture in this RFC.** Entity shape, schema and migration mechanics are decided in the post-approval architecture doc, per the RFC process.

## Open questions (maintainer decisions)

1. **Model.** Typed family (Household / Organisation flag on the family record, reusing its machinery) versus a separate organisation entity with its own table and UI?
2. **Soft credits.** In scope for the first slice, deferred, or explicitly out — does a person get credited when their organisation gives?
3. **Relationships.** A fixed relationship list (Contact, Employee, Owner…) or free-form? Do relationships carry dates?
4. **Directory and reporting defaults.** Organisations out of the member directory always, or a per-organisation choice? Counted in family statistics?
5. **Portal.** Can an organisation's contacts see or act on anything in the Member Portal under this model, or is it staff-side only in v1?

## Linked issues

Proposal and full discussion: #2478 (2017, three outside reporters, CiviCRM comparison, typed-entity suggestion). This RFC's decision sets that issue's implementation direction.
