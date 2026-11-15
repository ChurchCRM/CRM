# RFC: Multi-campus support

> **BLUF**
> ChurchCRM assumes one congregation in one place. A growing number of churches are one church in several places — multiple campuses under one leadership, one membership and one set of books — and today their only option is a separate ChurchCRM install per campus, which duplicates people, blocks cross-campus reporting and multiplies upgrades. Issue [#8694](https://github.com/ChurchCRM/CRM/issues/8694) carries this ask; the maintainer moved it to an RFC on 2026-11-14. This RFC proposes **a Campus entity with campus scoping on the records churches actually segment — people and families (home campus), groups, events and deposits — inside the existing single database, with church-wide roll-ups as the default reporting posture** — and leaves the scope boundaries (which records get a campus dimension, how campus interacts with permissions, and whether denominational hierarchies are in scope) as explicit maintainer decisions. Demand today is honestly thin; this RFC exists so that if the project builds it, it builds the right shape.

**Status:** Draft for maintainer review (no decision yet); no release commitment
**Date:** 2026-11-14
**Owner:** @dawoudio
**Proposal issue:** [#8694](https://github.com/ChurchCRM/CRM/issues/8694) (Multi-campus / multi-branch support, migrated from Discussion #6681)
**Numbering:** `0020` is provisional. A maintainer assigns the final number on merge. (Master's merged RFCs top out at 0017; 0018 and 0019 are concurrent drafts.)

## Problem

- **ChurchCRM's data model is single-congregation.** There is no campus, location or branch dimension on people, families, groups, events or finances. A church with two campuses cannot say which campus a family belongs to, run a group at one campus, or report giving per campus.
- **The workaround is separate installs.** One commenter on #8694 runs roughly twenty separate databases — one per location — purely to separate users, church names and data. That means duplicated people (a family attending two campuses exists twice), no cross-campus reporting of any kind, and twenty installs to upgrade and back up instead of one.
- **Prior requests died without a design.** Earlier asks were closed `not planned` in 2024 (#5183 multiple databases, #5214), and #2335 (2017) closed with a much narrower scope. The project has never recorded what "multi-campus" should mean in ChurchCRM, so every new request restarts the same conversation.
- **The church is one entity even when it has many campuses.** A multi-campus church is not several churches sharing a brand: it is one membership, one leadership, one legal and financial entity. Members belong to a home campus but attend services, groups and events across campuses. Giving is tracked per campus because campus budgets are real, while the books, the annual report and denominational statistics are church-wide. Any model that treats campuses as silos (separate installs, separate databases) fights how these churches actually work.

## Use cases

1. **Home campus.** A family joins at the North campus. Their record says so — but they can be checked in, grouped and reported at any campus, and they appear once in the church's membership.
2. **Campus groups and events.** A small group meets at the South campus; a class runs at both. Groups and events carry a campus (or are explicitly church-wide), so campus staff see their own calendar without losing the shared one. A church-wide event — a spring fund drive announced everywhere — is scoped to all campuses; a congregation-only event stays local.
3. **Per-campus giving, church-wide books.** Sunday's deposits are recorded against the campus that received them, feeding campus budgets — while funds themselves, year-end statements and denominational reports roll up across the whole church.
4. **Campus-scoped staff.** A campus administrator manages her campus's people, groups and events without write access to other campuses; central staff and pastors hold access to all campuses. Volunteer and staff permissions follow the same scoping.
5. **Leadership views.** The executive team sees the combined roll-up (total membership, attendance and giving); a campus pastor sees her campus; both are views over the same records, not separate reports.
6. **Portal and kiosk.** A member opening the Member Portal lands in their home campus's context (its events, its groups) without being walled off from church-wide content. Check-in kiosks already bind to a calendar (see the kiosk slices under #11481); a campus is the natural unit a kiosk serves.

## Proposal sketch

This RFC takes no position on schema or UI; the shape it proposes for decision is:

- **A first-class Campus entity** (name, address, active flag) managed by administrators, analogous to how churches already manage groups and event types.
- **Campus scoping inside the existing single database** — rows gain a campus dimension where segmentation is real; nothing here proposes multiple databases or multi-tenancy.
- **Home campus on people and families** — a family's home campus is its primary affiliation; attendance, group membership and giving history remain free to span campuses.
- **Campus on groups and events** — each group and event belongs to a campus or is explicitly church-wide.
- **Campus on deposits** — money is recorded where it was received; funds stay church-wide (see Q3).
- **Church-wide roll-up is the default reporting posture** — every report that can be filtered by campus defaults to all campuses, so single-campus churches see no change and multi-campus leadership gets its combined view first and its per-campus views by filtering.

## Goals

- One ChurchCRM install serves one church with any number of campuses, with members recorded once.
- Per-campus segmentation where churches genuinely segment: people/families, groups, events, giving.
- Church-wide roll-ups (membership, attendance, giving, denominational statistics) available without exports or merged spreadsheets.
- Campus-scoped staff and volunteer access, interacting cleanly with the RBAC model (RFC 0002).
- A migration path for today's single-campus installs that is a non-event: one default campus, everything backfilled, nothing visibly different until a second campus is added.

## Non-goals

- **No architecture or data design in this RFC.** Tables, fields and UI placement are decided in the post-approval architecture doc, per the RFC process. The data-model sketch in #8694's thread (a locations table, a primary-location link on person/family) is input to that doc, not a decision this RFC ratifies.
- **No multi-tenancy or hosted-service design.** Campuses are locations of one church in one install, not tenants. Nothing here speaks to running unrelated churches in one database — except as a scope question (Q6) about denominational hierarchies, which are related but not the same thing.
- **No inter-install synchronisation.** Churches already running per-campus installs would migrate into one install; live sync between installs is not proposed.

## Demand signal (recorded honestly)

- #8694 has been open since 2026-04 to gauge demand and carries **3 👍 reactions** as of 2026-11-14, plus two detailed use cases in its comments (a ~20-location operator forced into ~20 databases; a diocesan judicatory tracking 70 parishes). That is real pain, but it is not yet broad demand.
- What would change the calculus: sustained 👍/comment growth on #8694, additional concrete multi-campus use cases (especially from churches that would adopt ChurchCRM *because* of this), or a denominational body interested in the judicatory shape (Q6).

## Open questions (maintainer decisions)

1. **What exactly gets a campus dimension?** The proposal scopes people/families, groups, events and deposits. What about users (is a login inherently all-campus with scoped *permissions*, or campus-bound?), system settings (one church name and logo, or per-campus letterheads and names — the 20-database commenter separates church names per location), calendars, volunteer opportunities, and custom fields? Each record type added multiplies the migration and UI surface.
2. **Funds: global or campus-mapped?** A deposit clearly happens at a campus. A fund is a church-wide designation (Building Fund, Missions). Does anything financial besides the deposit need a campus — pledge campaigns, budgets — or is deposit-level scoping sufficient for campus budgets?
3. **Campus and RBAC (RFC 0002).** Is campus a dimension *of* permissions (a role granted per campus) or an orthogonal filter (you hold a role; your campus scope limits where it applies)? How do all-campus users work — an explicit grant, or the absence of a scope? This RFC assumes the RBAC redesign owns the mechanism and multi-campus owns the requirement; the boundary needs a maintainer call.
4. **Member Portal and kiosk shape.** Portal: does a member see a home-campus view by default with a campus switcher, or a single blended view with campus labels? Kiosk: kiosks already bind per calendar — should campus become the binding (a kiosk *is* a campus's kiosk, with its calendars and groups implied), and does check-in record the campus of attendance automatically?
5. **Migration for existing installs.** Automatic creation of a single default campus with all existing rows backfilled on upgrade seems obviously right — but is campus then *invisible* until a second campus exists (feature dormant), or visible-but-trivial? And what is the story for churches merging existing per-campus installs into one — a one-off import path, or out of scope for v1?
6. **Denominational hierarchies: in or out?** #8694's judicatory commenter needs a hierarchy — 70 parishes under 9 deaneries under 7 archdeaconries, with visibility across parishes but edits restricted to parish admins and judicatory super-admins, plus annual statistical collection. That is multi-*church* with levels, not multi-campus. Including it makes campuses nestable and units semi-autonomous; excluding it keeps campuses flat locations of one congregation. This is the largest single scope decision in the RFC.
7. **Events: campus-only vs church-wide default.** When a user creates an event without thinking about campuses, is it church-wide (visible everywhere) or local to the creator's campus? The fund-drive use case wants deliberate all-campus events; the congregation-only case wants deliberate local ones. The default decides which mistake users make.

## Alternatives considered

- **Separate installs per campus (the de facto answer today).** Zero development cost, total segmentation — at the price of duplicated people, no cross-campus reporting, no shared membership, and N installs to upgrade, secure and back up. It is the workaround #8694's commenters are trying to escape.
- **Multiple databases in one install (#5183, closed not planned 2024).** Segmentation at the database level gives isolation without a campus model, but cross-campus anything — roll-ups, a person attending two campuses, denominational statistics — requires merging databases at report time, which is where the real need lives.
- **Groups-as-campuses.** Churches can fake campuses today with groups ("North Campus Members"). It costs nothing to ship, but nothing enforces it: groups don't scope events, deposits, permissions or the portal, and campus pseudo-groups pollute the ministry group lists they coexist with.
- **Do nothing.** The demand signal is 3 👍. If it stays there, this RFC can sit in draft indefinitely at no cost; the Alternatives above remain the answer. The RFC's value in that world is that the design questions are recorded once, with the prior history (#2335, #5183, #5214, Discussion #6681), instead of being re-litigated per request.

## Linked issues

Proposal and discussion: #8694 (multi-campus / multi-branch support; migrated from Discussion #6681, carrying the original request from @tobsowo and the two detailed use cases summarised in the Demand signal section). Prior history: #2335 (multi-church functionality, 2017, closed completed — narrow scope), #5183 (multiple databases, closed not planned 2024), #5214 (closed not planned 2024).

Related: [RFC 0002](0002-rbac-redesign.md) (RBAC redesign — campus-scoped permissions, Q3), [RFC 0007](0007-event-locations.md) (event locations — a campus is not a room or a place record; the architecture doc must keep the two concepts distinct), and the kiosk binding work tracked under #11481 (Q4).
