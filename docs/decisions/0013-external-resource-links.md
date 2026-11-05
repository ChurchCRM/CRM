# RFC: External resource links with visibility

> **BLUF**
> Churches keep their real working documents outside ChurchCRM — event flyers and registration forms in Google Drive, permission slips in shared folders, sheet music in Dropbox, budgets in spreadsheets. What users keep asking for is not another file host: it is a way to *attach those documents to the records in ChurchCRM* — above all to events — so the flyer, the form and the sign-up sheet sit next to the event they belong to, visible to exactly the right audience. Issue #350 has carried the general ask ("File Upload and management", event forms, sheet music, downloads, worship planning) since 2016, and #8950 already specifies the events case in detail: multiple labeled external links per event with per-link visibility across the public calendar, the member portal and staff. This RFC proposes the general capability: **labeled links to external resources, attached to ChurchCRM records, each with an explicit visibility audience** — starting from external links only, with no file upload or hosting.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-05
**Owner:** @dawoudio
**Proposal issues:** [#350](https://github.com/ChurchCRM/CRM/issues/350) (File Upload and management) · [#8950](https://github.com/ChurchCRM/CRM/issues/8950) (Event resource links with public calendar, member portal, and staff visibility) · **Related:** [#817](https://github.com/ChurchCRM/CRM/issues/817) (event planning and resources)
**Numbering:** `0013` is provisional. A maintainer assigns the final number on merge.

## Maintainer direction already recorded

- **Start with external links, not uploads.** The first step is linking to files hosted elsewhere; ChurchCRM does not become a file host (decision on #350, 2026-11-05).
- **Build separately from the Custom Menu Links plugin.** The existing core plugin (`custom-links`) manages one global list of navigation shortcuts in the `menu_links` table. It stays untouched and keeps doing that job; this link system is a separate capability, not an expansion, replacement or migration of the plugin.

## Summary

- **The demand is on events first.** People ask for the flyer, the registration form and the permission slip *on the event* — that is #8950's scope, and it is the lead use case.
- **Visibility is the product.** The same event may carry a public flyer, a member-only sign-up sheet and a staff-only planning doc. A link without an audience is how confidential forms end up on a public calendar.
- **A link is not access control.** ChurchCRM decides who *sees* the link; the external provider (Drive, Dropbox, …) decides who can *open* it. Editors must be warned of that difference at the moment they widen visibility.
- **Links, not files.** No upload, storage, proxying or synchronisation of document contents — that keeps ChurchCRM out of the file-hosting business and keeps the privacy story honest.

## Use cases

1. **Event flyer and forms.** An event carries its flyer (public), its registration form (members), and its parental-authorisation form (members/staff) as labeled links.
2. **Worship planning.** A service event links its sheet music set and running order, visible to the worship team and staff, not the whole directory.
3. **Group resources.** A small group links its study guide and shared folder on the group, visible to group members.
4. **Person/family documents.** Staff link a family's externally stored documents (e.g. a signed form held in the church's Drive) on the family record, staff-only.
5. **Event series resources.** A recurring ministry event links the same standing resources across its occurrences without re-entering them per date.

## Goals

- A record (event first; group, person and family as decided below) can carry multiple labeled external links, in a stable order.
- Every link has exactly one visibility audience — **public** (anonymous public views/feeds), **members** (authenticated, authorised portal members), or **staff only** — and new links default to the most restrictive audience.
- Visibility is honoured on every surface the record appears on — public calendar and feeds, member portal, staff UI, and any API or export — never by merely hiding a link in one UI.
- Editors are warned, when setting a link public or member-visible, that the external provider's own sharing settings are what actually protect the document.
- Only safe web URLs (http/https) are accepted as links.

## Non-goals

- **No file upload, hosting, storage or proxying.** If native file management is ever wanted, it is a separate decision (and would reopen #350's wider scope consciously).
- **No document synchronisation or embedding/preview** of external content.
- **No changes to the Custom Menu Links plugin** or the `menu_links` table (maintainer decision above).
- **No architecture in this RFC.** Data model, enforcement points and feed/portal plumbing are decided in the post-approval architecture doc, per the RFC process. #8950's security and acceptance requirements carry into that doc unchanged.

## Open questions (maintainer decisions)

1. **Scope of v1.** Events only (exactly #8950), or events + groups from the start? Do person/family links belong in v1 at all, or later?
2. **Series vs occurrence.** Do links attach to an event series, an individual occurrence, or both — and what happens to links when an occurrence is edited or cancelled?
3. **Audience definitions.** Is "members" the member-portal audience exactly as #8950 defines it, and does member visibility always include staff? Which permission governs *publishing a link to public* (as opposed to adding a staff-only one)?
4. **Reuse.** Can one link be attached to several records (a standing "church forms" link on every youth event), or is each attachment a separate copy?
5. **Link kinds.** Free-form labels only, or a small set of kinds (flyer, form, document, spreadsheet, folder, other) for icons and filtering?

## Linked issues

Proposal and discussion: #350 (general file/link ask, 2016), #8950 (the events specification this RFC generalises). Historical: #817 (event planning and resource allocation, closed) raised event resources in 2016 without the links/visibility framing.
